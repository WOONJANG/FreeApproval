<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user($pdo)) {
    redirect_to('index.php');
}
$pageTitle = '회사코드 찾기';
$found = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $companyName = trim((string) ($_POST['company_name'] ?? ''));
    $ownerName = trim((string) ($_POST['owner_name'] ?? ''));
    $ownerPhone = normalize_phone((string) ($_POST['owner_phone'] ?? ''));
    if ($companyName !== '' && $ownerName !== '' && $ownerPhone !== '') {
        $found = find_company_by_lookup($pdo, $companyName, $ownerName, $ownerPhone);
        if (!$found) {
            set_flash('error', '일치하는 회사 정보를 찾지 못했습니다. 입력값을 다시 확인하세요.');
        }
    } else {
        set_flash('error', '회사이름, 대표자명, 대표 전화번호를 모두 입력하세요.');
    }
}
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
    <div class="auth-card" style="width:min(100%,680px);">
        <h1>회사코드 찾기</h1>
        <p>회사이름, 대표자명, 대표 전화번호가 모두 일치하면 회사코드를 보여줍니다.</p>
        <form method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="form-group full"><label for="company_name">회사이름</label><input type="text" id="company_name" name="company_name" required maxlength="120"></div>
            <div class="form-group"><label for="owner_name">대표자명</label><input type="text" id="owner_name" name="owner_name" required maxlength="50"></div>
            <div class="form-group"><label for="owner_phone">대표 전화번호</label><input type="tel" id="owner_phone" name="owner_phone" required maxlength="20" placeholder="01012345678"></div>
            <div class="form-group full"><button type="submit" class="btn-primary">회사코드 찾기</button></div>
        </form>
        <?php if ($found): ?>
            <div class="card inner-card">
                <h3 style="margin-top:0;">조회 결과</h3>
                <p><strong><?= e($found['company_name']) ?></strong></p>
                <p>회사코드: <strong><?= e($found['company_code']) ?></strong></p>
                <p class="muted">이 코드로 개인 회원가입을 진행하면 됩니다.</p>
            </div>
        <?php endif; ?>
        <div class="auth-links">
            코드가 확인되면 <a href="<?= e(base_url('register.php')) ?>"><strong>개인 회원가입</strong></a> · <a href="<?= e(base_url('guide.php')) ?>"><strong>사용 가이드</strong></a></div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
