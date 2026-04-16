<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_notice_manager($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect_to('notices.php'); }
verify_csrf_or_fail();
$noticeId = (int) ($_POST['notice_id'] ?? 0);
$title = trim((string) ($_POST['title'] ?? ''));
$content = trim((string) ($_POST['content'] ?? ''));
$isNotice = isset($_POST['is_notice']) ? 1 : 0;
$isPopup = isset($_POST['is_popup']) ? 1 : 0;
$isBanner = isset($_POST['is_banner']) ? 1 : 0;
$activeFrom = trim((string) ($_POST['active_from'] ?? ''));
$activeUntil = trim((string) ($_POST['active_until'] ?? ''));
if ($title === '' || $content === '') { set_flash('error', '제목과 내용을 모두 입력하세요.'); redirect_to($noticeId > 0 ? 'notice_form.php?id=' . $noticeId : 'notice_form.php'); }
if (mb_strlen($title) > 150) { set_flash('error', '제목은 150자 이하여야 합니다.'); redirect_to($noticeId > 0 ? 'notice_form.php?id=' . $noticeId : 'notice_form.php'); }
if (is_super_admin($pageUser)) { $companyId = null; $isGlobal = 1; $isNotice = 1; } else { $companyId = (int) $pageUser['company_id']; $isGlobal = 0; }
$params = [$title, $content, $isNotice, $isGlobal, $isPopup, $isBanner, $activeFrom !== '' ? date('Y-m-d H:i:s', strtotime($activeFrom)) : null, $activeUntil !== '' ? date('Y-m-d H:i:s', strtotime($activeUntil)) : null];
if ($noticeId > 0) {
    $existing = get_notice_by_id($pdo, $noticeId);
    if (!$existing || !can_manage_notice($pageUser, $existing)) { set_flash('error', '수정할 수 없는 공지사항입니다.'); redirect_to('notices.php'); }
    $stmt = $pdo->prepare('UPDATE `approval_notices` SET title = ?, content = ?, is_notice = ?, is_global = ?, is_popup = ?, is_banner = ?, active_from = ?, active_until = ?, updated_at = NOW() WHERE id = ?');
    $params[] = $noticeId;
    $stmt->execute($params);
    log_activity($pdo, $pageUser, (int) ($existing['company_id'] ?? ($pageUser['company_id'] ?? 0)), 'notice_updated', $title . ' 공지 수정', 'notice', $noticeId);
    set_flash('success', '공지사항이 수정되었습니다.');
    redirect_to('notice_view.php?id=' . $noticeId);
}
$stmt = $pdo->prepare('INSERT INTO `approval_notices` (company_id, writer_user_id, title, content, is_notice, is_global, is_popup, is_banner, view_count, active_from, active_until, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW(), NOW())');
$stmt->execute([$companyId, (int) $pageUser['id'], $title, $content, $isNotice, $isGlobal, $isPopup, $isBanner, $activeFrom !== '' ? date('Y-m-d H:i:s', strtotime($activeFrom)) : null, $activeUntil !== '' ? date('Y-m-d H:i:s', strtotime($activeUntil)) : null]);
$newId = (int) $pdo->lastInsertId();
log_activity($pdo, $pageUser, (int) ($companyId ?: 0), 'notice_created', $title . ' 공지 등록', 'notice', $newId);
set_flash('success', '공지사항이 등록되었습니다.');
redirect_to('notice_view.php?id=' . $newId);
