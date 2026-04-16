<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_company_settings_manager($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('admin/company_settings.php'); }
verify_csrf_or_fail();
$categoryId = max(0, (int) ($_POST['category_id'] ?? 0));
$stmt = $pdo->prepare('SELECT * FROM `approval_document_categories` WHERE id = ? LIMIT 1');
$stmt->execute([$categoryId]);
$category = $stmt->fetch();
if (!$category || !same_company($pageUser, (int) $category['company_id'])) { set_flash('error', '분류를 찾을 수 없습니다.'); redirect_to('admin/company_settings.php'); }
$companyId = (int) $category['company_id'];
$docCountStmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_documents` WHERE category_id = ?');
$docCountStmt->execute([$categoryId]);
$templateCountStmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_approval_templates` WHERE category_id = ?');
$templateCountStmt->execute([$categoryId]);
$docCount = (int) $docCountStmt->fetchColumn();
$templateCount = (int) $templateCountStmt->fetchColumn();
if ($docCount > 0 || $templateCount > 0) {
    set_flash('error', '사용 중인 문서 분류는 삭제할 수 없습니다. 먼저 다른 분류로 변경하거나 비활성화하세요.');
    redirect_to('admin/company_settings.php?company_id=' . $companyId);
}
$pdo->prepare('DELETE FROM `approval_document_categories` WHERE id = ?')->execute([$categoryId]);
log_activity($pdo, $pageUser, $companyId, 'category_deleted', $category['name'] . ' 분류 삭제', 'category', $categoryId);
set_flash('success', '문서 분류가 삭제되었습니다.');
redirect_to('admin/company_settings.php?company_id=' . $companyId);
