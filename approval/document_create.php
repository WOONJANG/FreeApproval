<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageUser = require_login($pdo);
if (is_super_admin($pageUser)) {
    set_flash('error', '최상위 관리자는 문서를 직접 기안하지 않습니다. 회사 계정으로 진행하세요.');
    redirect_to('index.php');
}
if (!company_has_capacity($pdo, (int) $pageUser['company_id'], 'document')) {
    set_flash('error', '현재 회사 플랜의 문서 저장 한도를 초과했습니다.');
    redirect_to('documents.php?view=all');
}
$pageTitle = '기안서 작성';
$categories = get_company_categories($pdo, (int) $pageUser['company_id'], true);
$templates = get_company_templates($pdo, (int) $pageUser['company_id'], true);
$selectedTemplateId = (int) ($_GET['template_id'] ?? 0);
$selectedTemplate = $selectedTemplateId > 0 ? get_template_by_id($pdo, $selectedTemplateId) : null;
if ($selectedTemplate && (int) ($selectedTemplate['company_id'] ?? 0) !== (int) $pageUser['company_id']) {
    $selectedTemplate = null;
    $selectedTemplateId = 0;
}
$approvalLine = get_assigned_approval_line($pdo, (int) $pageUser['id'], $selectedTemplateId > 0 ? $selectedTemplateId : null);
$willAutoApprove = will_auto_approve_submission($pdo, (int) $pageUser['id'], $selectedTemplateId > 0 ? $selectedTemplateId : null);
$readerOptions = get_company_members_for_reader($pdo, (int) $pageUser['company_id'], (int) $pageUser['id']);
$selectedReaderIds = $selectedTemplate ? get_template_reader_ids($pdo, (int) $selectedTemplate['id']) : [];
$selectedCategoryId = $selectedTemplate && !empty($selectedTemplate['category_id']) ? (int) $selectedTemplate['category_id'] : 0;
$prefillTitle = $selectedTemplate ? (string) ($selectedTemplate['title_template'] ?? '') : '';
$prefillContent = $selectedTemplate ? (string) ($selectedTemplate['content_template'] ?? '') : '';
require __DIR__ . '/includes/header.php';
?>
<div class="grid-2">
    <div class="card">
        <form action="<?= e(base_url('actions/save_document.php')) ?>" method="post" enctype="multipart/form-data" class="form-grid" id="docForm">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="form-group">
                <label for="category_id">문서 분류</label>
                <select id="category_id" name="category_id">
                    <option value="0">분류 선택</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>"<?= $selectedCategoryId === (int) $category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="template_id">결재선 템플릿</label>
                <select id="template_id" name="template_id" onchange="if(this.value){location.href='<?= e(base_url('document_create.php')) ?>?template_id='+this.value}">
                    <option value="0">개인 기본 결재선 사용</option>
                    <?php foreach ($templates as $template): ?>
                        <option value="<?= e((string) $template['id']) ?>"<?= $selectedTemplateId === (int) $template['id'] ? ' selected' : '' ?>><?= e($template['name']) ?><?= $template['category_name'] ? ' · ' . e($template['category_name']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group full"><label for="title">제목</label><input type="text" id="title" name="title" required maxlength="150" value="<?= e($prefillTitle) ?>"></div>
            <div class="form-group full"><label for="content">내용</label><textarea id="content" name="content" required><?= e($prefillContent) ?></textarea></div>
            <div class="form-group full">
                <label for="reader_ids">참조자 / 열람자 지정</label>
                <select id="reader_ids" name="reader_ids[]" multiple size="6">
                    <?php foreach ($readerOptions as $reader): ?>
                        <option value="<?= e((string) $reader['id']) ?>"<?= in_array((int) $reader['id'], $selectedReaderIds, true) ? ' selected' : '' ?>><?= e($reader['name']) ?> / <?= e($reader['job_title'] ?: '직급 미설정') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="muted">결재자는 아니지만 문서를 열람할 사람을 지정합니다.</div>
            </div>
            <div class="form-group full"><label for="attachments">첨부파일</label><input type="file" id="attachments" name="attachments[]" multiple><div class="muted">파일당 최대 10MB, 총 <?= e((string) MAX_ATTACHMENTS) ?>개까지 업로드 가능</div></div>
            <div class="form-group full desktop-actions"><div class="btn-row"><button type="submit" name="submit_action" value="save" class="btn-secondary">임시저장</button><button type="submit" name="submit_action" value="submit" class="btn-primary">기안하기</button></div></div>
        </form>
    </div>
    <div class="card">
        <h3 style="margin-top:0;">적용될 결재선</h3>
        <?php if (!$approvalLine): ?>
            <?php if ($willAutoApprove): ?>
                <div class="empty-state">현재 선택한 결재선에서는 기안자보다 높은 레벨이 없어 제출 즉시 자동 승인됩니다.</div>
            <?php else: ?>
                <div class="empty-state">회사 관리자가 아직 결재선을 지정하지 않았습니다. 템플릿 또는 개인 결재선을 먼저 설정해야 기안 제출이 됩니다.</div>
            <?php endif; ?>
        <?php else: ?>
            <div class="timeline">
                <?php foreach ($approvalLine as $idx => $approver): ?>
                    <div class="timeline-item"><div class="timeline-item-head"><strong><?= e((string) ($idx + 1)) ?>차 결재</strong><span class="badge badge-gray">예정</span></div><p>결재자: <?= e($approver['name']) ?> / <?= e($approver['job_title'] ?: '직급 미설정') ?></p></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="mobile-action-bar">
    <button type="submit" form="docForm" name="submit_action" value="save" class="btn-secondary">임시저장</button>
    <button type="submit" form="docForm" name="submit_action" value="submit" class="btn-primary">기안하기</button>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
