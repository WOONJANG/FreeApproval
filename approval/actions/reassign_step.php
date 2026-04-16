<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_admin($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('documents.php?view=all'); }
verify_csrf_or_fail();
$documentId = (int) ($_POST['document_id'] ?? 0);
$stepId = (int) ($_POST['step_id'] ?? 0);
$newApproverUserId = (int) ($_POST['new_approver_user_id'] ?? 0);
if ($newApproverUserId <= 0) { set_flash('error', '새 결재자를 선택하세요.'); redirect_to('document_view.php?id=' . $documentId); }
$document = get_document($pdo, $documentId);
if (!$document || !can_reassign_step($pageUser, $document)) { set_flash('error', '결재자를 재지정할 수 없습니다.'); redirect_back(); }
$stepStmt = $pdo->prepare('SELECT * FROM `approval_approval_steps` WHERE id = ? AND document_id = ? LIMIT 1');
$stepStmt->execute([$stepId, $documentId]);
$step = $stepStmt->fetch();
if (!$step || !in_array($step['status'], ['waiting', 'pending'], true)) { set_flash('error', '대기 중인 결재 단계만 재지정할 수 있습니다.'); redirect_to('document_view.php?id=' . $documentId); }
$userStmt = $pdo->prepare('SELECT * FROM `approval_users` WHERE id = ? AND is_active = 1 LIMIT 1');
$userStmt->execute([$newApproverUserId]);
$newApprover = $userStmt->fetch();
if (!$newApprover || (int) $newApprover['company_id'] !== (int) $document['company_id']) { set_flash('error', '같은 회사의 활성 사용자만 지정할 수 있습니다.'); redirect_to('document_view.php?id=' . $documentId); }
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE `approval_approval_steps` SET approver_user_id = ?, required_level_no = ? WHERE id = ?')->execute([$newApproverUserId, (int) $newApprover['level_no'], $stepId]);
    log_document_action($pdo, $documentId, (int) $pageUser['id'], 'reassigned', $step['step_no'] . '차 결재자를 ' . $newApprover['name'] . '으로 변경');
    if ($step['status'] === 'pending') {
        advance_document_approval_flow($pdo, $documentId, true);
    }
    $pdo->commit();
    log_activity($pdo, $pageUser, (int) ($document['company_id'] ?? ($pageUser['company_id'] ?? 0)), 'reassigned', '[' . ($document['doc_no'] ?: '-') . '] ' . ($document['title'] ?? '문서') , 'document', $documentId);
set_flash('success', '결재자를 변경했습니다.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    set_flash('error', $e->getMessage());
}
redirect_to('document_view.php?id=' . $documentId);
