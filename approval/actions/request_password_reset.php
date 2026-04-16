<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('forgot_password.php'); }
verify_csrf_or_fail();
$companyCode = normalize_company_code((string) ($_POST['company_code'] ?? ''));
$name = trim((string) ($_POST['name'] ?? ''));
$identifier = trim((string) ($_POST['identifier'] ?? ''));
$company = find_company_by_code($pdo, $companyCode);
if (!$company) { set_flash('error', '회사코드를 확인하세요.'); redirect_to('forgot_password.php'); }
$phone = normalize_phone($identifier);
$email = normalize_email($identifier);
$stmt = $pdo->prepare('SELECT * FROM `approval_users` WHERE company_id = ? AND name = ? AND (phone = ? OR email = ?) LIMIT 1');
$stmt->execute([(int) $company['id'], $name, $phone, $email]);
$user = $stmt->fetch();
if (!$user) { set_flash('error', '일치하는 계정을 찾지 못했습니다.'); redirect_to('forgot_password.php'); }
$token = create_password_reset_token($pdo, (int) $user['id']);
log_activity($pdo, $user, (int) $company['id'], 'password_reset_requested', $user['name'] . ' 계정 비밀번호 재설정 요청', 'user', (int) $user['id']);
set_flash('success', '재설정 링크: ' . base_url('reset_password.php?token=' . urlencode($token)));
redirect_to('forgot_password.php');
