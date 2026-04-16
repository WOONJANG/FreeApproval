<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('documents.php?view=my'); }
verify_csrf_or_fail();
$documentId = (int) ($_POST['document_id'] ?? 0);
$reason = trim((string) ($_POST['reason'] ?? ''));
if ($reason === '') { set_flash('error', '기안취소 사유를 입력하세요.'); redirect_to('document_view.php?id=' . $documentId); }
$document = get_document($pdo, $documentId);
if (!$document || !can_cancel_document($pdo, $pageUser, $document)) {
    set_flash('error', '기안취소할 수 없는 문서입니다.');
    redirect_back();
}
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE `approval_documents` SET status = "cancelled", current_step = NULL, cancel_reason = ?, updated_at = NOW() WHERE id = ?')->execute([$reason, $documentId]);
    $pdo->prepare('DELETE FROM `approval_approval_steps` WHERE document_id = ?')->execute([$documentId]);
    log_document_action($pdo, $documentId, (int) $pageUser['id'], 'cancelled', $reason);
    $pdo->commit();
    set_flash('success', '기안취소 처리되었습니다.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    set_flash('error', $e->getMessage());
}
redirect_to('document_view.php?id=' . $documentId);
