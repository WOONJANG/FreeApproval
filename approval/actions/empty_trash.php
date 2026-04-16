<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to('documents.php?view=trash');
}
csrf_validate();
$pageUser = require_login($pdo);
if (!is_admin($pageUser)) {
    set_flash('error', '권한이 없습니다.');
    redirect_to('documents.php');
}
$companyId = max(0, (int) ($_POST['company_id'] ?? 0));
try {
    $count = empty_trash_for_scope($pdo, $pageUser, $companyId);
    set_flash('success', $count > 0 ? '휴지통에서 ' . $count . '건을 영구 삭제했습니다.' : '휴지통에 비울 문서가 없습니다.');
} catch (Throwable $e) {
    set_flash('error', '휴지통 비우기에 실패했습니다.');
}
$target = 'documents.php?view=trash';
if (is_super_admin($pageUser) && $companyId > 0) {
    $target .= '&company_id=' . $companyId;
}
redirect_to($target);
