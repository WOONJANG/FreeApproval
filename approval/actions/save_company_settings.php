<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_company_settings_manager($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('admin/company_settings.php'); }
verify_csrf_or_fail();
$companyId = max(0, (int) ($_POST['company_id'] ?? 0));
$company = get_company_settings($pdo, $pageUser, $companyId);
if (!$company) { set_flash('error', '회사를 찾을 수 없습니다.'); redirect_to('admin/company_settings.php'); }
$serviceStatus = (string) ($_POST['service_status'] ?? $company['service_status']);
$planName = trim((string) ($_POST['plan_name'] ?? $company['plan_name']));
$memberLimit = trim((string) ($_POST['member_limit'] ?? ''));
$documentLimit = trim((string) ($_POST['document_limit'] ?? ''));
$trialEndsAt = trim((string) ($_POST['trial_ends_at'] ?? ''));
$adfreeUntil = trim((string) ($_POST['adfree_until'] ?? ''));
$locale = in_array((string) ($_POST['locale'] ?? 'ko'), ['ko','en'], true) ? (string) $_POST['locale'] : 'ko';
$allowBulk = isset($_POST['allow_bulk_approval']) ? 1 : 0;
if (!is_super_admin($pageUser)) {
    $serviceStatus = $company['service_status'];
    $planName = $company['plan_name'];
    $memberLimit = (string) ($company['member_limit'] ?? '');
    $documentLimit = (string) ($company['document_limit'] ?? '');
    $trialEndsAt = !empty($company['trial_ends_at']) ? date('Y-m-d\TH:i', strtotime($company['trial_ends_at'])) : '';
    $adfreeUntil = !empty($company['adfree_until']) ? date('Y-m-d\TH:i', strtotime($company['adfree_until'])) : '';
}
$pdo->prepare('UPDATE `approval_companies` SET service_status = ?, plan_name = ?, member_limit = ?, document_limit = ?, trial_ends_at = ?, adfree_until = ?, allow_bulk_approval = ?, locale = ?, updated_at = NOW() WHERE id = ?')->execute([
    in_array($serviceStatus, ['trial','active','suspended','expired'], true) ? $serviceStatus : 'trial',
    $planName !== '' ? $planName : 'Free',
    $memberLimit !== '' ? (int) $memberLimit : null,
    $documentLimit !== '' ? (int) $documentLimit : null,
    $trialEndsAt !== '' ? date('Y-m-d H:i:s', strtotime($trialEndsAt)) : null,
    $adfreeUntil !== '' ? date('Y-m-d H:i:s', strtotime($adfreeUntil)) : null,
    $allowBulk,
    $locale,
    $companyId,
]);
log_activity($pdo, $pageUser, $companyId, 'company_settings_saved', $company['company_name'] . ' 회사 설정 저장', 'company', $companyId);
set_flash('success', '회사 설정이 저장되었습니다.');
redirect_to('admin/company_settings.php?company_id=' . $companyId);
