<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_company_settings_manager($pdo);
$requestedCompanyId = max(0, (int) ($_GET['company_id'] ?? 0));
$company = get_company_settings($pdo, $pageUser, $requestedCompanyId);
if (!$company) {
    http_response_code(404);
    exit('회사 정보를 찾을 수 없습니다.');
}
$companyId = (int) $company['id'];
$categories = get_company_categories($pdo, $companyId);
$templates = get_company_templates($pdo, $companyId);
$approverOptions = get_assignable_approvers($pdo, 0, $companyId);
$readerOptions = get_company_members_for_reader($pdo, $companyId);
$activityLogs = get_company_activity_logs($pdo, $pageUser, 30, $companyId);
$pageTitle = '회사 설정';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid-2">
    <section class="card">
        <div class="section-head"><h3>회사 기본 설정</h3><?php if (is_super_admin($pageUser)): ?><span class="badge badge-blue">최상위 관리자 수정 가능</span><?php endif; ?></div>
        <form action="<?= e(base_url('actions/save_company_settings.php')) ?>" method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="company_id" value="<?= e((string) $companyId) ?>">
            <div class="form-group"><label>회사명</label><input type="text" value="<?= e($company['company_name']) ?>" readonly></div>
            <div class="form-group"><label>회사코드</label><input type="text" value="<?= e($company['company_code']) ?>" readonly></div>
            <div class="form-group"><label>대표자명</label><input type="text" value="<?= e($company['owner_name']) ?>" readonly></div>
            <div class="form-group"><label>대표 전화번호</label><input type="text" value="<?= e(format_phone($company['owner_phone'])) ?>" readonly></div>
            <div class="form-group"><label for="service_status">이용 상태</label><select id="service_status" name="service_status" <?= is_super_admin($pageUser) ? '' : 'disabled' ?>><option value="trial"<?= $company['service_status']==='trial'?' selected':'' ?>>체험중</option><option value="active"<?= $company['service_status']==='active'?' selected':'' ?>>사용중</option><option value="suspended"<?= $company['service_status']==='suspended'?' selected':'' ?>>정지</option><option value="expired"<?= $company['service_status']==='expired'?' selected':'' ?>>만료</option></select><?php if (!is_super_admin($pageUser)): ?><input type="hidden" name="service_status" value="<?= e($company['service_status']) ?>"><?php endif; ?></div>
            <div class="form-group"><label for="plan_name">플랜</label><select id="plan_name" name="plan_name" <?= is_super_admin($pageUser) ? '' : 'disabled' ?>><option value="Free"<?= $company['plan_name']==='Free'?' selected':'' ?>>Free</option><option value="Standard"<?= $company['plan_name']==='Standard'?' selected':'' ?>>Standard</option><option value="Premium"<?= $company['plan_name']==='Premium'?' selected':'' ?>>Premium</option></select><?php if (!is_super_admin($pageUser)): ?><input type="hidden" name="plan_name" value="<?= e($company['plan_name']) ?>"><?php endif; ?></div>
            <div class="form-group"><label for="member_limit">회원 수 제한</label><input type="number" id="member_limit" name="member_limit" value="<?= e((string) ($company['member_limit'] ?? '')) ?>" min="0" <?= is_super_admin($pageUser) ? '' : 'readonly' ?>></div>
            <div class="form-group"><label for="document_limit">문서 수 제한</label><input type="number" id="document_limit" name="document_limit" value="<?= e((string) ($company['document_limit'] ?? '')) ?>" min="0" <?= is_super_admin($pageUser) ? '' : 'readonly' ?>></div>
            <div class="form-group"><label for="trial_ends_at">체험 종료일</label><input type="datetime-local" id="trial_ends_at" name="trial_ends_at" value="<?= !empty($company['trial_ends_at']) ? e(date('Y-m-d\TH:i', strtotime($company['trial_ends_at']))) : '' ?>" <?= is_super_admin($pageUser) ? '' : 'readonly' ?>></div>
            <div class="form-group"><label for="adfree_until">광고 제거 만료일</label><input type="datetime-local" id="adfree_until" name="adfree_until" value="<?= !empty($company['adfree_until']) ? e(date('Y-m-d\TH:i', strtotime($company['adfree_until']))) : '' ?>" <?= is_super_admin($pageUser) ? '' : 'readonly' ?>></div>
            <div class="form-group"><label for="locale">기본 언어</label><select id="locale" name="locale"><option value="ko"<?= $company['locale']==='ko'?' selected':'' ?>>한국어</option><option value="en"<?= $company['locale']==='en'?' selected':'' ?>>English</option></select></div>
            <div class="form-group full"><label class="checkbox-inline"><input type="checkbox" name="allow_bulk_approval" value="1" <?= (int) $company['allow_bulk_approval']===1?'checked':'' ?>> 일괄 승인 허용</label><div class="muted">허용 시 결재 대기 목록에서 여러 문서를 한 번에 승인할 수 있습니다.</div></div>
            <div class="form-group full"><div class="btn-row"><button type="submit" class="btn-primary">회사 설정 저장</button><?php if (is_super_admin($pageUser)): ?><a href="<?= e(base_url('admin/companies.php')) ?>" class="btn btn-outline">기업 관리</a><?php endif; ?></div></div>
        </form>
    </section>
    <section class="card">
        <div class="section-head"><h3>최근 활동 로그</h3><a href="<?= e(base_url('actions/export_users.php?company_id=' . $companyId)) ?>" class="btn btn-sm btn-outline">가입자 엑셀</a></div>
        <?php if (!$activityLogs): ?><div class="empty-state">활동 로그가 없습니다.</div><?php else: ?><div class="timeline compact"><?php foreach ($activityLogs as $log): ?><div class="timeline-item"><div class="timeline-item-head"><strong><?= e($log['action_key']) ?></strong><span class="badge badge-gray"><?= e(format_datetime($log['created_at'])) ?></span></div><p><?= e($log['description']) ?></p><p><?= e($log['user_name'] ?: '시스템') ?><?= $log['ip_address'] ? ' · IP ' . e($log['ip_address']) : '' ?></p></div><?php endforeach; ?></div><?php endif; ?>
    </section>
</div>

<div class="grid-2" style="margin-top:18px;">
    <section class="card">
        <div class="section-head"><h3>문서 분류 관리</h3></div>
        <form action="<?= e(base_url('actions/save_category.php')) ?>" method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="company_id" value="<?= e((string) $companyId) ?>">
            <div class="form-group"><label for="category_name">분류명</label><input type="text" id="category_name" name="name" maxlength="80" required></div>
            <div class="form-group"><label for="sort_order">정렬순서</label><input type="number" id="sort_order" name="sort_order" value="1" min="1"></div>
            <div class="form-group"><label class="checkbox-inline"><input type="checkbox" name="is_active" value="1" checked> 사용</label></div>
            <div class="form-group full"><button type="submit" class="btn-primary">분류 추가</button></div>
        </form>
        <div class="timeline compact" style="margin-top:14px;">
            <?php foreach ($categories as $category): ?>
                <div class="timeline-item">
                    <form action="<?= e(base_url('actions/save_category.php')) ?>" method="post" class="form-grid">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="company_id" value="<?= e((string) $companyId) ?>">
                        <input type="hidden" name="category_id" value="<?= e((string) $category['id']) ?>">
                        <div class="form-group"><label>분류명</label><input type="text" name="name" value="<?= e($category['name']) ?>" maxlength="80" required></div>
                        <div class="form-group"><label>정렬순서</label><input type="number" name="sort_order" value="<?= e((string) $category['sort_order']) ?>" min="1"></div>
                        <div class="form-group"><label class="checkbox-inline"><input type="checkbox" name="is_active" value="1" <?= (int) $category['is_active'] === 1 ? 'checked' : '' ?>> 활성화</label></div>
                        <div class="form-group full"><div class="btn-row"><button type="submit" class="btn btn-sm btn-primary">수정 저장</button></div></div>
                    </form>
                    <form action="<?= e(base_url('actions/delete_category.php')) ?>" method="post" class="inline-form" onsubmit="return confirm('이 분류를 삭제할까요? 사용 중인 문서/템플릿이 있으면 삭제되지 않습니다.');" style="margin-top:8px;">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="category_id" value="<?= e((string) $category['id']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline">삭제</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!$categories): ?><div class="empty-state">등록된 문서 분류가 없습니다.</div><?php endif; ?>
        </div>
    </section>
    <section class="card">
        <div class="section-head"><h3>결재선 템플릿</h3></div>
        <form action="<?= e(base_url('actions/save_template.php')) ?>" method="post" class="form-grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="company_id" value="<?= e((string) $companyId) ?>">
            <div class="form-group"><label for="template_name">템플릿명</label><input type="text" id="template_name" name="name" required maxlength="100"></div>
            <div class="form-group"><label for="template_category_id">기본 문서분류</label><select id="template_category_id" name="category_id"><option value="0">선택 안 함</option><?php foreach ($categories as $category): ?><option value="<?= e((string) $category['id']) ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group full"><label for="template_description">설명</label><input type="text" id="template_description" name="description" maxlength="255"></div>
            <div class="form-group"><label for="template_title_template">양식 제목</label><input type="text" id="template_title_template" name="title_template" maxlength="150" placeholder="예: [검수요청] "></div>
            <div class="form-group full"><label for="template_content_template">양식 본문</label><textarea id="template_content_template" name="content_template" placeholder="기안 작성 시 기본으로 들어갈 본문 양식을 입력하세요."></textarea></div>
            <div class="form-group full"><label for="template_approver_ids">결재 순서</label><select id="template_approver_ids" name="approver_ids[]" multiple size="7"><?php foreach ($approverOptions as $approver): ?><option value="<?= e((string) $approver['id']) ?>"><?= e($approver['name']) ?> / <?= e($approver['job_title'] ?: '직급 미설정') ?> / Level <?= e((string) $approver['level_no']) ?></option><?php endforeach; ?></select><div class="muted">템플릿에 기안자보다 낮은 레벨은 자동 제외됩니다. 기안자가 마지막 결재자로 남는 경우 그 마지막 단계는 자동 승인됩니다.</div></div>
            <div class="form-group full"><label for="template_reader_ids">참조자 / 열람자 기본값</label><select id="template_reader_ids" name="reader_ids[]" multiple size="6"><?php foreach ($readerOptions as $reader): ?><option value="<?= e((string) $reader['id']) ?>"><?= e($reader['name']) ?> / <?= e($reader['job_title'] ?: '직급 미설정') ?></option><?php endforeach; ?></select><div class="muted">이 템플릿으로 기안할 때 기본으로 선택될 참조자/열람자입니다.</div></div>
            <div class="form-group"><label class="checkbox-inline"><input type="checkbox" name="is_active" value="1" checked> 활성화</label></div>
            <div class="form-group full"><button type="submit" class="btn-primary">템플릿 저장</button></div>
        </form>
        <div class="timeline compact" style="margin-top:14px;">
            <?php foreach ($templates as $template): $steps = get_template_steps($pdo, (int) $template['id']); $templateReaders = get_template_readers($pdo, (int) $template['id']); $templateApproverIds = get_template_approver_ids($pdo, (int) $template['id']); $templateReaderIds = get_template_reader_ids($pdo, (int) $template['id']); ?>
                <div class="timeline-item">
                    <div class="timeline-item-head"><strong><?= e($template['name']) ?></strong><span class="badge <?= (int) $template['is_active'] === 1 ? 'badge-green' : 'badge-gray' ?>"><?= (int) $template['is_active'] === 1 ? '활성' : '비활성' ?></span><span class="badge badge-blue"><?= e((string) $template['step_count']) ?>단계</span><span class="badge badge-gray">참조 <?= e((string) $template['reader_count']) ?>명</span><span class="badge badge-gray">사용 <?= e((string) ($template['usage_count'] ?? 0)) ?>건</span></div>
                    <form action="<?= e(base_url('actions/save_template.php')) ?>" method="post" class="form-grid" style="margin-top:10px;">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="company_id" value="<?= e((string) $companyId) ?>">
                        <input type="hidden" name="template_id" value="<?= e((string) $template['id']) ?>">
                        <div class="form-group"><label>템플릿명</label><input type="text" name="name" value="<?= e($template['name']) ?>" required maxlength="100"></div>
                        <div class="form-group"><label>기본 문서분류</label><select name="category_id"><option value="0">선택 안 함</option><?php foreach ($categories as $category): ?><option value="<?= e((string) $category['id']) ?>"<?= (int) ($template['category_id'] ?? 0) === (int) $category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group full"><label>설명</label><input type="text" name="description" value="<?= e($template['description'] ?? '') ?>" maxlength="255"></div>
                        <div class="form-group"><label>양식 제목</label><input type="text" name="title_template" value="<?= e($template['title_template'] ?? '') ?>" maxlength="150"></div>
                        <div class="form-group full"><label>양식 본문</label><textarea name="content_template"><?= e($template['content_template'] ?? '') ?></textarea></div>
                        <div class="form-group full"><label>결재 순서</label><select name="approver_ids[]" multiple size="7"><?php foreach ($approverOptions as $approver): ?><option value="<?= e((string) $approver['id']) ?>"<?= in_array((int) $approver['id'], $templateApproverIds, true) ? ' selected' : '' ?>><?= e($approver['name']) ?> / <?= e($approver['job_title'] ?: '직급 미설정') ?> / Level <?= e((string) $approver['level_no']) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group full"><label>참조자 / 열람자 기본값</label><select name="reader_ids[]" multiple size="6"><?php foreach ($readerOptions as $reader): ?><option value="<?= e((string) $reader['id']) ?>"<?= in_array((int) $reader['id'], $templateReaderIds, true) ? ' selected' : '' ?>><?= e($reader['name']) ?> / <?= e($reader['job_title'] ?: '직급 미설정') ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label class="checkbox-inline"><input type="checkbox" name="is_active" value="1" <?= (int) $template['is_active'] === 1 ? 'checked' : '' ?>> 활성화</label></div>
                        <div class="form-group full"><div class="btn-row"><button type="submit" class="btn btn-sm btn-primary">수정 저장</button></div></div>
                    </form>
                    <p>결재선: <?php foreach ($steps as $step): ?><span class="badge badge-gray"><?= e((string) $step['step_no']) ?>차 <?= e($step['name']) ?></span> <?php endforeach; ?></p>
                    <p>참조/열람: <?php if ($templateReaders): ?><?php foreach ($templateReaders as $reader): ?><span class="badge badge-gray"><?= e($reader['name']) ?></span> <?php endforeach; ?><?php else: ?>-<?php endif; ?></p>
                    <form action="<?= e(base_url('actions/delete_template.php')) ?>" method="post" class="inline-form" onsubmit="return confirm('이 템플릿을 삭제할까요? 사용 중인 문서가 있으면 삭제되지 않습니다.');" style="margin-top:8px;"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="template_id" value="<?= e((string) $template['id']) ?>"><button type="submit" class="btn btn-sm btn-outline">삭제</button></form>
                </div>
            <?php endforeach; ?>
            <?php if (!$templates): ?><div class="empty-state">등록된 결재선 템플릿이 없습니다.</div><?php endif; ?>
        </div>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
