<?php
declare(strict_types=1);
$pageUser = $pageUser ?? (isset($pdo) ? current_user($pdo) : null);
$pageTitle = $pageTitle ?? APP_NAME;
$successFlash = get_flash('success');
$errorFlash = get_flash('error');
$unreadCount = ($pageUser && isset($pdo)) ? unread_notification_count($pdo, (int) $pageUser['id']) : 0;
$bannerNotices = ($pageUser && isset($pdo)) ? get_company_banner_notices($pdo, $pageUser) : [];
$popupNotices = ($pageUser && isset($pdo)) ? get_company_popup_notices($pdo, $pageUser) : [];
$locale = current_locale($pageUser);
?>
<!doctype html>
<html lang="<?= e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
    <script defer src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</head>
<body>
<div class="app-shell">
    <?php if ($pageUser): ?>
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <div class="brand-mark">APR</div>
                <div>
                    <strong><?= e(APP_NAME) ?></strong>
                    <p><?= is_super_admin($pageUser) ? '프로젝트 전체 관리' : e(($pageUser['company_name'] ?? '회사 미지정') . ' 결재 관리') ?></p>
                </div>
            </div>

            <?= render_adfit_slot('sidebar', '사이드바 AdFit 자리') ?>

            <nav class="nav">
                <a class="<?= is_active_menu('/index.php') ? 'active' : '' ?>" href="<?= e(base_url('index.php')) ?>"><?= e(t('dashboard', [], $pageUser)) ?></a>
                <a class="<?= is_active_menu('/documents.php') && (($_GET['view'] ?? 'all') === 'all') ? 'active' : '' ?>" href="<?= e(base_url('documents.php?view=all')) ?>">기안서 통합</a>
                <a class="<?= is_active_menu('/documents.php') && (($_GET['view'] ?? '') === 'my') ? 'active' : '' ?>" href="<?= e(base_url('documents.php?view=my')) ?>">내 기안서</a>
                <a class="<?= is_active_menu('/documents.php') && (($_GET['view'] ?? '') === 'waiting') ? 'active' : '' ?>" href="<?= e(base_url('documents.php?view=waiting')) ?>">결재 대기</a>
                <a class="<?= is_active_menu('/documents.php') && (($_GET['view'] ?? '') === 'completed') ? 'active' : '' ?>" href="<?= e(base_url('documents.php?view=completed')) ?>">결재 완료</a>
                <a class="<?= is_active_menu('/documents.php') && (($_GET['view'] ?? '') === 'rejected') ? 'active' : '' ?>" href="<?= e(base_url('documents.php?view=rejected')) ?>">반려함</a>
                <a class="<?= is_active_menu('/notifications.php') ? 'active' : '' ?>" href="<?= e(base_url('notifications.php')) ?>">알림<?php if ($unreadCount > 0): ?><span class="nav-badge"><?= e((string) $unreadCount) ?></span><?php endif; ?></a>
                <a class="<?= is_active_menu('/notices.php') || is_active_menu('/notice_view.php') || is_active_menu('/notice_form.php') ? 'active' : '' ?>" href="<?= e(base_url('notices.php')) ?>"><?= e(t('notices', [], $pageUser)) ?></a>
                <a class="<?= is_active_menu('/guide.php') ? 'active' : '' ?>" href="<?= e(base_url('guide.php')) ?>">사용 가이드</a>
                <a class="<?= is_active_menu('/document_create.php') ? 'active' : '' ?>" href="<?= e(base_url('document_create.php')) ?>"><?= e(t('create_document', [], $pageUser)) ?></a>
                <?php if (can_manage_members($pageUser) || is_admin($pageUser)): ?>
                    <div class="nav-section"><?= is_admin($pageUser) ? '관리/설정' : '운영 권한' ?></div>
                    <?php if (can_manage_members($pageUser)): ?><a class="<?= is_active_menu('/admin/users.php') ? 'active' : '' ?>" href="<?= e(base_url('admin/users.php')) ?>"><?= e(t('users', [], $pageUser)) ?></a><?php endif; ?>
                    <a class="<?= is_active_menu('/admin/company_settings.php') ? 'active' : '' ?>" href="<?= e(base_url('admin/company_settings.php')) ?>"><?= e(is_admin($pageUser) ? '회사 관리' : '회사 설정') ?></a>
                    <?php if (is_admin($pageUser)): ?><a class="<?= is_active_menu('/documents.php') && (($_GET['view'] ?? '') === 'trash') ? 'active' : '' ?>" href="<?= e(base_url('documents.php?view=trash')) ?>">휴지통</a><?php endif; ?>
                <?php endif; ?>
                <?php if (is_super_admin($pageUser)): ?>
                    <a class="<?= is_active_menu('/admin/companies.php') ? 'active' : '' ?>" href="<?= e(base_url('admin/companies.php')) ?>">기업 관리</a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-user">
                <strong><?= e($pageUser['name']) ?></strong>
                <span><?= e($pageUser['job_title'] ?: (is_super_admin($pageUser) ? '프로젝트 관리자' : '직급 미설정')) ?></span>
                <?php if (!is_super_admin($pageUser)): ?>
                    <span><?= e($pageUser['company_name'] ?: '회사 미지정') ?><?php if (is_admin($pageUser) && !empty($pageUser['company_code'])): ?> · 코드 <?= e($pageUser['company_code']) ?><?php endif; ?></span>
                    <span><span class="<?= e(company_status_badge((string) ($pageUser['service_status'] ?? 'trial'))) ?>"><?= e(company_status_label((string) ($pageUser['service_status'] ?? 'trial'))) ?></span> <span class="<?= e(plan_badge_class((string) ($pageUser['plan_name'] ?? 'Free'))) ?>"><?= e($pageUser['plan_name'] ?? 'Free') ?></span></span>
                <?php endif; ?>
                <div class="lang-switch"><a href="<?= e(current_path() . '?lang=ko') ?>">KO</a> · <a href="<?= e(current_path() . '?lang=en') ?>">EN</a></div>
                <a href="<?= e(base_url('actions/logout.php')) ?>" class="logout-link"><?= e(t('logout', [], $pageUser)) ?></a>
            </div>
        </aside>
    <?php endif; ?>

    <div class="main-area">
        <?php if ($pageUser): ?>
            <header class="topbar">
                <button type="button" class="menu-toggle" data-toggle-sidebar>☰</button>
                <div>
                    <h1><?= e($pageTitle) ?></h1>
                    <p class="muted"><?= is_super_admin($pageUser) ? '전체 회사, 플랜, 공지 배너/팝업, 가입자, 문서를 한 번에 관리합니다.' : (is_company_admin($pageUser) ? '회사 관리자 화면입니다. 레벨은 관리자 화면에서만 보이고 일반 화면에는 직급명만 보입니다.' : (has_company_delegate_permissions($pageUser) ? '회사 대표가 위임한 운영 권한 사용자입니다. 결재 승인 권한은 별도로 적용됩니다.' : '일반 화면에는 레벨이 숨겨지고 직급명만 표시됩니다.')) ?></p>
                </div>
            </header>
        <?php endif; ?>

        <main class="page">
            <?= render_adfit_slot('top', '상단 AdFit 728x90 / 320x100') ?>
            <?php foreach ($bannerNotices as $bn): ?>
                <div class="banner-notice <?= (int) ($bn['is_global'] ?? 0) === 1 ? 'is-global' : '' ?>"><strong><?= e(notice_type_label($bn)) ?></strong> <?= e($bn['title']) ?> <a href="<?= e(base_url('notice_view.php?id=' . $bn['id'])) ?>">자세히</a></div>
            <?php endforeach; ?>
            <?php if ($successFlash): ?>
                <div class="alert alert-success"><?= e($successFlash) ?></div>
            <?php endif; ?>
            <?php if ($errorFlash): ?>
                <div class="alert alert-error"><?= e($errorFlash) ?></div>
            <?php endif; ?>
            <?php foreach ($popupNotices as $pn): ?>
                <div class="modal notice-popup" id="popupNotice<?= e((string) $pn['id']) ?>">
                    <div class="modal-content">
                        <h3><?= e($pn['title']) ?></h3>
                        <div class="box-content"><?= nl2br(e($pn['content'])) ?></div>
                        <div class="btn-row" style="margin-top:16px;">
                            <a href="<?= e(base_url('notice_view.php?id=' . $pn['id'])) ?>" class="btn btn-sm btn-secondary">상세 보기</a>
                            <button type="button" class="btn btn-sm btn-outline" data-dismiss-popup="popupNotice<?= e((string) $pn['id']) ?>">오늘 그만 보기</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
