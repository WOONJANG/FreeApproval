<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageUser = require_login($pdo);
$pageTitle = '알림함';
$notifications = get_notifications($pdo, (int) $pageUser['id'], 100);
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="section-head">
        <h3>전체 알림</h3>
        <form action="<?= e(base_url('actions/mark_notifications_read.php')) ?>" method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button type="submit" class="btn btn-sm btn-outline">모두 읽음 처리</button></form>
    </div>
    <?php if (!$notifications): ?><div class="empty-state">알림이 없습니다.</div><?php else: ?><div class="timeline"><?php foreach ($notifications as $notice): ?><div class="timeline-item <?= (int) $notice['is_read'] ? '' : 'is-unread' ?>"><div class="timeline-item-head"><strong><?= e($notice['title']) ?></strong><?php if (!(int) $notice['is_read']): ?><span class="badge badge-blue">새 알림</span><?php endif; ?></div><p><?= e($notice['message']) ?></p><p><?= e(format_datetime($notice['created_at'])) ?></p><?php if ($notice['link_url']): ?><a href="<?= e($notice['link_url']) ?>" class="text-link">관련 문서 열기</a><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
