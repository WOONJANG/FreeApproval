<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageUser = require_login($pdo);
$pageTitle = '대시보드';
$stats = get_dashboard_stats($pdo, $pageUser);
$recentNotifications = get_notifications($pdo, (int) $pageUser['id'], 5);
$recentDocs = array_slice(get_documents_for_scope($pdo, $pageUser, is_admin($pageUser) ? 'all' : 'my'), 0, 5);
$recentCompanies = is_super_admin($pageUser) ? array_slice(get_company_admin_stats($pdo), 0, 5) : [];
$recentNotices = get_recent_visible_notices($pdo, $pageUser, 5);
$recentActivities = get_company_activity_logs($pdo, $pageUser, 8, is_super_admin($pageUser) ? 0 : (int) ($pageUser['company_id'] ?? 0));
require __DIR__ . '/includes/header.php';
?>
<div class="grid-4">
    <?php if (is_super_admin($pageUser)): ?>
        <div class="stat-box"><h3>가입 회사</h3><strong><?= e((string) $stats['company_count']) ?></strong></div>
        <div class="stat-box"><h3>전체 가입자</h3><strong><?= e((string) $stats['active_users']) ?></strong></div>
        <div class="stat-box"><h3>오늘 신규 기안</h3><strong><?= e((string) $stats['today_docs']) ?></strong></div>
        <div class="stat-box"><h3>7일 이상 대기</h3><strong><?= e((string) $stats['old_waiting']) ?></strong></div>
    <?php elseif (is_company_admin($pageUser)): ?>
        <div class="stat-box"><h3>회사 전체 문서</h3><strong><?= e((string) $stats['total_docs']) ?></strong></div>
        <div class="stat-box"><h3>오늘 신규 기안</h3><strong><?= e((string) $stats['today_docs']) ?></strong></div>
        <div class="stat-box"><h3>오늘 완료</h3><strong><?= e((string) $stats['today_approved']) ?></strong></div>
        <div class="stat-box"><h3>회사 인원</h3><strong><?= e((string) $stats['active_users']) ?></strong></div>
    <?php else: ?>
        <div class="stat-box"><h3>내 문서</h3><strong><?= e((string) $stats['my_docs']) ?></strong></div>
        <div class="stat-box"><h3>내 결재 대기</h3><strong><?= e((string) $stats['waiting_for_me']) ?></strong></div>
        <div class="stat-box"><h3>결재 완료</h3><strong><?= e((string) $stats['approved_docs']) ?></strong></div>
        <div class="stat-box"><h3>읽지 않은 알림</h3><strong><?= e((string) $stats['unread_notifications']) ?></strong></div>
    <?php endif; ?>
</div>

<?php if (!is_super_admin($pageUser)): ?>
<div class="grid-3" style="margin-top:18px;">
    <div class="stat-box"><h3>서비스 상태</h3><strong><?= e(company_status_label((string) ($pageUser['service_status'] ?? 'trial'))) ?></strong></div>
    <div class="stat-box"><h3>플랜</h3><strong><?= e((string) ($pageUser['plan_name'] ?? 'Free')) ?></strong></div>
    <div class="stat-box"><h3>일괄 승인</h3><strong><?= (int) ($pageUser['allow_bulk_approval'] ?? 0) === 1 ? '허용' : '비허용' ?></strong></div>
</div>
<?php endif; ?>

<div class="grid-2" style="margin-top:18px;">
    <section class="card">
        <div class="section-head"><h3>최근 문서</h3><a href="<?= e(base_url('documents.php?view=all')) ?>" class="btn btn-sm btn-outline">전체 보기</a></div>
        <?php if (!$recentDocs): ?>
            <div class="empty-state">문서가 없습니다.</div>
        <?php else: ?>
            <div class="timeline compact">
                <?php foreach ($recentDocs as $doc): ?>
                    <div class="timeline-item">
                        <div class="timeline-item-head">
                            <strong><?= e($doc['doc_no'] ?: '-') ?> · <?= e($doc['title']) ?></strong>
                            <span class="<?= e(status_badge_class($doc['status'])) ?>"><?= e(document_status_label($doc['status'])) ?></span>
                        </div>
                        <p><?= e($doc['writer_name']) ?> / <?= e($doc['writer_job_title'] ?: '직급 미설정') ?></p>
                        <p>분류 <?= e($doc['category_name'] ?: '-') ?> · <?= e(current_step_text($doc)) ?> · 결재대기 <?= e(waiting_days_text($doc['submitted_at'])) ?></p>
                        <a href="<?= e(base_url('document_view.php?id=' . $doc['id'])) ?>" class="text-link">상세 보기</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <?php if (is_super_admin($pageUser)): ?>
            <div class="section-head"><h3>최근 가입 회사</h3><a href="<?= e(base_url('admin/companies.php')) ?>" class="btn btn-sm btn-outline">기업 관리</a></div>
            <?php if (!$recentCompanies): ?>
                <div class="empty-state">가입한 회사가 없습니다.</div>
            <?php else: ?>
                <div class="timeline compact">
                    <?php foreach ($recentCompanies as $company): ?>
                        <div class="timeline-item">
                            <div class="timeline-item-head"><strong><?= e($company['company_name']) ?></strong><span class="badge badge-gray"><?= e($company['company_code']) ?></span></div>
                            <p>대표자: <?= e($company['owner_name']) ?> / <?= e(format_phone($company['owner_phone'])) ?></p>
                            <p><?= e(company_status_label((string) $company['service_status'])) ?> · <?= e($company['plan_name']) ?> · 가입자 <?= e((string) $company['user_count']) ?>명</p>
                            <a href="<?= e(base_url('admin/company_settings.php?company_id=' . $company['id'])) ?>" class="text-link">회사 설정 보기</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="section-head"><h3>최근 알림</h3><a href="<?= e(base_url('notifications.php')) ?>" class="btn btn-sm btn-outline">알림함</a></div>
            <?php if (!$recentNotifications): ?>
                <div class="empty-state">알림이 없습니다.</div>
            <?php else: ?>
                <div class="timeline compact">
                    <?php foreach ($recentNotifications as $notice): ?>
                        <div class="timeline-item <?= (int) $notice['is_read'] ? '' : 'is-unread' ?>">
                            <div class="timeline-item-head"><strong><?= e($notice['title']) ?></strong><?php if (!(int) $notice['is_read']): ?><span class="badge badge-blue">새 알림</span><?php endif; ?></div>
                            <p><?= e($notice['message']) ?></p>
                            <p><?= e(format_datetime($notice['created_at'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<div class="grid-2" style="margin-top:18px;">
    <section class="card">
        <div class="section-head">
            <h3>최근 공지사항</h3>
            <div class="btn-row">
                <a href="<?= e(base_url('notices.php')) ?>" class="btn btn-sm btn-outline">전체 보기</a>
                <?php if (can_manage_notices($pageUser)): ?><a href="<?= e(base_url('notice_form.php')) ?>" class="btn btn-sm btn-outline">글쓰기</a><?php endif; ?>
            </div>
        </div>
        <?php if (!$recentNotices): ?>
            <div class="empty-state">공지사항이 없습니다.</div>
        <?php else: ?>
            <div class="timeline compact">
                <?php foreach ($recentNotices as $notice): ?>
                    <div class="timeline-item">
                        <div class="timeline-item-head">
                            <strong><?= e($notice['title']) ?></strong>
                            <span class="<?= e((int) $notice['is_global'] ? 'badge badge-blue' : ((int) $notice['is_notice'] ? 'badge badge-amber' : 'badge badge-gray')) ?>"><?= e(notice_type_label($notice)) ?></span>
                        </div>
                        <p><?= e($notice['writer_name']) ?> / <?= e($notice['writer_job_title'] ?: ((int) $notice['is_global'] ? '프로젝트 관리자' : '직급 미설정')) ?></p>
                        <p>조회수 <?= e((string) $notice['view_count']) ?> · <?= e(format_datetime($notice['created_at'])) ?></p>
                        <a href="<?= e(base_url('notice_view.php?id=' . $notice['id'])) ?>" class="text-link">상세 보기</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="section-head"><h3>최근 활동 로그</h3><?= is_super_admin($pageUser) ? '<span class="badge badge-blue">전체</span>' : '' ?></div>
        <?php if (!$recentActivities): ?>
            <div class="empty-state">최근 활동이 없습니다.</div>
        <?php else: ?>
            <div class="timeline compact">
                <?php foreach ($recentActivities as $log): ?>
                    <div class="timeline-item">
                        <div class="timeline-item-head"><strong><?= e($log['action_key']) ?></strong><span class="badge badge-gray"><?= e(format_datetime($log['created_at'])) ?></span></div>
                        <p><?= e($log['description']) ?></p>
                        <p><?= e($log['user_name'] ?: '시스템') ?><?= $log['company_name'] ? ' · ' . e($log['company_name']) : '' ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
