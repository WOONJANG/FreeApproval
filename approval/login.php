<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user($pdo)) {
    redirect_to('index.php');
}
$pageTitle = '로그인';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
    <div class="auth-card">
        <h1>로그인</h1>
        <p>개인 회원과 회사 관리자는 회사코드 + 전화번호 또는 이메일 + 비밀번호로 로그인하고, 찐 어드민은 관리자 아이디로 로그인합니다.</p>
        <form action="<?= e(base_url('actions/login.php')) ?>" method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="form-group full">
                <label for="company_code">회사코드</label>
                <input type="text" id="company_code" name="company_code" maxlength="6" placeholder="일반/회사관리자 로그인 시 필수, 최상위 관리자는 비워도 됨">
            </div>
            <div class="form-group full">
                <label for="identifier">전화번호, 이메일 또는 최상위 관리자 아이디</label>
                <input type="text" id="identifier" name="identifier" required maxlength="120" placeholder="01012345678 / user@company.com / admin">
            </div>
            <div class="form-group full">
                <label for="password">비밀번호</label>
                <input type="password" id="password" name="password" required maxlength="100">
            </div>
            <div class="form-group full">
                <button type="submit" class="btn-primary">로그인</button>
            </div>
        </form>
        <div class="auth-links">
            <a href="<?= e(base_url('company_register.php')) ?>"><strong>기업가입</strong></a> ·
            <a href="<?= e(base_url('company_code_lookup.php')) ?>"><strong>회사코드 찾기</strong></a> ·
            <a href="<?= e(base_url('register.php')) ?>"><strong>개인 회원가입</strong></a> ·
            <a href="<?= e(base_url('forgot_password.php')) ?>"><strong>비밀번호 재설정</strong></a> ·
            <a href="<?= e(base_url('guide.php')) ?>"><strong>사용 가이드</strong></a>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
