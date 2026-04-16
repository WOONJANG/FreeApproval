<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('documents.php?view=waiting'); }
verify_csrf_or_fail();
$documentId = (int) ($_POST['document_id'] ?? 0);
$comment = trim((string) ($_POST['comment'] ?? ''));
$document = get_document($pdo, $documentId);
if (!$document || !can_approve_document($pdo, $pageUser, $document)) {
    set_flash('error', '승인할 수 없는 문서입니다.');
    redirect_back();
}
try {
    $pdo->beginTransaction();
    $step = find_pending_step_for_user($pdo, $documentId, (int) $pageUser['id']);
    if (!$step) { throw new RuntimeException('현재 차례의 문서가 아닙니다.'); }
    $pdo->prepare('UPDATE `approval_approval_steps` SET status = "approved", acted_by_user_id = ?, acted_at = NOW(), comment = ? WHERE id = ?')->execute([(int) $pageUser['id'], $comment, (int) $step['id']]);
    advance_document_approval_flow($pdo, $documentId, true);
    log_document_action($pdo, $documentId, (int) $pageUser['id'], 'approved', $comment !== '' ? $comment : '승인');
    $pdo->commit();
    log_activity($pdo, $pageUser, (int) ($document['company_id'] ?? ($pageUser['company_id'] ?? 0)), 'approved', '[' . ($document['doc_no'] ?: '-') . '] ' . ($document['title'] ?? '문서') , 'document', $documentId);
set_flash('success', '승인 처리되었습니다.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    set_flash('error', $e->getMessage());
}
redirect_to('document_view.php?id=' . $documentId);
