<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_admin($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('documents.php?view=trash'); }
verify_csrf_or_fail();
$documentId = (int) ($_POST['document_id'] ?? 0);
$document = get_document($pdo, $documentId);
if (!$document || !can_restore_document($pageUser, $document)) {
    set_flash('error', '복구할 수 없는 문서입니다.');
    redirect_back('documents.php?view=trash');
}
$restoreStatus = $document['status_before_delete'] ?: 'draft';
$pdo->beginTransaction();
try {
    $pendingStepNo = null;
    if ($restoreStatus === 'submitted') {
        $pendingStmt = $pdo->prepare('SELECT step_no FROM `approval_approval_steps` WHERE document_id = ? AND status = "pending" ORDER BY step_no ASC LIMIT 1');
        $pendingStmt->execute([$documentId]);
        $pendingStepNo = $pendingStmt->fetchColumn();
    }
    $pdo->prepare('UPDATE `approval_documents` SET status = ?, current_step = ?, deleted_at = NULL, deleted_by = NULL, delete_reason = NULL, updated_at = NOW() WHERE id = ?')->execute([$restoreStatus, $pendingStepNo !== false ? $pendingStepNo : null, $documentId]);
    log_document_action($pdo, $documentId, (int) $pageUser['id'], 'restored', '휴지통 복구');
    $pdo->commit();
    log_activity($pdo, $pageUser, (int) ($document['company_id'] ?? ($pageUser['company_id'] ?? 0)), 'restored', '[' . ($document['doc_no'] ?: '-') . '] ' . ($document['title'] ?? '문서') , 'document', $documentId);
set_flash('success', '문서를 복구했습니다.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    set_flash('error', $e->getMessage());
}
redirect_to('document_view.php?id=' . $documentId);
