<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageUser = require_login($pdo);
$documentId = (int) ($_GET['id'] ?? 0);
$document = $documentId > 0 ? get_document($pdo, $documentId) : null;
if (!$document || !user_can_view_document($pdo, $pageUser, $document)) { http_response_code(404); exit('문서를 찾을 수 없습니다.'); }
mark_document_notifications_read($pdo, (int) $pageUser['id'], $documentId);
$attachments = get_document_attachments($pdo, $documentId);
$steps = get_document_steps($pdo, $documentId);
$logs = get_document_logs($pdo, $documentId);
$readers = get_document_readers($pdo, $documentId);
$canEdit = can_edit_document($pdo, $pageUser, $document);
$canApprove = can_approve_document($pdo, $pageUser, $document);
$canCancel = can_cancel_document($pdo, $pageUser, $document);
$canDelete = can_delete_document($pageUser, $document);
$canRestore = can_restore_document($pageUser, $document);
$canReassign = can_reassign_step($pageUser, $document);
$approverOptions = get_assignable_approvers($pdo, 0, (int) $document['company_id']);
$hasRevision = (int) $pdo->query('SELECT COUNT(*) FROM `approval_revisions` WHERE document_id = ' . (int) $documentId)->fetchColumn() > 0;
$pageTitle = '문서 상세';
require __DIR__ . '/includes/header.php';
?>
<div class="grid-2">
    <section class="card">
        <div class="btn-row space-between">
            <div class="btn-row">
                <span class="<?= e(status_badge_class($document['status'])) ?>"><?= e(document_status_label($document['status'])) ?></span>
                <span class="badge badge-gray"><?= e($document['doc_no'] ?: '-') ?></span>
                <span class="badge badge-gray">버전 v<?= e((string) $document['version_no']) ?></span>
                <?php if ($document['category_name']): ?><span class="badge badge-amber"><?= e($document['category_name']) ?></span><?php endif; ?>
                <?php if ($document['template_name']): ?><span class="badge badge-blue"><?= e($document['template_name']) ?></span><?php endif; ?>
            </div>
            <div class="btn-row desktop-actions">
                <?php if ($canEdit): ?><a href="<?= e(base_url('document_edit.php?id=' . $document['id'])) ?>" class="btn btn-sm btn-outline">수정</a><?php endif; ?>
                <?php if ($canCancel): ?><button type="button" class="btn btn-sm btn-outline" data-open-modal="cancelModal">기안취소</button><?php endif; ?>
                <?php if ($canDelete): ?><button type="button" class="btn btn-sm btn-danger" data-open-modal="deleteModal">휴지통</button><?php endif; ?>
                <?php if ($canRestore): ?><form action="<?= e(base_url('actions/restore_document.php')) ?>" method="post" style="display:inline;"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="document_id" value="<?= e((string) $document['id']) ?>"><button type="submit" class="btn btn-sm btn-secondary">복구</button></form><?php endif; ?>
                <a href="<?= e(base_url('document_print.php?id=' . $document['id'])) ?>" class="btn btn-sm btn-outline" target="_blank">인쇄용 보기</a>
                <a href="<?= e(base_url('documents.php?view=all')) ?>" class="btn btn-sm btn-outline">목록</a>
            </div>
        </div>
        <h2 style="margin:18px 0 10px;"><?= e($document['title']) ?></h2>
        <div class="doc-meta">
            <span>문서번호: <?= e($document['doc_no'] ?: '-') ?></span>
            <span>작성자: <?= e($document['writer_name']) ?> / <?= e($document['writer_job_title'] ?: '직급 미설정') ?></span>
            <span>회사: <?= e($document['company_name']) ?> (<?= e($document['company_code']) ?>)</span>
            <span>작성일: <?= e(format_datetime($document['created_at'])) ?></span>
            <span>최근 수정: <?= e(format_datetime($document['updated_at'])) ?></span>
        </div>
        <?php if ($document['status'] === 'deleted'): ?><div class="alert alert-error" style="margin-top:16px;">휴지통 이동 사유: <?= e($document['delete_reason'] ?: '-') ?><?php if ($document['deleted_by_name']): ?> · 처리자: <?= e($document['deleted_by_name']) ?><?php endif; ?></div><?php endif; ?>
        <?php if ($document['status'] === 'cancelled' && $document['cancel_reason']): ?><div class="alert alert-error" style="margin-top:16px;">기안취소 사유: <?= e($document['cancel_reason']) ?></div><?php endif; ?>
        <div class="card inner-card"><h3 style="margin-top:0;">본문</h3><div class="box-content"><?= nl2br(e($document['content'])) ?></div></div>
        <div class="card inner-card"><h3 style="margin-top:0;">첨부파일</h3><?php if (!$attachments): ?><div class="empty-state">첨부파일이 없습니다.</div><?php else: ?><div class="attachments"><?php foreach ($attachments as $file): ?><a class="attachment-link" href="<?= e(base_url('download.php?file_id=' . $file['id'])) ?>"><?= e($file['original_name']) ?> <span class="muted">(<?= e((string) $file['download_count']) ?>)</span></a><?php endforeach; ?></div><?php endif; ?></div>
        <div class="card inner-card"><h3 style="margin-top:0;">참조자 / 열람자</h3><?php if (!$readers): ?><div class="empty-state">지정된 참조자가 없습니다.</div><?php else: ?><div class="attachments"><?php foreach ($readers as $reader): ?><span class="attachment-link"><?= e($reader['name']) ?> / <?= e($reader['job_title'] ?: '직급 미설정') ?></span><?php endforeach; ?></div><?php endif; ?></div>
    </section>

    <section class="card">
        <div class="section-head"><h3 style="margin:0;">결재선</h3><div class="btn-row"><?php if ($document['status'] === 'submitted'): ?><form action="<?= e(base_url('actions/send_reminder.php')) ?>" method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="document_id" value="<?= e((string) $document['id']) ?>"><button type="submit" class="btn btn-sm btn-outline">결재 독촉</button></form><?php endif; ?><?php if ($hasRevision): ?><a href="<?= e(base_url('document_compare.php?id=' . $document['id'])) ?>" class="btn btn-sm btn-outline">재기안 비교</a><?php endif; ?></div></div>
        <div class="timeline">
            <?php foreach ($steps as $step): ?>
                <div class="timeline-item">
                    <div class="timeline-item-head">
                        <strong><?= e((string) $step['step_no']) ?>차 결재 · <?= e($step['approver_name']) ?></strong>
                        <span class="<?= e(status_badge_class($step['status'])) ?>"><?= e(step_status_label($step['status'])) ?></span>
                    </div>
                    <p><?= e($step['approver_job_title'] ?: '직급 미설정') ?><?php if (!(int) $step['approver_is_active']): ?> · 비활성 계정<?php endif; ?></p>
                    <p>처리시각: <?= e(format_datetime($step['acted_at'])) ?></p>
                    <p>의견: <?= e($step['comment'] ?: '-') ?></p>
                    <?php if ($canReassign && in_array($step['status'], ['waiting', 'pending'], true)): ?>
                        <form action="<?= e(base_url('actions/reassign_step.php')) ?>" method="post" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="document_id" value="<?= e((string) $document['id']) ?>">
                            <input type="hidden" name="step_id" value="<?= e((string) $step['id']) ?>">
                            <select name="new_approver_user_id" required>
                                <option value="">결재자 변경</option>
                                <?php foreach ($approverOptions as $opt): ?><option value="<?= e((string) $opt['id']) ?>"><?= e($opt['name']) ?> / <?= e($opt['job_title'] ?: '직급 미설정') ?> / Level <?= e((string) $opt['level_no']) ?></option><?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline">재지정</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($canApprove): ?>
            <div class="card inner-card">
                <h3 style="margin-top:0;">현재 내 차례 처리</h3>
                <form action="<?= e(base_url('actions/approve_document.php')) ?>" method="post" class="form-grid" id="approveForm">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="document_id" value="<?= e((string) $document['id']) ?>">
                    <div class="form-group full"><label for="approve_comment">승인 의견</label><textarea id="approve_comment" name="comment" placeholder="선택 입력"></textarea></div>
                    <div class="form-group full desktop-actions"><button type="submit" class="btn-primary">승인</button></div>
                </form>
                <form action="<?= e(base_url('actions/reject_document.php')) ?>" method="post" class="form-grid" style="margin-top:12px;" id="rejectForm">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="document_id" value="<?= e((string) $document['id']) ?>">
                    <div class="form-group full"><label for="reject_comment">반려 사유</label><textarea id="reject_comment" name="comment" required></textarea></div>
                    <div class="form-group full desktop-actions"><button type="submit" class="btn-danger">반려</button></div>
                </form>
            </div>
        <?php endif; ?>

        <div class="card inner-card">
            <h3 style="margin-top:0;">처리 이력</h3>
            <?php if (!$logs): ?><div class="empty-state">이력이 없습니다.</div><?php else: ?><div class="timeline compact"><?php foreach ($logs as $log): ?><div class="timeline-item"><div class="timeline-item-head"><strong><?= e(log_action_label($log['action_type'])) ?></strong><span class="badge badge-gray"><?= e(format_datetime($log['created_at'])) ?></span></div><p><?= e($log['name']) ?> / <?= e($log['job_title'] ?: '직급 미설정') ?></p><p><?= e($log['comment'] ?: '-') ?></p></div><?php endforeach; ?></div><?php endif; ?>
        </div>
    </section>
</div>

<?php if ($canCancel): ?>
<div class="modal" id="cancelModal"><div class="modal-content"><h3>기안취소</h3><form action="<?= e(base_url('actions/cancel_document.php')) ?>" method="post" class="form-grid"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="document_id" value="<?= e((string) $document['id']) ?>"><div class="form-group full"><label for="cancel_reason">취소 사유</label><textarea id="cancel_reason" name="reason" required></textarea></div><div class="btn-row"><button type="submit" class="btn-danger">기안취소</button><button type="button" class="btn-outline" data-close-modal>닫기</button></div></form></div></div>
<?php endif; ?>
<?php if ($canDelete): ?>
<div class="modal" id="deleteModal"><div class="modal-content"><h3>휴지통 이동</h3><form action="<?= e(base_url('actions/delete_document.php')) ?>" method="post" class="form-grid"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="document_id" value="<?= e((string) $document['id']) ?>"><div class="form-group full"><label for="delete_reason">삭제 사유</label><textarea id="delete_reason" name="reason" required></textarea></div><div class="btn-row"><button type="submit" class="btn-danger">휴지통 이동</button><button type="button" class="btn-outline" data-close-modal>닫기</button></div></form></div></div>
<?php endif; ?>
<?php if ($canApprove): ?>
<div class="mobile-action-bar">
    <button type="submit" form="approveForm" class="btn-primary">승인</button>
    <button type="submit" form="rejectForm" class="btn-danger">반려</button>
</div>
<?php elseif ($canEdit): ?>
<div class="mobile-action-bar">
    <a href="<?= e(base_url('document_edit.php?id=' . $document['id'])) ?>" class="btn-secondary">수정</a>
    <a href="<?= e(base_url('document_compare.php?id=' . $document['id'])) ?>" class="btn-outline">비교</a>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
