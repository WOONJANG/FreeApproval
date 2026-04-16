<?php
require_once __DIR__ . '/includes/bootstrap.php';
$token = trim((string) ($_GET['token'] ?? ''));
$reset = $token !== '' ? find_valid_password_reset($pdo, $token) : null;
$pageTitle = '새 비밀번호 설정';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
    <div class="auth-card">
        <h1>새 비밀번호 설정</h1>
        <?php if (!$reset): ?>
            <div class="alert alert-error">유효하지 않거나 만료된 재설정 링크입니다.</div>
            <div class="auth-links"><a href="<?= e(base_url('forgot_password.php')) ?>">재설정 다시 요청</a></div>
        <?php else: ?>
            <p><?= e($reset['company_name'] ?: '') ?> / <?= e($reset['name']) ?> 계정의 새 비밀번호를 입력하세요.</p>
            <form action="<?= e(base_url('actions/complete_password_reset.php')) ?>" method="post" class="form-grid">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="form-group full"><label for="password">새 비밀번호</label><input type="password" id="password" name="password" minlength="8" required></div>
                <div class="form-group full"><label for="password_confirm">새 비밀번호 확인</label><input type="password" id="password_confirm" name="password_confirm" minlength="8" required></div>
                <div class="form-group full"><button type="submit" class="btn-primary">비밀번호 변경</button></div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
