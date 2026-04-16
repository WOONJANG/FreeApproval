<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('documents.php?view=waiting'); }
verify_csrf_or_fail();
$documentId = (int) ($_POST['document_id'] ?? 0);
$comment = trim((string) ($_POST['comment'] ?? ''));
if ($comment === '') {
    set_flash('error', '반려 사유를 입력하세요.');
    redirect_to('document_view.php?id=' . $documentId);
}
$document = get_document($pdo, $documentId);
if (!$document || !can_approve_document($pdo, $pageUser, $document)) {
    set_flash('error', '반려할 수 없는 문서입니다.');
    redirect_back();
}
try {
    $pdo->beginTransaction();
    $step = find_pending_step_for_user($pdo, $documentId, (int) $pageUser['id']);
    if (!$step) { throw new RuntimeException('현재 차례의 문서가 아닙니다.'); }
    $pdo->prepare('UPDATE `approval_approval_steps` SET status = "rejected", acted_by_user_id = ?, acted_at = NOW(), comment = ? WHERE id = ?')->execute([(int) $pageUser['id'], $comment, (int) $step['id']]);
    $pdo->prepare('UPDATE `approval_documents` SET status = "rejected", current_step = NULL, updated_at = NOW() WHERE id = ?')->execute([$documentId]);
    log_document_action($pdo, $documentId, (int) $pageUser['id'], 'rejected', $comment);
    $doc = get_document($pdo, $documentId);
    notify_writer($pdo, $documentId, 'rejected', '문서가 반려되었습니다', '[' . ($doc['doc_no'] ?: '-') . '] ' . $doc['title'] . ' 문서가 반려되었습니다. 사유: ' . $comment);
    $pdo->commit();
    log_activity($pdo, $pageUser, (int) ($document['company_id'] ?? ($pageUser['company_id'] ?? 0)), 'rejected', '[' . ($document['doc_no'] ?: '-') . '] ' . ($document['title'] ?? '문서') , 'document', $documentId);
set_flash('success', '반려 처리되었습니다.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    set_flash('error', $e->getMessage());
}
redirect_to('document_view.php?id=' . $documentId);
