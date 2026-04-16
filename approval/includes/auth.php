<?php
declare(strict_types=1);

function current_user(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cached = null;
    if ($cached && (int) $cached['id'] === (int) $_SESSION['user_id']) {
        return $cached;
    }

    $stmt = $pdo->prepare(
        'SELECT u.*, c.company_name, c.company_code, c.owner_name, c.owner_phone, c.is_active AS company_is_active,
                c.service_status, c.plan_name, c.member_limit, c.document_limit, c.trial_ends_at, c.adfree_until, c.allow_bulk_approval, c.locale
         FROM `approval_users` u
         LEFT JOIN `approval_companies` c ON c.id = u.company_id
         WHERE u.id = ?
         LIMIT 1'
    );
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !(int) $user['is_active']) {
        unset($_SESSION['user_id']);
        return null;
    }

    if (($user['role'] ?? 'user') !== 'super_admin' && (isset($user['company_is_active']) && (int) $user['company_is_active'] !== 1 || in_array((string) ($user['service_status'] ?? 'active'), ['suspended','expired'], true))) {
        unset($_SESSION['user_id']);
        return null;
    }

    $cached = $user;
    return $cached;
}

function require_login(PDO $pdo): array
{
    $user = current_user($pdo);
    if (!$user) {
        set_flash('error', '로그인이 필요합니다.');
        redirect_to('login.php');
    }
    return $user;
}

function is_super_admin(array $user): bool
{
    return ($user['role'] ?? 'user') === 'super_admin';
}

function is_company_admin(array $user): bool
{
    return ($user['role'] ?? 'user') === 'admin';
}

function can_manage_members(array $user): bool
{
    return is_super_admin($user) || is_company_admin($user) || ((int) ($user['can_manage_members'] ?? 0) === 1 && !empty($user['company_id']));
}

function can_manage_notices(array $user): bool
{
    return is_super_admin($user) || is_company_admin($user) || ((int) ($user['can_manage_notices'] ?? 0) === 1 && !empty($user['company_id']));
}

function has_company_delegate_permissions(array $user): bool
{
    return !is_super_admin($user) && !is_company_admin($user) && (can_manage_members($user) || can_manage_notices($user));
}

function can_assign_management_permissions(array $user): bool
{
    return is_super_admin($user) || is_company_admin($user);
}

function is_admin(array $user): bool
{
    return is_super_admin($user) || is_company_admin($user);
}

function require_admin(PDO $pdo): array
{
    $user = require_login($pdo);
    if (!is_admin($user)) {
        http_response_code(403);
        exit('관리자만 접근할 수 있습니다.');
    }
    return $user;
}

function require_member_manager(PDO $pdo): array
{
    $user = require_login($pdo);
    if (!can_manage_members($user)) {
        http_response_code(403);
        exit('회원 관리 권한이 없습니다.');
    }
    return $user;
}

function require_notice_manager(PDO $pdo): array
{
    $user = require_login($pdo);
    if (!can_manage_notices($user)) {
        http_response_code(403);
        exit('공지 관리 권한이 없습니다.');
    }
    return $user;
}

function require_company_settings_manager(PDO $pdo): array
{
    $user = require_login($pdo);
    if (!(is_super_admin($user) || is_company_admin($user) || can_manage_members($user))) {
        http_response_code(403);
        exit('회사 설정 권한이 없습니다.');
    }
    return $user;
}

function require_super_admin(PDO $pdo): array
{
    $user = require_login($pdo);
    if (!is_super_admin($user)) {
        http_response_code(403);
        exit('최상위 관리자만 접근할 수 있습니다.');
    }
    return $user;
}

function same_company(array $user, ?int $companyId): bool
{
    if (is_super_admin($user)) {
        return true;
    }
    return $companyId !== null && (int) $user['company_id'] === $companyId;
}

function ensure_phone_available(PDO $pdo, string $phone, ?int $ignoreUserId = null): bool
{
    $sql = 'SELECT id FROM `approval_users` WHERE phone = ?';
    $params = [$phone];
    if ($ignoreUserId) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreUserId;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return !$stmt->fetch();
}

function ensure_email_available(PDO $pdo, string $email, ?int $ignoreUserId = null): bool
{
    if ($email === '') {
        return true;
    }
    $sql = 'SELECT id FROM `approval_users` WHERE email = ?';
    $params = [$email];
    if ($ignoreUserId) {
        $sql .= ' AND id <> ?';
        $params[] = $ignoreUserId;
    }
    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return !$stmt->fetch();
}

function clear_login_failures(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('UPDATE `approval_users` SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?');
    $stmt->execute([$userId]);
}

function register_failed_login(PDO $pdo, array $user): void
{
    $attempts = (int) $user['failed_login_attempts'] + 1;
    $lockedUntil = null;
    if ($attempts >= LOGIN_MAX_ATTEMPTS) {
        $lockedUntil = date('Y-m-d H:i:s', strtotime('+' . LOGIN_LOCK_MINUTES . ' minutes'));
        $attempts = 0;
    }
    $stmt = $pdo->prepare('UPDATE `approval_users` SET failed_login_attempts = ?, locked_until = ? WHERE id = ?');
    $stmt->execute([$attempts, $lockedUntil, (int) $user['id']]);
}

function user_is_locked(array $user): bool
{
    if (empty($user['locked_until'])) {
        return false;
    }
    return strtotime((string) $user['locked_until']) > time();
}

function login_failure_message(array $user): string
{
    if (user_is_locked($user)) {
        return '로그인 시도가 너무 많아 잠시 잠겼습니다. ' . format_datetime($user['locked_until']) . ' 이후 다시 시도하세요.';
    }
    return '회사코드, 전화번호/이메일 또는 관리자 아이디, 비밀번호가 올바르지 않거나 비활성 계정입니다.';
}

function login_by_credentials(PDO $pdo, string $identifier, string $password, string $companyCode = ''): array
{
    $identifier = trim($identifier);
    $phone = normalize_phone($identifier);
    $email = normalize_email($identifier);
    $companyCode = normalize_company_code($companyCode);

    $superStmt = $pdo->prepare(
        'SELECT u.*, c.company_name, c.company_code, c.owner_name, c.owner_phone, c.is_active AS company_is_active,
                c.service_status, c.plan_name, c.member_limit, c.document_limit, c.trial_ends_at, c.adfree_until, c.allow_bulk_approval, c.locale
         FROM `approval_users` u
         LEFT JOIN `approval_companies` c ON c.id = u.company_id
         WHERE u.role = "super_admin" AND u.login_id = ?
         LIMIT 1'
    );
    $superStmt->execute([$identifier]);
    $superUser = $superStmt->fetch();
    if ($superUser) {
        if (!(int) $superUser['is_active']) {
            return ['ok' => false, 'message' => '관리자 아이디 또는 비밀번호가 올바르지 않거나 비활성 계정입니다.'];
        }
        if (user_is_locked($superUser)) {
            return ['ok' => false, 'message' => login_failure_message($superUser)];
        }
        if (!password_verify($password, $superUser['password_hash'])) {
            register_failed_login($pdo, $superUser);
            $superStmt->execute([$identifier]);
            $fresh = $superStmt->fetch() ?: $superUser;
            return ['ok' => false, 'message' => login_failure_message($fresh)];
        }

        clear_login_failures($pdo, (int) $superUser['id']);
        $_SESSION['user_id'] = (int) $superUser['id'];
        session_regenerate_id(true);
        return ['ok' => true, 'user' => $superUser];
    }

    if ($companyCode === '') {
        return ['ok' => false, 'message' => '개인/회사 관리자 로그인은 회사코드가 필요합니다.'];
    }

    $stmt = $pdo->prepare(
        'SELECT u.*, c.company_name, c.company_code, c.owner_name, c.owner_phone, c.is_active AS company_is_active,
                c.service_status, c.plan_name, c.member_limit, c.document_limit, c.trial_ends_at, c.adfree_until, c.allow_bulk_approval, c.locale
         FROM `approval_users` u
         INNER JOIN `approval_companies` c ON c.id = u.company_id
         WHERE c.company_code = ? AND (u.phone = ? OR u.email = ?)
         LIMIT 1'
    );
    $stmt->execute([$companyCode, $phone, $email]);
    $user = $stmt->fetch();

    if (!$user || !(int) $user['is_active']) {
        return ['ok' => false, 'message' => '회사코드, 전화번호/이메일, 비밀번호가 올바르지 않거나 비활성 계정입니다.'];
    }

    if (($user['role'] ?? 'user') === 'super_admin') {
        return ['ok' => false, 'message' => '최상위 관리자 계정은 관리자 아이디로 로그인하세요.'];
    }

    if (isset($user['company_is_active']) && (int) $user['company_is_active'] !== 1) {
        return ['ok' => false, 'message' => '소속 회사가 비활성화되어 로그인할 수 없습니다.'];
    }
    if (in_array((string) ($user['service_status'] ?? 'active'), ['suspended', 'expired'], true)) {
        return ['ok' => false, 'message' => '소속 회사 이용상태가 정지 또는 만료 상태라 로그인할 수 없습니다.'];
    }

    if (user_is_locked($user)) {
        return ['ok' => false, 'message' => login_failure_message($user)];
    }

    if (!password_verify($password, $user['password_hash'])) {
        register_failed_login($pdo, $user);
        $stmt->execute([$companyCode, $phone, $email]);
        $fresh = $stmt->fetch() ?: $user;
        return ['ok' => false, 'message' => login_failure_message($fresh)];
    }

    clear_login_failures($pdo, (int) $user['id']);
    $_SESSION['user_id'] = (int) $user['id'];
    session_regenerate_id(true);
    return ['ok' => true, 'user' => $user];
}

function logout_current_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function can_manage_member(array $manager, array $member): bool
{
    if (is_super_admin($manager)) {
        return true;
    }
    if (($member['role'] ?? 'user') === 'super_admin') {
        return false;
    }
    if (empty($manager['company_id']) || (int) $manager['company_id'] !== (int) ($member['company_id'] ?? 0)) {
        return false;
    }
    if ((int) $manager['id'] === (int) ($member['id'] ?? 0)) {
        return true;
    }
    if (is_company_admin($manager)) {
        return ($member['role'] ?? 'user') === 'user';
    }
    if (can_manage_members($manager)) {
        return ($member['role'] ?? 'user') === 'user';
    }
    return false;
}
