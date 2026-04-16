<?php
declare(strict_types=1);

function get_company_by_id(PDO $pdo, int $companyId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM `approval_companies` WHERE id = ? LIMIT 1');
    $stmt->execute([$companyId]);
    return $stmt->fetch() ?: null;
}

function company_scope_for_user(array $user, int $requestedCompanyId = 0): int
{
    if (is_super_admin($user)) {
        return $requestedCompanyId;
    }
    return (int) ($user['company_id'] ?? 0);
}

function get_company_settings(PDO $pdo, array $user, int $requestedCompanyId = 0): ?array
{
    $companyId = company_scope_for_user($user, $requestedCompanyId);
    if ($companyId <= 0) {
        return null;
    }
    return get_company_by_id($pdo, $companyId);
}

function get_company_categories(PDO $pdo, int $companyId, bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM `approval_document_categories` WHERE company_id = ?';
    if ($onlyActive) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$companyId]);
    return $stmt->fetchAll();
}

function get_company_templates(PDO $pdo, int $companyId, bool $onlyActive = false): array
{
    $sql = 'SELECT t.*, c.name AS category_name,
               (SELECT COUNT(*) FROM `approval_approval_template_steps` s WHERE s.template_id = t.id) AS step_count,
               (SELECT COUNT(*) FROM `approval_approval_template_readers` r WHERE r.template_id = t.id) AS reader_count,
               (SELECT COUNT(*) FROM `approval_documents` d WHERE d.template_id = t.id) AS usage_count
            FROM `approval_approval_templates` t
            LEFT JOIN `approval_document_categories` c ON c.id = t.category_id
            WHERE t.company_id = ?';
    if ($onlyActive) {
        $sql .= ' AND t.is_active = 1';
    }
    $sql .= ' ORDER BY t.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$companyId]);
    return $stmt->fetchAll();
}

function get_template_by_id(PDO $pdo, int $templateId): ?array
{
    $stmt = $pdo->prepare('SELECT t.*, c.name AS category_name FROM `approval_approval_templates` t LEFT JOIN `approval_document_categories` c ON c.id = t.category_id WHERE t.id = ? LIMIT 1');
    $stmt->execute([$templateId]);
    return $stmt->fetch() ?: null;
}

function get_template_steps(PDO $pdo, int $templateId): array
{
    $stmt = $pdo->prepare('SELECT s.*, u.name, u.job_title, u.level_no FROM `approval_approval_template_steps` s INNER JOIN `approval_users` u ON u.id = s.approver_user_id WHERE s.template_id = ? AND u.is_active = 1 ORDER BY s.step_no ASC');
    $stmt->execute([$templateId]);
    return $stmt->fetchAll();
}

function get_template_approver_ids(PDO $pdo, int $templateId): array
{
    return array_map(static fn(array $row): int => (int) $row['approver_user_id'], get_template_steps($pdo, $templateId));
}

function get_template_readers(PDO $pdo, int $templateId): array
{
    $stmt = $pdo->prepare('SELECT tr.*, u.name, u.job_title, u.level_no FROM `approval_approval_template_readers` tr INNER JOIN `approval_users` u ON u.id = tr.reader_user_id WHERE tr.template_id = ? ORDER BY u.name ASC, u.id ASC');
    $stmt->execute([$templateId]);
    return $stmt->fetchAll();
}

function get_template_reader_ids(PDO $pdo, int $templateId): array
{
    return array_map(static fn(array $row): int => (int) $row['reader_user_id'], get_template_readers($pdo, $templateId));
}

function get_template_usage_count(PDO $pdo, int $templateId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_documents` WHERE template_id = ?');
    $stmt->execute([$templateId]);
    return (int) $stmt->fetchColumn();
}

function sync_template_steps(PDO $pdo, int $templateId, array $approverIds): void
{
    $pdo->prepare('DELETE FROM `approval_approval_template_steps` WHERE template_id = ?')->execute([$templateId]);
    $normalized = [];
    foreach (array_unique(array_map('intval', $approverIds)) as $approverId) {
        if ($approverId > 0) {
            $normalized[] = $approverId;
        }
    }
    if (!$normalized) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($normalized), '?'));
    $stmt = $pdo->prepare('SELECT id, level_no, name FROM `approval_users` WHERE id IN (' . $placeholders . ') ORDER BY level_no ASC, name ASC, id ASC');
    $stmt->execute($normalized);
    $rows = $stmt->fetchAll();
    $insert = $pdo->prepare('INSERT INTO `approval_approval_template_steps` (template_id, step_no, approver_user_id, created_at) VALUES (?, ?, ?, NOW())');
    $stepNo = 1;
    foreach ($rows as $row) {
        $insert->execute([$templateId, $stepNo++, (int) $row['id']]);
    }
}

function sync_template_readers(PDO $pdo, int $templateId, array $readerIds): void
{
    $pdo->prepare('DELETE FROM `approval_approval_template_readers` WHERE template_id = ?')->execute([$templateId]);
    if (!$readerIds) {
        return;
    }
    $insert = $pdo->prepare('INSERT INTO `approval_approval_template_readers` (template_id, reader_user_id, created_at) VALUES (?, ?, NOW())');
    foreach (array_unique(array_map('intval', $readerIds)) as $readerId) {
        if ($readerId > 0) {
            $insert->execute([$templateId, $readerId]);
        }
    }
}

function get_company_members_for_reader(PDO $pdo, int $companyId, int $excludeUserId = 0): array
{
    $sql = 'SELECT id, name, job_title, level_no, role, is_active FROM `approval_users` WHERE company_id = ? AND is_active = 1';
    $params = [$companyId];
    if ($excludeUserId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeUserId;
    }
    $sql .= ' ORDER BY name ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_document_readers(PDO $pdo, int $documentId): array
{
    $stmt = $pdo->prepare('SELECT dr.*, u.name, u.job_title, u.level_no FROM `approval_document_readers` dr INNER JOIN `approval_users` u ON u.id = dr.reader_user_id WHERE dr.document_id = ? ORDER BY u.name ASC');
    $stmt->execute([$documentId]);
    return $stmt->fetchAll();
}

function get_document_reader_ids(PDO $pdo, int $documentId): array
{
    return array_map(static fn(array $row): int => (int) $row['reader_user_id'], get_document_readers($pdo, $documentId));
}

function sync_document_readers(PDO $pdo, int $documentId, array $readerIds): void
{
    $pdo->prepare('DELETE FROM `approval_document_readers` WHERE document_id = ?')->execute([$documentId]);
    if (!$readerIds) {
        return;
    }
    $insert = $pdo->prepare('INSERT INTO `approval_document_readers` (document_id, reader_user_id, created_at) VALUES (?, ?, NOW())');
    foreach (array_unique(array_map('intval', $readerIds)) as $readerId) {
        if ($readerId > 0) {
            $insert->execute([$documentId, $readerId]);
        }
    }
}

function get_company_activity_logs(PDO $pdo, array $user, int $limit = 50, int $requestedCompanyId = 0): array
{
    $companyId = company_scope_for_user($user, $requestedCompanyId);
    $sql = 'SELECT a.*, u.name AS user_name, u.job_title AS user_job_title, c.company_name FROM `approval_activity_logs` a LEFT JOIN `approval_users` u ON u.id = a.user_id LEFT JOIN `approval_companies` c ON c.id = a.company_id';
    $params = [];
    if ($companyId > 0) {
        $sql .= ' WHERE a.company_id = ?';
        $params[] = $companyId;
    }
    $sql .= ' ORDER BY a.id DESC LIMIT ' . (int) $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function is_last_company_admin(PDO $pdo, int $companyId, int $exceptUserId = 0): bool
{
    $sql = 'SELECT COUNT(*) FROM `approval_users` WHERE company_id = ? AND role = "admin" AND is_active = 1';
    $params = [$companyId];
    if ($exceptUserId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $exceptUserId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() === 0;
}

function company_has_capacity(PDO $pdo, int $companyId, string $type): bool
{
    $company = get_company_by_id($pdo, $companyId);
    if (!$company) {
        return false;
    }
    if ($type === 'member' && !empty($company['member_limit'])) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_users` WHERE company_id = ?');
        $stmt->execute([$companyId]);
        return (int) $stmt->fetchColumn() < (int) $company['member_limit'];
    }
    if ($type === 'document' && !empty($company['document_limit'])) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_documents` WHERE company_id = ? AND status <> "deleted"');
        $stmt->execute([$companyId]);
        return (int) $stmt->fetchColumn() < (int) $company['document_limit'];
    }
    return true;
}

function get_company_banner_notices(PDO $pdo, array $user): array
{
    $conditions = ['n.is_banner = 1'];
    $params = [];
    if (!is_super_admin($user)) {
        $conditions[] = '(n.is_global = 1 OR n.company_id = ?)';
        $params[] = (int) $user['company_id'];
    }
    $conditions[] = '(n.active_from IS NULL OR n.active_from <= NOW())';
    $conditions[] = '(n.active_until IS NULL OR n.active_until >= NOW())';
    $sql = 'SELECT n.* FROM `approval_notices` n WHERE ' . implode(' AND ', $conditions) . ' ORDER BY n.is_global DESC, n.is_notice DESC, n.id DESC LIMIT 3';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_company_popup_notices(PDO $pdo, array $user): array
{
    $conditions = ['n.is_popup = 1'];
    $params = [];
    if (!is_super_admin($user)) {
        $conditions[] = '(n.is_global = 1 OR n.company_id = ?)';
        $params[] = (int) $user['company_id'];
    }
    $conditions[] = '(n.active_from IS NULL OR n.active_from <= NOW())';
    $conditions[] = '(n.active_until IS NULL OR n.active_until >= NOW())';
    $sql = 'SELECT n.* FROM `approval_notices` n WHERE ' . implode(' AND ', $conditions) . ' ORDER BY n.is_global DESC, n.is_notice DESC, n.id DESC LIMIT 2';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function create_password_reset_token(PDO $pdo, int $userId): string
{
    $plain = bin2hex(random_bytes(16));
    $hash = password_hash($plain, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO `approval_password_reset_tokens` (user_id, token_hash, expires_at, used_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NULL, NOW())');
    $stmt->execute([$userId, $hash, PASSWORD_RESET_TOKEN_MINUTES]);
    return $plain;
}

function find_valid_password_reset(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->query('SELECT pr.*, u.name, u.email, u.phone, u.company_id, c.company_code, c.company_name FROM `approval_password_reset_tokens` pr INNER JOIN `approval_users` u ON u.id = pr.user_id LEFT JOIN `approval_companies` c ON c.id = u.company_id WHERE pr.used_at IS NULL AND pr.expires_at >= NOW() ORDER BY pr.id DESC');
    foreach ($stmt->fetchAll() as $row) {
        if (password_verify($token, $row['token_hash'])) {
            return $row;
        }
    }
    return null;
}

function use_password_reset_token(PDO $pdo, int $tokenId, string $newPassword): void
{
    $pdo->prepare('UPDATE `approval_password_reset_tokens` SET used_at = NOW() WHERE id = ?')->execute([$tokenId]);
    $pdo->prepare('UPDATE `approval_users` SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = (SELECT user_id FROM `approval_password_reset_tokens` WHERE id = ? LIMIT 1)')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $tokenId]);
}
