<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageUser = require_notice_manager($pdo);
$noticeId = (int) ($_GET['id'] ?? 0);
$notice = [
    'id' => 0,
    'title' => '',
    'content' => '',
    'is_notice' => 0,
    'is_global' => is_super_admin($pageUser) ? 1 : 0,
    'is_popup' => 0,
    'is_banner' => 0,
    'active_from' => null,
    'active_until' => null,
    'company_id' => $pageUser['company_id'] ?? null,
];
if ($noticeId > 0) {
    $existing = get_notice_by_id($pdo, $noticeId);
    if (!$existing || !can_manage_notice($pageUser, $existing)) {
        http_response_code(403);
        exit('이 공지사항을 수정할 수 없습니다.');
    }
    $notice = $existing;
}
$pageTitle = $noticeId > 0 ? '공지사항 수정' : '공지사항 작성';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <form action="<?= e(base_url('actions/save_notice.php')) ?>" method="post" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="notice_id" value="<?= e((string) $notice['id']) ?>">
        <div class="form-group full">
            <label>구분</label>
            <?php if (is_super_admin($pageUser) && ((int) $notice['id'] === 0 || (int) $notice['is_global'] === 1)): ?>
                <input type="text" value="프로젝트 전체 공지" readonly>
                <input type="hidden" name="is_notice" value="1">
            <?php else: ?>
                <label class="checkbox-inline"><input type="checkbox" name="is_notice" value="1" <?= (int) $notice['is_notice'] ? 'checked' : '' ?>> 상단 고정 공지</label>
            <?php endif; ?>
            <label class="checkbox-inline"><input type="checkbox" name="is_banner" value="1" <?= (int) ($notice['is_banner'] ?? 0) ? 'checked' : '' ?>> 상단 배너로도 노출</label>
            <label class="checkbox-inline"><input type="checkbox" name="is_popup" value="1" <?= (int) ($notice['is_popup'] ?? 0) ? 'checked' : '' ?>> 팝업으로 노출</label>
            <div class="muted">배너/팝업은 활성 기간 내에서만 표시됩니다.</div>
        </div>
        <div class="form-group"><label for="active_from">노출 시작</label><input type="datetime-local" id="active_from" name="active_from" value="<?= !empty($notice['active_from']) ? e(date('Y-m-d\TH:i', strtotime($notice['active_from']))) : '' ?>"></div>
        <div class="form-group"><label for="active_until">노출 종료</label><input type="datetime-local" id="active_until" name="active_until" value="<?= !empty($notice['active_until']) ? e(date('Y-m-d\TH:i', strtotime($notice['active_until']))) : '' ?>"></div>
        <div class="form-group full">
            <label for="title">제목</label>
            <input type="text" id="title" name="title" required maxlength="150" value="<?= e($notice['title']) ?>">
        </div>
        <div class="form-group full">
            <label for="content">내용</label>
            <textarea id="content" name="content" required><?= e($notice['content']) ?></textarea>
        </div>
        <div class="form-group full">
            <div class="btn-row">
                <button type="submit" class="btn-primary"><?= $noticeId > 0 ? '수정 저장' : '등록하기' ?></button>
                <a class="btn-secondary" href="<?= e(base_url('notices.php')) ?>">목록</a>
            </div>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
