<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('company_register.php');
}
verify_csrf_or_fail();
$companyName = trim((string) ($_POST['company_name'] ?? ''));
$ownerName = trim((string) ($_POST['owner_name'] ?? ''));
$ownerPhone = normalize_phone((string) ($_POST['owner_phone'] ?? ''));
$adminEmail = normalize_email((string) ($_POST['admin_email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

if ($companyName === '' || $ownerName === '' || $ownerPhone === '' || $password === '') {
    set_flash('error', '필수 항목을 모두 입력하세요.');
    redirect_to('company_register.php');
}
if (!preg_match('/^\d{10,11}$/', $ownerPhone)) {
    set_flash('error', '대표 전화번호는 숫자 10~11자리로 입력하세요.');
    redirect_to('company_register.php');
}
if ($adminEmail !== '' && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
    set_flash('error', '이메일 형식이 올바르지 않습니다.');
    redirect_to('company_register.php');
}
if (!ensure_phone_available($pdo, $ownerPhone)) {
    set_flash('error', '이미 사용 중인 대표 전화번호입니다.');
    redirect_to('company_register.php');
}
if (!ensure_email_available($pdo, $adminEmail)) {
    set_flash('error', '이미 사용 중인 이메일입니다.');
    redirect_to('company_register.php');
}
if (mb_strlen($password) < 8) {
    set_flash('error', '비밀번호는 8자 이상이어야 합니다.');
    redirect_to('company_register.php');
}
if ($password !== $passwordConfirm) {
    set_flash('error', '비밀번호 확인이 일치하지 않습니다.');
    redirect_to('company_register.php');
}

try {
    $pdo->beginTransaction();
    $companyCode = generate_company_code($pdo);
    $stmt = $pdo->prepare('INSERT INTO `approval_companies` (company_code, company_name, owner_name, owner_phone, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())');
    $stmt->execute([$companyCode, $companyName, $ownerName, $ownerPhone]);
    $companyId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('INSERT INTO `approval_users` (company_id, login_id, email, phone, password_hash, name, level_no, job_title, role, can_manage_members, can_manage_notices, is_active, failed_login_attempts, locked_until, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?, 99, ?, "admin", 1, 1, 1, 0, NULL, NOW(), NOW())');
    $stmt->execute([$companyId, $adminEmail !== '' ? $adminEmail : null, $ownerPhone, password_hash($password, PASSWORD_DEFAULT), $ownerName, '회사 관리자']);

    log_activity($pdo, ['id' => (int) $pdo->lastInsertId(), 'name' => $ownerName], $companyId, 'company_registered', $companyName . ' 기업가입', 'company', $companyId);
    $pdo->commit();
    set_flash('success', '기업가입이 완료되었습니다. 회사코드는 ' . $companyCode . ' 입니다. 회사코드와 대표 전화번호(또는 관리자 이메일), 비밀번호로 로그인하세요.');
    redirect_to('login.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
    redirect_to('company_register.php');
}
