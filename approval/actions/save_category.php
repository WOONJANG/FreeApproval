<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_company_settings_manager($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('admin/company_settings.php'); }
verify_csrf_or_fail();
$companyId = max(0, (int) ($_POST['company_id'] ?? 0));
$company = get_company_settings($pdo, $pageUser, $companyId);
if (!$company) { set_flash('error', '회사를 찾을 수 없습니다.'); redirect_to('admin/company_settings.php'); }
$categoryId = max(0, (int) ($_POST['category_id'] ?? 0));
$name = trim((string) ($_POST['name'] ?? ''));
$sortOrder = max(1, (int) ($_POST['sort_order'] ?? 1));
$isActive = isset($_POST['is_active']) ? 1 : 0;
if ($name === '') { set_flash('error', '분류명을 입력하세요.'); redirect_to('admin/company_settings.php?company_id=' . $companyId); }
try {
    if ($categoryId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM `approval_document_categories` WHERE id = ? LIMIT 1');
        $stmt->execute([$categoryId]);
        $category = $stmt->fetch();
        if (!$category || (int) $category['company_id'] !== $companyId) {
            throw new RuntimeException('수정할 분류를 찾을 수 없습니다.');
        }
        $pdo->prepare('UPDATE `approval_document_categories` SET name = ?, sort_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?')->execute([$name, $sortOrder, $isActive, $categoryId]);
        log_activity($pdo, $pageUser, $companyId, 'category_updated', $name . ' 분류 수정', 'category', $categoryId);
        set_flash('success', '문서 분류가 수정되었습니다.');
    } else {
        $pdo->prepare('INSERT INTO `approval_document_categories` (company_id, name, sort_order, is_active, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())')->execute([$companyId, $name, $sortOrder, $isActive, (int) $pageUser['id']]);
        log_activity($pdo, $pageUser, $companyId, 'category_created', $name . ' 분류 추가', 'category', (int) $pdo->lastInsertId());
        set_flash('success', '문서 분류가 추가되었습니다.');
    }
} catch (Throwable $e) {
    set_flash('error', $e->getMessage());
}
redirect_to('admin/company_settings.php?company_id=' . $companyId);
