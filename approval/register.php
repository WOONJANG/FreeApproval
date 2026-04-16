<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user($pdo)) {
    redirect_to('index.php');
}
$pageTitle = '개인 회원가입';
$prefillCompanyCode = normalize_company_code((string) ($_GET['company_code'] ?? ''));
$companyPreview = $prefillCompanyCode !== '' ? get_company_name_for_code($pdo, $prefillCompanyCode) : null;
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
    <div class="auth-card" style="width:min(100%,680px);">
        <h1>개인 회원가입</h1>
        <p>회사코드를 확인한 뒤 이름, 이메일, 전화번호, 비밀번호로 가입합니다. 레벨과 직급명은 회사 관리자만 설정합니다.</p>
        <form action="<?= e(base_url('actions/register.php')) ?>" method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="form-group full">
                <label for="company_code">회사코드</label>
                <input type="text" id="company_code" name="company_code" required maxlength="6" value="<?= e($prefillCompanyCode) ?>" placeholder="예: A1B2C3">
                <?php if ($companyPreview): ?><div class="muted">확인된 회사: <?= e($companyPreview) ?></div><?php endif; ?>
            </div>
            <div class="form-group"><label for="name">이름</label><input type="text" id="name" name="name" required maxlength="50"></div>
            <div class="form-group"><label for="email">이메일</label><input type="email" id="email" name="email" required maxlength="120"></div>
            <div class="form-group"><label for="phone">전화번호</label><input type="tel" id="phone" name="phone" required maxlength="20" placeholder="01012345678"></div>
            <div class="form-group"><label for="password">비밀번호</label><input type="password" id="password" name="password" required minlength="8" maxlength="100"></div>
            <div class="form-group full"><label for="password_confirm">비밀번호 확인</label><input type="password" id="password_confirm" name="password_confirm" required minlength="8" maxlength="100"></div>
            <div class="form-group full"><button type="submit" class="btn-primary">가입하기</button></div>
        </form>
        <div class="auth-links">
            회사코드를 모르면 <a href="<?= e(base_url('company_code_lookup.php')) ?>"><strong>회사코드 찾기</strong></a>
                    · <a href="<?= e(base_url('guide.php')) ?>"><strong>사용 가이드</strong></a></div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
