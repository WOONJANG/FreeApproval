<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_company_settings_manager($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('admin/company_settings.php'); }
verify_csrf_or_fail();
$companyId = max(0, (int) ($_POST['company_id'] ?? 0));
$company = get_company_settings($pdo, $pageUser, $companyId);
if (!$company) { set_flash('error', '회사를 찾을 수 없습니다.'); redirect_to('admin/company_settings.php'); }
$templateId = max(0, (int) ($_POST['template_id'] ?? 0));
$template = $templateId > 0 ? get_template_by_id($pdo, $templateId) : null;
if ($templateId > 0 && (!$template || (int) $template['company_id'] !== $companyId)) {
    set_flash('error', '템플릿을 찾을 수 없습니다.');
    redirect_to('admin/company_settings.php?company_id=' . $companyId);
}
$name = trim((string) ($_POST['name'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$categoryId = max(0, (int) ($_POST['category_id'] ?? 0));
$approverIds = $_POST['approver_ids'] ?? [];
$readerIds = $_POST['reader_ids'] ?? [];
if (!is_array($approverIds)) { $approverIds = []; }
if (!is_array($readerIds)) { $readerIds = []; }
$titleTemplate = trim((string) ($_POST['title_template'] ?? ''));
$contentTemplate = trim((string) ($_POST['content_template'] ?? ''));
$isActive = (int) (($_POST['is_active'] ?? '0') === '1');
$validApproverIds = array_map(static fn(array $row): int => (int) $row['id'], get_assignable_approvers($pdo, 0, $companyId));
$validReaderIds = array_map(static fn(array $row): int => (int) $row['id'], get_company_members_for_reader($pdo, $companyId));
$selected = [];
foreach ($approverIds as $id) {
    $id = (int) $id;
    if (in_array($id, $validApproverIds, true)) {
        $selected[] = $id;
    }
}
$selectedReaders = [];
foreach ($readerIds as $id) {
    $id = (int) $id;
    if (in_array($id, $validReaderIds, true)) {
        $selectedReaders[] = $id;
    }
}
if ($categoryId > 0) {
    $validCategoryIds = array_map(static fn(array $row): int => (int) $row['id'], get_company_categories($pdo, $companyId));
    if (!in_array($categoryId, $validCategoryIds, true)) {
        set_flash('error', '기본 문서분류가 유효하지 않습니다.');
        redirect_to('admin/company_settings.php?company_id=' . $companyId);
    }
}
if ($name === '' || !$selected) { set_flash('error', '템플릿명과 결재자를 지정하세요.'); redirect_to('admin/company_settings.php?company_id=' . $companyId); }
$pdo->beginTransaction();
try {
    if ($templateId > 0) {
        $pdo->prepare('UPDATE `approval_approval_templates` SET name = ?, description = ?, category_id = ?, title_template = ?, content_template = ?, is_active = ?, updated_at = NOW() WHERE id = ?')->execute([$name, $description !== '' ? $description : null, $categoryId > 0 ? $categoryId : null, $titleTemplate !== '' ? $titleTemplate : null, $contentTemplate !== '' ? $contentTemplate : null, $isActive, $templateId]);
        sync_template_steps($pdo, $templateId, $selected);
        sync_template_readers($pdo, $templateId, $selectedReaders);
    } else {
        $pdo->prepare('INSERT INTO `approval_approval_templates` (company_id, name, description, category_id, title_template, content_template, is_active, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())')->execute([$companyId, $name, $description !== '' ? $description : null, $categoryId > 0 ? $categoryId : null, $titleTemplate !== '' ? $titleTemplate : null, $contentTemplate !== '' ? $contentTemplate : null, $isActive, (int) $pageUser['id']]);
        $templateId = (int) $pdo->lastInsertId();
        sync_template_steps($pdo, $templateId, $selected);
        sync_template_readers($pdo, $templateId, $selectedReaders);
    }
    $pdo->commit();
    log_activity($pdo, $pageUser, $companyId, $template ? 'template_updated' : 'template_created', $name . ' 템플릿 ' . ($template ? '수정' : '생성'), 'template', $templateId);
    set_flash('success', $template ? '결재선 템플릿이 수정되었습니다.' : '결재선 템플릿이 저장되었습니다.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    set_flash('error', $e->getMessage());
}
redirect_to('admin/company_settings.php?company_id=' . $companyId);
