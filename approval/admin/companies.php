<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_super_admin($pdo);
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$companies = get_company_admin_stats($pdo, $keyword);
$pageTitle = '기업 관리';
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><form method="get" class="form-grid"><div class="form-group"><label for="keyword">검색</label><input type="search" id="keyword" name="keyword" value="<?= e($keyword) ?>" placeholder="회사명, 회사코드, 대표자명, 대표전화번호"></div><div class="form-group"><label>&nbsp;</label><button type="submit" class="btn-primary">검색</button></div></form></div>
<div class="card">
<?php if (!$companies): ?><div class="empty-state">가입한 회사가 없습니다.</div><?php else: ?><div class="table-wrap"><table class="table"><thead><tr><th>ID</th><th>회사명</th><th>회사코드</th><th>대표자</th><th>대표전화번호</th><th>이용상태</th><th>플랜</th><th>가입자 수</th><th>문서 수</th><th>결재 진행중</th><th>결재 완료</th><th>바로가기</th></tr></thead><tbody><?php foreach ($companies as $company): ?><tr><td><?= e((string) $company['id']) ?></td><td><?= e($company['company_name']) ?></td><td><strong><?= e($company['company_code']) ?></strong></td><td><?= e($company['owner_name']) ?></td><td><?= e(format_phone($company['owner_phone'])) ?></td><td><span class="<?= e(company_status_badge((string) $company['service_status'])) ?>"><?= e(company_status_label((string) $company['service_status'])) ?></span></td><td><span class="<?= e(plan_badge_class((string) $company['plan_name'])) ?>"><?= e($company['plan_name']) ?></span></td><td><?= e((string) $company['user_count']) ?></td><td><?= e((string) $company['document_count']) ?></td><td><?= e((string) $company['pending_count']) ?></td><td><?= e((string) $company['approved_count']) ?></td><td><div class="btn-row"><a href="<?= e(base_url('admin/users.php?company_id=' . $company['id'])) ?>" class="btn btn-sm btn-outline">가입자</a><a href="<?= e(base_url('documents.php?view=all&company_id=' . $company['id'])) ?>" class="btn btn-sm btn-outline">문서</a><a href="<?= e(base_url('admin/company_settings.php?company_id=' . $company['id'])) ?>" class="btn btn-sm btn-outline">설정</a></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
