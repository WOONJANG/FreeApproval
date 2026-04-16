<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user($pdo)) {
    redirect_to('index.php');
}
$pageTitle = '기업가입';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
    <div class="auth-card" style="width:min(100%,680px);">
        <h1>기업가입</h1>
        <p>회사 등록 후 6자리 회사코드가 발급됩니다. 대표자 정보로 회사 관리자 계정도 같이 생성됩니다.</p>
        <form action="<?= e(base_url('actions/register_company.php')) ?>" method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="form-group full"><label for="company_name">회사이름</label><input type="text" id="company_name" name="company_name" required maxlength="120"></div>
            <div class="form-group"><label for="owner_name">대표자명</label><input type="text" id="owner_name" name="owner_name" required maxlength="50"></div>
            <div class="form-group"><label for="owner_phone">대표 전화번호</label><input type="tel" id="owner_phone" name="owner_phone" required maxlength="20" placeholder="01012345678"></div>
            <div class="form-group full"><label for="admin_email">회사 관리자 이메일 (선택)</label><input type="email" id="admin_email" name="admin_email" maxlength="120" placeholder="admin@company.com"></div>
            <div class="form-group"><label for="password">회사 관리자 비밀번호</label><input type="password" id="password" name="password" required minlength="8" maxlength="100"></div>
            <div class="form-group"><label for="password_confirm">비밀번호 확인</label><input type="password" id="password_confirm" name="password_confirm" required minlength="8" maxlength="100"></div>
            <div class="form-group full"><button type="submit" class="btn-primary">기업가입 완료</button></div>
        </form>
        <div class="auth-links">
            이미 회사코드가 있으면 <a href="<?= e(base_url('register.php')) ?>"><strong>개인 회원가입</strong></a> · <a href="<?= e(base_url('guide.php')) ?>"><strong>사용 가이드</strong></a></div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
