<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('register.php');
}
verify_csrf_or_fail();
$companyCode = normalize_company_code((string) ($_POST['company_code'] ?? ''));
$name = trim((string) ($_POST['name'] ?? ''));
$email = normalize_email((string) ($_POST['email'] ?? ''));
$phone = normalize_phone((string) ($_POST['phone'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

if ($companyCode === '' || $name === '' || $email === '' || $phone === '' || $password === '') {
    set_flash('error', '필수 항목을 모두 입력하세요.');
    redirect_to('register.php');
}
if (!preg_match('/^[A-Z0-9]{6}$/', $companyCode)) {
    set_flash('error', '회사코드는 영문 대문자/숫자 6자리여야 합니다.');
    redirect_to('register.php');
}
$company = find_company_by_code($pdo, $companyCode);
if (!$company || !(int) $company['is_active']) {
    set_flash('error', '유효한 회사코드를 찾지 못했습니다.');
    redirect_to('register.php');
}
if (!company_has_capacity($pdo, (int) $company['id'], 'member')) {
    set_flash('error', '현재 회사 플랜의 가입자 수 한도를 초과했습니다.');
    redirect_to('register.php');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash('error', '이메일 형식이 올바르지 않습니다.');
    redirect_to('register.php');
}
if (!preg_match('/^\d{10,11}$/', $phone)) {
    set_flash('error', '전화번호는 숫자 10~11자리로 입력하세요.');
    redirect_to('register.php');
}
if (!ensure_phone_available($pdo, $phone)) {
    set_flash('error', '이미 가입된 전화번호입니다.');
    redirect_to('register.php');
}
if (!ensure_email_available($pdo, $email)) {
    set_flash('error', '이미 가입된 이메일입니다.');
    redirect_to('register.php');
}
if (mb_strlen($password) < 8) {
    set_flash('error', '비밀번호는 8자 이상이어야 합니다.');
    redirect_to('register.php');
}
if ($password !== $passwordConfirm) {
    set_flash('error', '비밀번호 확인이 일치하지 않습니다.');
    redirect_to('register.php');
}
$stmt = $pdo->prepare('INSERT INTO `approval_users` (company_id, login_id, email, phone, password_hash, name, level_no, job_title, role, can_manage_members, can_manage_notices, is_active, failed_login_attempts, locked_until, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?, 1, NULL, "user", 0, 0, 1, 0, NULL, NOW(), NOW())');
$stmt->execute([(int) $company['id'], $email, $phone, password_hash($password, PASSWORD_DEFAULT), $name]);
$userId = (int) $pdo->lastInsertId();
log_activity($pdo, ['id'=>$userId,'name'=>$name], (int) $company['id'], 'user_registered', $name . ' 회원가입', 'user', $userId);
set_flash('success', '회원가입이 완료되었습니다. 회사코드와 전화번호(또는 이메일), 비밀번호로 로그인하세요.');
redirect_to('login.php');
