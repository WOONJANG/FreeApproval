<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = current_user($pdo);
if ($pageUser) { log_activity($pdo, $pageUser, isset($pageUser['company_id']) ? (int) $pageUser['company_id'] : null, 'logout', $pageUser['name'] . ' 로그아웃'); }
logout_current_user();
set_flash('success', '로그아웃되었습니다.');
redirect_to('login.php');
