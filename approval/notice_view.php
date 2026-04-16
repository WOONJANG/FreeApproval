<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageUser = require_login($pdo);
$noticeId = (int) ($_GET['id'] ?? 0);
$notice = get_notice_by_id($pdo, $noticeId);
if (!$notice || !can_view_notice($pageUser, $notice)) {
    http_response_code(404);
    exit('공지사항을 찾을 수 없거나 볼 수 없습니다.');
}
increment_notice_view_count($pdo, $noticeId);
$notice = get_notice_by_id($pdo, $noticeId);
$pageTitle = '공지사항 상세';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="section-head">
        <div>
            <div class="btn-row">
                <span class="<?= e((int) $notice['is_global'] ? 'badge badge-blue' : ((int) $notice['is_notice'] ? 'badge badge-amber' : 'badge badge-gray')) ?>"><?= e(notice_type_label($notice)) ?></span>
                <?php if (!empty($notice['company_name'])): ?><span class="badge badge-gray"><?= e($notice['company_name']) ?> · <?= e($notice['company_code']) ?></span><?php endif; ?>
            </div>
            <h2 style="margin:12px 0 0;"><?= e($notice['title']) ?></h2>
        </div>
        <div class="btn-row">
            <?php if (can_manage_notices($pageUser) && can_manage_notice($pageUser, $notice)): ?>
                <a class="btn btn-sm btn-outline" href="<?= e(base_url('notice_form.php?id=' . $notice['id'])) ?>">수정</a>
                <form action="<?= e(base_url('actions/delete_notice.php')) ?>" method="post" onsubmit="return confirm('공지사항을 삭제하시겠습니까?');">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="notice_id" value="<?= e((string) $notice['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-danger">삭제</button>
                </form>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline" href="<?= e(base_url('notices.php')) ?>">목록</a>
        </div>
    </div>

    <div class="doc-meta" style="margin-top:14px;">
        <span>노출형식: <?= e((int) ($notice['is_banner'] ?? 0) === 1 ? '배너 ' : '') ?><?= e((int) ($notice['is_popup'] ?? 0) === 1 ? '팝업' : '') ?></span>
        <span>작성자: <?= e($notice['writer_name']) ?> / <?= e($notice['writer_job_title'] ?: ((int) $notice['is_global'] ? '프로젝트 관리자' : '직급 미설정')) ?></span>
        <span>등록일: <?= e(format_datetime($notice['created_at'])) ?></span>
        <span>조회수: <?= e((string) $notice['view_count']) ?></span>
    </div>

    <div class="card inner-card">
        <div class="box-content"><?= nl2br(e($notice['content'])) ?></div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
