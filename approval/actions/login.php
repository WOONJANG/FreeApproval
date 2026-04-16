<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('login.php');
}
verify_csrf_or_fail();
$identifier = trim((string) ($_POST['identifier'] ?? ''));
$companyCode = normalize_company_code((string) ($_POST['company_code'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$result = login_by_credentials($pdo, $identifier, $password, $companyCode);
if (!$result['ok']) {
    set_flash('error', $result['message']);
    redirect_to('login.php');
}
log_activity($pdo, $result['user'] ?? null, isset($result['user']['company_id']) ? (int) $result['user']['company_id'] : null, 'login', ($result['user']['name'] ?? '사용자') . ' 로그인');
set_flash('success', '로그인되었습니다.');
redirect_to('index.php');
