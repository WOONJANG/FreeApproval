<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_company_settings_manager($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('admin/company_settings.php'); }
verify_csrf_or_fail();
$templateId = max(0, (int) ($_POST['template_id'] ?? 0));
$template = get_template_by_id($pdo, $templateId);
if (!$template || !same_company($pageUser, (int) $template['company_id'])) { set_flash('error', '템플릿을 찾을 수 없습니다.'); redirect_to('admin/company_settings.php'); }
$usageCount = get_template_usage_count($pdo, $templateId);
if ($usageCount > 0) {
    set_flash('error', '이미 문서에서 사용된 템플릿은 삭제할 수 없습니다. 비활성화 후 유지하세요.');
    redirect_to('admin/company_settings.php?company_id=' . (int) $template['company_id']);
}
$pdo->beginTransaction();
try {
    $pdo->prepare('DELETE FROM `approval_approval_template_readers` WHERE template_id = ?')->execute([$templateId]);
    $pdo->prepare('DELETE FROM `approval_approval_template_steps` WHERE template_id = ?')->execute([$templateId]);
    $pdo->prepare('DELETE FROM `approval_approval_templates` WHERE id = ?')->execute([$templateId]);
    $pdo->commit();
    log_activity($pdo, $pageUser, (int) $template['company_id'], 'template_deleted', $template['name'] . ' 템플릿 삭제', 'template', $templateId);
    set_flash('success', '템플릿이 삭제되었습니다.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    set_flash('error', $e->getMessage());
}
redirect_to('admin/company_settings.php?company_id=' . (int) $template['company_id']);
