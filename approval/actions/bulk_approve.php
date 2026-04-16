<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('documents.php?view=waiting'); }
verify_csrf_or_fail();
if ((int) ($pageUser['allow_bulk_approval'] ?? 0) !== 1) {
    set_flash('error', '회사 설정에서 일괄 승인이 허용되지 않았습니다.');
    redirect_to('documents.php?view=waiting');
}
$documentIds = $_POST['document_ids'] ?? [];
if (!is_array($documentIds) || !$documentIds) {
    set_flash('error', '승인할 문서를 선택하세요.');
    redirect_to('documents.php?view=waiting');
}
$approved = 0;
foreach ($documentIds as $documentId) {
    $documentId = (int) $documentId;
    $document = get_document($pdo, $documentId);
    if (!$document || !can_approve_document($pdo, $pageUser, $document)) {
        continue;
    }
    try {
        $pdo->beginTransaction();
        $step = find_pending_step_for_user($pdo, $documentId, (int) $pageUser['id']);
        if (!$step) {
            throw new RuntimeException('현재 차례 문서가 아닙니다.');
        }
        $pdo->prepare('UPDATE `approval_approval_steps` SET status = "approved", acted_by_user_id = ?, acted_at = NOW(), comment = ? WHERE id = ?')->execute([(int) $pageUser['id'], '일괄 승인', (int) $step['id']]);
        advance_document_approval_flow($pdo, $documentId, true);
        log_document_action($pdo, $documentId, (int) $pageUser['id'], 'approved', '일괄 승인');
        $pdo->commit();
        $approved++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
    }
}
set_flash('success', $approved . '건을 일괄 승인했습니다.');
redirect_to('documents.php?view=waiting');
