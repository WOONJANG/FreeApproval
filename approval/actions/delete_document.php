<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_admin($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('documents.php?view=all'); }
verify_csrf_or_fail();
$documentId = (int) ($_POST['document_id'] ?? 0);
$reason = trim((string) ($_POST['reason'] ?? ''));
if ($reason === '') { set_flash('error', '삭제 사유를 입력하세요.'); redirect_to('document_view.php?id=' . $documentId); }
$document = get_document($pdo, $documentId);
if (!$document || !can_delete_document($pageUser, $document)) {
    set_flash('error', '문서를 휴지통으로 이동할 권한이 없습니다.');
    redirect_to('documents.php?view=all');
}
$pdo->beginTransaction();
try {
    $prev = $document['status'] === 'deleted' ? ($document['status_before_delete'] ?: 'draft') : $document['status'];
    $pdo->prepare('UPDATE `approval_documents` SET status_before_delete = ?, status = "deleted", current_step = NULL, deleted_at = NOW(), deleted_by = ?, delete_reason = ?, updated_at = NOW() WHERE id = ?')->execute([$prev, (int) $pageUser['id'], $reason, $documentId]);
    log_document_action($pdo, $documentId, (int) $pageUser['id'], 'deleted', $reason);
    notify_writer($pdo, $documentId, 'deleted', '문서가 휴지통으로 이동되었습니다', '[' . ($document['doc_no'] ?: '-') . '] ' . $document['title'] . ' 문서가 휴지통으로 이동되었습니다. 사유: ' . $reason);
    $pdo->commit();
    log_activity($pdo, $pageUser, (int) ($document['company_id'] ?? ($pageUser['company_id'] ?? 0)), 'deleted', '[' . ($document['doc_no'] ?: '-') . '] ' . ($document['title'] ?? '문서') , 'document', $documentId);
set_flash('success', '문서를 휴지통으로 이동했습니다.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    set_flash('error', $e->getMessage());
}
redirect_to('document_view.php?id=' . $documentId);
