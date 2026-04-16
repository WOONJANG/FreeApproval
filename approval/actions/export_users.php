<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_member_manager($pdo);
$companyId = max(0, (int) ($_GET['company_id'] ?? 0));
$params = [];
$where = 'WHERE u.role <> "super_admin"';
if (is_super_admin($pageUser) && $companyId > 0) {
    $where .= ' AND u.company_id = ?';
    $params[] = $companyId;
} elseif (!is_super_admin($pageUser)) {
    $where .= ' AND u.company_id = ?';
    $params[] = (int) $pageUser['company_id'];
}
$stmt = $pdo->prepare('SELECT u.*, c.company_name, c.company_code FROM `approval_users` u LEFT JOIN `approval_companies` c ON c.id = u.company_id ' . $where . ' ORDER BY c.company_name ASC, u.level_no ASC, u.id ASC');
$stmt->execute($params);
$rows = [];
foreach ($stmt->fetchAll() as $member) {
    $rows[] = [
        $member['company_name'] ?: '-',
        $member['company_code'] ?: '-',
        $member['name'],
        $member['email'] ?: '-',
        $member['phone'] ?: '-',
        'Level ' . (string) $member['level_no'],
        $member['job_title'] ?: '-',
        $member['role'],
        (int) $member['can_manage_members'] === 1 ? 'Y' : 'N',
        (int) $member['can_manage_notices'] === 1 ? 'Y' : 'N',
        (int) $member['is_active'] === 1 ? '사용중' : '비활성',
        format_datetime($member['created_at']),
    ];
}
export_csv('approval-users-' . date('Ymd-His') . '.csv', ['회사','회사코드','이름','이메일','전화번호','레벨','직급명','권한','회원관리','공지관리','상태','가입일'], $rows);
