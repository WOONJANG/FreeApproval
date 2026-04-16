<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('documents.php?view=all'); }
verify_csrf_or_fail();
$documentId = (int) ($_POST['document_id'] ?? 0);
$document = get_document($pdo, $documentId);
if (!$document || !user_can_view_document($pdo, $pageUser, $document)) {
    set_flash('error', '문서를 찾을 수 없습니다.');
    redirect_to('documents.php?view=all');
}
$stmt = $pdo->prepare('SELECT s.approver_user_id, u.name, d.doc_no, d.title FROM `approval_approval_steps` s INNER JOIN `approval_users` u ON u.id = s.approver_user_id INNER JOIN `approval_documents` d ON d.id = s.document_id WHERE s.document_id = ? AND s.status = "pending" LIMIT 1');
$stmt->execute([$documentId]);
$row = $stmt->fetch();
if (!$row) {
    set_flash('error', '현재 독촉할 결재자가 없습니다.');
    redirect_to('document_view.php?id=' . $documentId);
}
create_notification($pdo, (int) $row['approver_user_id'], 'approval_reminder', '결재 요청이 다시 도착했습니다', '[' . $row['doc_no'] . '] ' . $row['title'] . ' 문서 결재를 요청합니다.', base_url('document_view.php?id=' . $documentId));
log_activity($pdo, $pageUser, (int) $document['company_id'], 'approval_reminder', '[' . ($document['doc_no'] ?: '-') . '] ' . $row['name'] . '님께 결재 독촉', 'document', $documentId);
set_flash('success', '현재 결재자에게 독촉 알림을 보냈습니다.');
redirect_to('document_view.php?id=' . $documentId);
