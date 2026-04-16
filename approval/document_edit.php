<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageUser = require_login($pdo);
$documentId = (int) ($_GET['id'] ?? 0);
$document = $documentId > 0 ? get_document($pdo, $documentId) : null;
if (!$document || !user_can_view_document($pdo, $pageUser, $document)) {
    http_response_code(404); exit('문서를 찾을 수 없습니다.');
}
if (!can_edit_document($pdo, $pageUser, $document)) {
    http_response_code(403); exit('이 문서는 수정할 수 없습니다.');
}
$attachments = get_document_attachments($pdo, $documentId);
$categories = get_company_categories($pdo, (int) $pageUser['company_id'], true);
$templates = get_company_templates($pdo, (int) $pageUser['company_id'], true);
$selectedTemplateId = (int) ($document['template_id'] ?? 0);
$approvalLine = get_assigned_approval_line($pdo, (int) $pageUser['id'], $selectedTemplateId > 0 ? $selectedTemplateId : null);
$willAutoApprove = will_auto_approve_submission($pdo, (int) $pageUser['id'], $selectedTemplateId > 0 ? $selectedTemplateId : null);
$readerOptions = get_company_members_for_reader($pdo, (int) $pageUser['company_id'], (int) $pageUser['id']);
$selectedReaderIds = get_document_reader_ids($pdo, $documentId);
$pageTitle = $document['status'] === 'rejected' ? '반려 문서 수정' : '기안서 수정';
require __DIR__ . '/includes/header.php';
?>
<div class="grid-2">
    <div class="card">
        <form action="<?= e(base_url('actions/save_document.php')) ?>" method="post" enctype="multipart/form-data" class="form-grid" id="docForm">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="document_id" value="<?= e((string) $document['id']) ?>">
            <div class="form-group">
                <label for="category_id">문서 분류</label>
                <select id="category_id" name="category_id">
                    <option value="0">분류 선택</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>"<?= (int) ($document['category_id'] ?? 0) === (int) $category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="template_id">결재선 템플릿</label>
                <select id="template_id" name="template_id">
                    <option value="0">개인 기본 결재선 사용</option>
                    <?php foreach ($templates as $template): ?>
                        <option value="<?= e((string) $template['id']) ?>"<?= $selectedTemplateId === (int) $template['id'] ? ' selected' : '' ?>><?= e($template['name']) ?><?= $template['category_name'] ? ' · ' . e($template['category_name']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full"><label for="title">제목</label><input type="text" id="title" name="title" required maxlength="150" value="<?= e($document['title']) ?>"></div>
            <div class="form-group full"><label for="content">내용</label><textarea id="content" name="content" required><?= e($document['content']) ?></textarea></div>
            <div class="form-group full">
                <label for="reader_ids">참조자 / 열람자 지정</label>
                <select id="reader_ids" name="reader_ids[]" multiple size="6">
                    <?php foreach ($readerOptions as $reader): ?>
                        <option value="<?= e((string) $reader['id']) ?>"<?= in_array((int) $reader['id'], $selectedReaderIds, true) ? ' selected' : '' ?>><?= e($reader['name']) ?> / <?= e($reader['job_title'] ?: '직급 미설정') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($attachments): ?><div class="form-group full"><label>기존 첨부파일</label><div class="attachments"><?php foreach ($attachments as $file): ?><a class="attachment-link" href="<?= e(base_url('download.php?file_id=' . $file['id'])) ?>"><?= e($file['original_name']) ?></a><?php endforeach; ?></div></div><?php endif; ?>
            <div class="form-group full"><label for="attachments">첨부파일 추가</label><input type="file" id="attachments" name="attachments[]" multiple><div class="muted">기존 첨부파일은 유지되고 새 파일만 추가됩니다. 최대 <?= e((string) MAX_ATTACHMENTS) ?>개</div></div>
            <div class="form-group full desktop-actions"><div class="btn-row"><button type="submit" name="submit_action" value="save" class="btn-secondary">저장</button><button type="submit" name="submit_action" value="submit" class="btn-primary"><?= $document['status'] === 'rejected' ? '다시 기안하기' : '기안하기' ?></button></div></div>
        </form>
    </div>
    <div class="card">
        <h3 style="margin-top:0;">적용될 결재선</h3>
        <?php if (!$approvalLine): ?>
            <?php if ($willAutoApprove): ?>
                <div class="empty-state">현재 선택한 결재선에서는 기안자보다 높은 레벨이 없어 제출 즉시 자동 승인됩니다.</div>
            <?php else: ?>
                <div class="empty-state">회사 관리자가 아직 결재선을 지정하지 않았습니다.</div>
            <?php endif; ?>
        <?php else: ?><div class="timeline"><?php foreach ($approvalLine as $idx => $approver): ?><div class="timeline-item"><div class="timeline-item-head"><strong><?= e((string) ($idx + 1)) ?>차 결재</strong><span class="badge badge-gray">예정</span></div><p>결재자: <?= e($approver['name']) ?> / <?= e($approver['job_title'] ?: '직급 미설정') ?></p></div><?php endforeach; ?></div><?php endif; ?>
    </div>
</div>
<div class="mobile-action-bar">
    <button type="submit" form="docForm" name="submit_action" value="save" class="btn-secondary">저장</button>
    <button type="submit" form="docForm" name="submit_action" value="submit" class="btn-primary"><?= $document['status'] === 'rejected' ? '다시 기안' : '기안하기' ?></button>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
