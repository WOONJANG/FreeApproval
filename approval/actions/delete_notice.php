<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_notice_manager($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('notices.php');
}
verify_csrf_or_fail();
$noticeId = (int) ($_POST['notice_id'] ?? 0);
$notice = get_notice_by_id($pdo, $noticeId);
if (!$notice || !can_manage_notice($pageUser, $notice)) {
    set_flash('error', '삭제할 수 없는 공지사항입니다.');
    redirect_to('notices.php');
}
$stmt = $pdo->prepare('DELETE FROM `approval_notices` WHERE id = ?');
$stmt->execute([$noticeId]);
set_flash('success', '공지사항이 삭제되었습니다.');
redirect_to('notices.php');
