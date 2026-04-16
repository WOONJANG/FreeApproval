<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_member_manager($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('admin/users.php');
}
verify_csrf_or_fail();
$userId = (int) ($_POST['user_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM `approval_users` WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$member = $stmt->fetch();
if (!$member || (!can_manage_member($pageUser, $member) && $userId !== (int) $pageUser['id'])) {
    set_flash('error', '수정할 수 없는 회원입니다.');
    redirect_to('admin/users.php');
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = normalize_email((string) ($_POST['email'] ?? ''));
$phone = normalize_phone((string) ($_POST['phone'] ?? ''));
$levelNo = max(1, (int) ($_POST['level_no'] ?? 1));
$jobTitle = trim((string) ($_POST['job_title'] ?? ''));
$role = (string) ($_POST['role'] ?? ($member['role'] ?? 'user'));
$isActive = isset($_POST['is_active']) ? 1 : 0;
$newPassword = (string) ($_POST['new_password'] ?? '');
$approverIds = $_POST['approver_ids'] ?? [];
if (!is_array($approverIds)) {
    $approverIds = [];
}

$canAssignDelegation = can_assign_management_permissions($pageUser);
$canEditApprovalLine = $canAssignDelegation && $member['role'] !== 'super_admin';
$canManageMembersValue = $canAssignDelegation && $member['role'] !== 'super_admin' ? (isset($_POST['can_manage_members']) ? 1 : 0) : (int) ($member['can_manage_members'] ?? 0);
$canManageNoticesValue = $canAssignDelegation && $member['role'] !== 'super_admin' ? (isset($_POST['can_manage_notices']) ? 1 : 0) : (int) ($member['can_manage_notices'] ?? 0);

if ($name === '') {
    set_flash('error', '이름은 필수입니다.');
    redirect_to('admin/user_edit.php?id=' . $userId);
}
if (($member['role'] ?? 'user') !== 'super_admin') {
    if ($phone === '' || !preg_match('/^\d{10,11}$/', $phone)) {
        set_flash('error', '전화번호는 숫자 10~11자리로 입력하세요.');
        redirect_to('admin/user_edit.php?id=' . $userId);
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', '이메일 형식이 올바르지 않습니다.');
        redirect_to('admin/user_edit.php?id=' . $userId);
    }
    if (!ensure_phone_available($pdo, $phone, $userId)) {
        set_flash('error', '이미 사용 중인 전화번호입니다.');
        redirect_to('admin/user_edit.php?id=' . $userId);
    }
    if (!ensure_email_available($pdo, $email, $userId)) {
        set_flash('error', '이미 사용 중인 이메일입니다.');
        redirect_to('admin/user_edit.php?id=' . $userId);
    }
}
if ($userId === (int) $pageUser['id'] && $isActive === 0) {
    set_flash('error', '현재 로그인 중인 계정은 비활성화할 수 없습니다.');
    redirect_to('admin/user_edit.php?id=' . $userId);
}

if (($member['role'] ?? 'user') === 'admin' && (int) ($member['company_id'] ?? 0) > 0) {
    if ($isActive === 0 && is_last_company_admin($pdo, (int) $member['company_id'], $userId)) {
        set_flash('error', '마지막 회사 대표 계정은 비활성화할 수 없습니다.');
        redirect_to('admin/user_edit.php?id=' . $userId);
    }
}
if (($member['role'] ?? 'user') === 'admin' && $role !== 'admin' && (int) ($member['company_id'] ?? 0) > 0 && is_last_company_admin($pdo, (int) $member['company_id'], $userId)) {
    set_flash('error', '마지막 회사 대표 계정은 다른 권한으로 변경할 수 없습니다.');
    redirect_to('admin/user_edit.php?id=' . $userId);
}
if (!is_super_admin($pageUser)) {
    $role = $member['role'];
}
if (($member['role'] ?? '') === 'super_admin') {
    $role = 'super_admin';
    $canManageMembersValue = (int) ($member['can_manage_members'] ?? 1);
    $canManageNoticesValue = (int) ($member['can_manage_notices'] ?? 1);
}
if (($member['role'] ?? 'user') === 'admin' && !is_super_admin($pageUser) && $userId !== (int) $pageUser['id']) {
    set_flash('error', '회사 대표계정은 최상위 관리자만 수정할 수 있습니다.');
    redirect_to('admin/users.php');
}

try {
    $pdo->beginTransaction();
    $params = [$name, $email !== '' ? $email : null, $phone !== '' ? $phone : null, $jobTitle, $canManageMembersValue, $canManageNoticesValue, $isActive];
    $sql = 'UPDATE `approval_users` SET name = ?, email = ?, phone = ?, job_title = ?, can_manage_members = ?, can_manage_notices = ?, is_active = ?, failed_login_attempts = 0, locked_until = NULL, updated_at = NOW()';
    if (($member['role'] ?? 'user') !== 'super_admin') {
        $sql .= ', level_no = ?, role = ?';
        $params[] = $levelNo;
        $params[] = in_array($role, ['user', 'admin'], true) ? $role : 'user';
    }
    if ($newPassword !== '') {
        if (mb_strlen($newPassword) < 8) {
            throw new RuntimeException('비밀번호는 8자 이상이어야 합니다.');
        }
        $sql .= ', password_hash = ?';
        $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    $sql .= ' WHERE id = ?';
    $params[] = $userId;
    $pdo->prepare($sql)->execute($params);

    if (($member['role'] ?? 'user') !== 'super_admin' && $canEditApprovalLine) {
        $validApproverIds = [];
        if (!empty($member['company_id'])) {
            $optionIds = array_map(static fn(array $row): int => (int) $row['id'], get_assignable_approvers($pdo, $userId, (int) $member['company_id']));
            foreach ($approverIds as $approverId) {
                $approverId = (int) $approverId;
                if (in_array($approverId, $optionIds, true)) {
                    $validApproverIds[] = $approverId;
                }
            }
        }
        sync_user_approvers($pdo, $userId, $validApproverIds);
    }

    $pdo->commit();
    log_activity($pdo, $pageUser, (int) ($member['company_id'] ?? ($pageUser['company_id'] ?? 0)), 'user_saved', $member['name'] . ' 회원 정보 저장', 'user', $userId);
    set_flash('success', '회원 정보가 저장되었습니다.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
}
redirect_to('admin/user_edit.php?id=' . $userId);
