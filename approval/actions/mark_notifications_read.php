<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('notifications.php');
}
verify_csrf_or_fail();
mark_all_notifications_read($pdo, (int) $pageUser['id']);
set_flash('success', '알림을 모두 읽음 처리했습니다.');
redirect_to('notifications.php');
