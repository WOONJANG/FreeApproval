<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('forgot_password.php'); }
verify_csrf_or_fail();
$token = trim((string) ($_POST['token'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
$reset = $token !== '' ? find_valid_password_reset($pdo, $token) : null;
if (!$reset) { set_flash('error', '유효하지 않거나 만료된 링크입니다.'); redirect_to('forgot_password.php'); }
if (mb_strlen($password) < 8) { set_flash('error', '비밀번호는 8자 이상이어야 합니다.'); redirect_to('reset_password.php?token=' . urlencode($token)); }
if ($password !== $passwordConfirm) { set_flash('error', '비밀번호 확인이 일치하지 않습니다.'); redirect_to('reset_password.php?token=' . urlencode($token)); }
use_password_reset_token($pdo, (int) $reset['id'], $password);
log_activity($pdo, ['id' => $reset['user_id'], 'name' => $reset['name']], (int) ($reset['company_id'] ?? 0), 'password_reset_completed', $reset['name'] . ' 계정 비밀번호 재설정 완료', 'user', (int) $reset['user_id']);
set_flash('success', '비밀번호가 변경되었습니다. 새 비밀번호로 로그인하세요.');
redirect_to('login.php');
