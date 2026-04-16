<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageUser = require_login($pdo);
$scope = $_GET['view'] ?? 'all';
$allowedScopes = ['all', 'my', 'waiting', 'completed', 'rejected', 'trash'];
if (!in_array($scope, $allowedScopes, true)) {
    $scope = 'all';
}
if ($scope === 'trash' && !is_admin($pageUser)) {
    $scope = 'all';
}
$filters = get_document_filters_from_request();
$documents = get_documents_for_scope($pdo, $pageUser, $scope, $filters);
$counts = get_scope_counts($pdo, $pageUser, $filters);
$companies = is_super_admin($pageUser) ? get_company_options($pdo) : [];
$categoryCompanyId = is_super_admin($pageUser) && (int) $filters['company_id'] > 0 ? (int) $filters['company_id'] : (int) ($pageUser['company_id'] ?? 0);
$categories = $categoryCompanyId > 0 ? get_company_categories($pdo, $categoryCompanyId, true) : [];
$pageTitle = scope_label($scope);
$allowBulkApproval = (int) ($pageUser['allow_bulk_approval'] ?? 0) === 1;
require __DIR__ . '/includes/header.php';
?>
<div class="grid-4">
    <div class="stat-box"><h3>전체 문서</h3><strong><?= e((string) $counts['all']) ?></strong></div>
    <div class="stat-box"><h3>내 기안서</h3><strong><?= e((string) $counts['my']) ?></strong></div>
    <div class="stat-box"><h3>내 결재 대기</h3><strong><?= e((string) $counts['waiting']) ?></strong></div>
    <div class="stat-box"><h3>결재 완료</h3><strong><?= e((string) $counts['completed']) ?></strong></div>
</div>

<div class="card" style="margin-top:18px;">
    <form method="get" action="<?= e(base_url('documents.php')) ?>" class="form-grid">
        <input type="hidden" name="view" value="<?= e($scope) ?>">
        <div class="form-group">
            <label for="keyword">검색</label>
            <input type="search" id="keyword" name="keyword" value="<?= e($filters['keyword']) ?>" placeholder="문서번호, 제목, 내용, 작성자, 회사명, 분류">
        </div>
        <div class="form-group">
            <label for="status">상태</label>
            <select id="status" name="status">
                <option value="">전체</option>
                <option value="draft"<?= $filters['status'] === 'draft' ? ' selected' : '' ?>>임시저장</option>
                <option value="submitted"<?= $filters['status'] === 'submitted' ? ' selected' : '' ?>>결재 진행중</option>
                <option value="approved"<?= $filters['status'] === 'approved' ? ' selected' : '' ?>>결재 완료</option>
                <option value="rejected"<?= $filters['status'] === 'rejected' ? ' selected' : '' ?>>반려</option>
                <option value="cancelled"<?= $filters['status'] === 'cancelled' ? ' selected' : '' ?>>기안취소</option>
                <?php if (is_admin($pageUser)): ?><option value="deleted"<?= $filters['status'] === 'deleted' ? ' selected' : '' ?>>휴지통</option><?php endif; ?>
            </select>
        </div>
        <?php if (is_super_admin($pageUser)): ?>
        <div class="form-group">
            <label for="company_id">회사</label>
            <select id="company_id" name="company_id">
                <option value="0">전체 회사</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= e((string) $company['id']) ?>"<?= (int) $filters['company_id'] === (int) $company['id'] ? ' selected' : '' ?>><?= e($company['company_name']) ?> (<?= e($company['company_code']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
            <label for="category_id">문서 분류</label>
            <select id="category_id" name="category_id">
                <option value="0">전체 분류</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']) ?>"<?= (int) $filters['category_id'] === (int) $category['id'] ? ' selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label for="date_from">시작일</label><input type="date" id="date_from" name="date_from" value="<?= e($filters['date_from']) ?>"></div>
        <div class="form-group"><label for="date_to">종료일</label><input type="date" id="date_to" name="date_to" value="<?= e($filters['date_to']) ?>"></div>
        <div class="form-group full">
            <div class="btn-row">
                <button type="submit" class="btn-primary">검색</button>
                <a href="<?= e(base_url('documents.php?view=' . $scope)) ?>" class="btn-secondary">초기화</a>
                <a href="<?= e(base_url('document_create.php')) ?>" class="btn-secondary">새 기안서 작성</a>
                <a href="<?= e(base_url('actions/export_documents.php?view=' . urlencode($scope) . '&keyword=' . urlencode($filters['keyword']) . '&status=' . urlencode($filters['status']) . '&date_from=' . urlencode($filters['date_from']) . '&date_to=' . urlencode($filters['date_to']) . '&company_id=' . (int) $filters['company_id'] . '&category_id=' . (int) $filters['category_id'])) ?>" class="btn btn-outline">엑셀 다운로드</a>
            </div>
        </div>
    </form>
    <?php if ($scope === 'trash' && is_admin($pageUser)): ?>
        <form action="<?= e(base_url('actions/empty_trash.php')) ?>" method="post" class="inline-form" onsubmit="return confirm('휴지통을 비우면 복구할 수 없습니다. 계속할까요?');" style="margin-top:12px;">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="company_id" value="<?= e((string) ((int) $filters['company_id'])) ?>">
            <button type="submit" class="btn btn-danger">휴지통 비우기</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($scope === 'waiting' && $allowBulkApproval && $documents): ?>
<form action="<?= e(base_url('actions/bulk_approve.php')) ?>" method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<?php endif; ?>
<div class="card">
    <?php if (!$documents): ?>
        <div class="empty-state">표시할 문서가 없습니다.</div>
    <?php else: ?>
        <?php if ($scope === 'waiting' && $allowBulkApproval): ?>
            <div class="btn-row" style="margin-bottom:12px; justify-content:space-between;">
                <div class="muted">회사 설정에서 일괄 승인이 허용된 상태입니다. 현재 내 차례인 문서만 선택됩니다.</div>
                <button type="submit" class="btn-primary btn-sm">선택 문서 일괄 승인</button>
            </div>
        <?php endif; ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <?php if ($scope === 'waiting' && $allowBulkApproval): ?><th><input type="checkbox" data-check-all="bulkDocs"></th><?php endif; ?>
                        <th>상태</th>
                        <?php if (is_super_admin($pageUser)): ?><th>회사</th><?php endif; ?>
                        <th>문서번호</th>
                        <th>제목</th>
                        <th>분류</th>
                        <th>작성자</th>
                        <th>현재 단계</th>
                        <th>결재 대기</th>
                        <th>첨부</th>
                        <th>버전</th>
                        <th>작성일</th>
                        <th>보기</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $document): ?>
                        <tr>
                            <?php if ($scope === 'waiting' && $allowBulkApproval): ?><td><input type="checkbox" name="document_ids[]" value="<?= e((string) $document['id']) ?>" class="bulkDocs"></td><?php endif; ?>
                            <td><span class="<?= e(status_badge_class($document['status'])) ?>"><?= e(document_status_label($document['status'])) ?></span></td>
                            <?php if (is_super_admin($pageUser)): ?><td><strong><?= e($document['company_name']) ?></strong><div class="muted"><?= e($document['company_code']) ?></div></td><?php endif; ?>
                            <td><?= e($document['doc_no'] ?: '-') ?></td>
                            <td><strong><?= e($document['title']) ?></strong><div class="muted"><?= e(mb_strimwidth(strip_tags((string) $document['content']), 0, 80, '...')) ?></div></td>
                            <td><?= e($document['category_name'] ?: '-') ?></td>
                            <td><strong><?= e($document['writer_name']) ?></strong><div class="muted"><?= e($document['writer_job_title'] ?: '직급 미설정') ?></div></td>
                            <td><?= e(current_step_text($document)) ?></td>
                            <td><?= e(waiting_days_text($document['submitted_at'])) ?></td>
                            <td><?= e((string) $document['attachment_count']) ?>개</td>
                            <td>v<?= e((string) $document['version_no']) ?></td>
                            <td><?= e(format_datetime($document['created_at'])) ?></td>
                            <td><a href="<?= e(base_url('document_view.php?id=' . $document['id'])) ?>" class="btn btn-sm btn-outline">상세</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mobile-list">
            <?php foreach ($documents as $document): ?>
                <div class="mobile-card">
                    <span class="<?= e(status_badge_class($document['status'])) ?>"><?= e(document_status_label($document['status'])) ?></span>
                    <h3><?= e($document['doc_no'] ?: '-') ?> · <?= e($document['title']) ?></h3>
                    <div class="mobile-meta">
                        <?php if (is_super_admin($pageUser)): ?><span>회사: <?= e($document['company_name']) ?> (<?= e($document['company_code']) ?>)</span><?php endif; ?>
                        <span>분류: <?= e($document['category_name'] ?: '-') ?></span>
                        <span>작성자: <?= e($document['writer_name']) ?> / <?= e($document['writer_job_title'] ?: '직급 미설정') ?></span>
                        <span>현재 단계: <?= e(current_step_text($document)) ?></span>
                        <span>결재 대기: <?= e(waiting_days_text($document['submitted_at'])) ?></span>
                        <span>첨부: <?= e((string) $document['attachment_count']) ?>개</span>
                        <span>버전: v<?= e((string) $document['version_no']) ?></span>
                        <span>작성일: <?= e(format_datetime($document['created_at'])) ?></span>
                    </div>
                    <div class="btn-row" style="margin-top:12px;"><a href="<?= e(base_url('document_view.php?id=' . $document['id'])) ?>" class="btn btn-sm btn-outline">상세 보기</a></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php if ($scope === 'waiting' && $allowBulkApproval && $documents): ?></form><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
