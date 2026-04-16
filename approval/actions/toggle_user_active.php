<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_member_manager($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('admin/users.php'); }
verify_csrf_or_fail();
$userId = (int) ($_POST['user_id'] ?? 0);
$toggleTo = (int) ($_POST['toggle_to'] ?? 0) === 1 ? 1 : 0;
$stmt = $pdo->prepare('SELECT * FROM `approval_users` WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$member = $stmt->fetch();
if (!$member || !can_manage_member($pageUser, $member)) {
    set_flash('error', '변경할 수 없는 회원입니다.');
    redirect_to('admin/users.php');
}
if ($userId === (int) $pageUser['id'] && $toggleTo === 0) {
    set_flash('error', '현재 로그인 중인 계정은 비활성화할 수 없습니다.');
    redirect_to('admin/users.php');
}
if (($member['role'] ?? 'user') === 'admin' && $toggleTo === 0 && is_last_company_admin($pdo, (int) $member['company_id'], $userId)) {
    set_flash('error', '마지막 회사 대표 계정은 비활성화할 수 없습니다.');
    redirect_to('admin/users.php');
}
$pdo->prepare('UPDATE `approval_users` SET is_active = ?, failed_login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = ?')->execute([$toggleTo, $userId]);
log_activity($pdo, $pageUser, (int) ($member['company_id'] ?? ($pageUser['company_id'] ?? 0)), 'user_toggle_active', $member['name'] . ' 계정 ' . ($toggleTo === 1 ? '활성화' : '비활성화'), 'user', $userId);
set_flash('success', $toggleTo === 1 ? '계정이 활성화되었습니다.' : '계정이 비활성화되었습니다.');
redirect_to('admin/users.php');
