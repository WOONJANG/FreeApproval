<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageUser = require_login($pdo);
$documentId = (int) ($_GET['id'] ?? 0);
$document = get_document($pdo, $documentId);
if (!$document || !user_can_view_document($pdo, $pageUser, $document)) {
    http_response_code(404); exit('문서를 찾을 수 없습니다.');
}
$stmt = $pdo->prepare('SELECT * FROM `approval_revisions` WHERE document_id = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$documentId]);
$revision = $stmt->fetch();
$pageTitle = '재기안 비교 보기';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="section-head"><h3>반려 전/후 비교</h3><a href="<?= e(base_url('document_view.php?id=' . $documentId)) ?>" class="btn btn-sm btn-outline">문서 상세</a></div>
    <?php if (!$revision): ?>
        <div class="empty-state">비교할 이전 버전이 없습니다.</div>
    <?php else: ?>
        <div class="grid-2 compare-grid">
            <div class="card inner-card">
                <h4 style="margin-top:0;">이전 버전 v<?= e((string) $revision['version_no']) ?></h4>
                <p><strong>제목</strong><br><?= e($revision['title_snapshot']) ?></p>
                <div class="box-content"><?= nl2br(e($revision['content_snapshot'])) ?></div>
            </div>
            <div class="card inner-card">
                <h4 style="margin-top:0;">현재 버전 v<?= e((string) $document['version_no']) ?></h4>
                <p><strong>제목</strong><br><?= e($document['title']) ?></p>
                <div class="box-content"><?= nl2br(e($document['content'])) ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
