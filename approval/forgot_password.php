<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user($pdo)) {
    redirect_to('index.php');
}
$pageTitle = '비밀번호 재설정';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
    <div class="auth-card">
        <h1>비밀번호 재설정</h1>
        <p>회사코드, 이름, 이메일 또는 전화번호가 일치하면 재설정 링크를 발급합니다.</p>
        <form action="<?= e(base_url('actions/request_password_reset.php')) ?>" method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="form-group full"><label for="company_code">회사코드</label><input type="text" id="company_code" name="company_code" maxlength="6" required></div>
            <div class="form-group"><label for="name">이름</label><input type="text" id="name" name="name" required></div>
            <div class="form-group"><label for="identifier">이메일 또는 전화번호</label><input type="text" id="identifier" name="identifier" required></div>
            <div class="form-group full"><button type="submit" class="btn-primary">재설정 링크 발급</button></div>
        </form>
        <div class="auth-links"><a href="<?= e(base_url('login.php')) ?>">로그인으로 돌아가기</a> · <a href="<?= e(base_url('guide.php')) ?>"><strong>사용 가이드</strong></a></div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
