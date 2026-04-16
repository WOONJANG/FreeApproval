<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_member_manager($pdo);
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$companyIdFilter = max(0, (int) ($_GET['company_id'] ?? 0));
$conditions = [];
$params = [];
if (!is_super_admin($pageUser)) {
    $conditions[] = 'u.company_id = ?';
    $params[] = (int) $pageUser['company_id'];
} elseif ($companyIdFilter > 0) {
    $conditions[] = 'u.company_id = ?';
    $params[] = $companyIdFilter;
}
if ($keyword !== '') {
    $conditions[] = '(u.name LIKE ? OR u.phone LIKE ? OR u.email LIKE ? OR u.job_title LIKE ? OR c.company_name LIKE ? OR c.company_code LIKE ?)';
    $like = '%' . $keyword . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$sql = '
    SELECT u.*, c.company_name, c.company_code
    FROM `approval_users` u
    LEFT JOIN `approval_companies` c ON c.id = u.company_id
    ' . $where . '
    ORDER BY FIELD(u.role, "super_admin", "admin", "user"), c.company_name ASC, u.level_no ASC, u.id ASC
';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
$companies = is_super_admin($pageUser) ? get_company_options($pdo) : [];
$pageTitle = '회원 관리';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <form method="get" class="form-grid">
        <?php if (is_super_admin($pageUser)): ?>
        <div class="form-group"><label for="company_id">회사</label><select id="company_id" name="company_id"><option value="0">전체 회사</option><?php foreach ($companies as $company): ?><option value="<?= e((string) $company['id']) ?>"<?= $companyIdFilter === (int) $company['id'] ? ' selected' : '' ?>><?= e($company['company_name']) ?> (<?= e($company['company_code']) ?>)</option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <div class="form-group"><label for="keyword">검색</label><input type="search" id="keyword" name="keyword" value="<?= e($keyword) ?>" placeholder="이름, 전화번호, 이메일, 직급명, 회사명"></div>
        <div class="form-group"><label>&nbsp;</label><div class="btn-row"><button type="submit" class="btn-primary">검색</button><a href="<?= e(base_url('actions/export_users.php' . (is_super_admin($pageUser) && $companyIdFilter > 0 ? '?company_id=' . $companyIdFilter : ''))) ?>" class="btn btn-outline">엑셀 다운로드</a></div></div>
    </form>
</div>
<div class="card">
<?php if (!$users): ?><div class="empty-state">회원이 없습니다.</div><?php else: ?>
<div class="table-wrap"><table class="table"><thead><tr><th>ID</th><?php if (is_super_admin($pageUser)): ?><th>회사</th><?php endif; ?><th>이름</th><th>이메일</th><th>전화번호</th><th>레벨</th><th>직급명</th><th>결재선</th><th>권한</th><th>운영권한</th><th>상태</th><th>잠금</th><th>관리</th></tr></thead><tbody>
<?php foreach ($users as $member): $approverCount = ((int) $member['role'] !== 'super_admin' && !empty($member['company_id'])) ? count(get_user_assigned_approvers($pdo, (int) $member['id'])) : 0; ?>
<tr>
<td><?= e((string) $member['id']) ?></td>
<?php if (is_super_admin($pageUser)): ?><td><?= e($member['company_name'] ?: '-') ?><div class="muted"><?= e($member['company_code'] ?: '-') ?></div></td><?php endif; ?>
<td><?= e($member['name']) ?></td>
<td><?= e($member['email'] ?: '-') ?></td>
<td><?= e($member['phone'] ? format_phone($member['phone']) : ($member['login_id'] ?: '-')) ?></td>
<td><?php if ($member['role'] === 'super_admin'): ?>-<?php else: ?>Level <?= e((string) $member['level_no']) ?><?php endif; ?></td>
<td><?= e($member['job_title'] ?: '-') ?></td>
<td><?= e((string) $approverCount) ?>명</td>
<td><?= e($member['role']) ?></td>
<td><?php if ((int) ($member['can_manage_members'] ?? 0) === 1): ?><span class="badge badge-blue">회원관리</span><?php endif; ?><?php if ((int) ($member['can_manage_notices'] ?? 0) === 1): ?><span class="badge badge-amber">공지관리</span><?php endif; ?><?php if ((int) ($member['can_manage_members'] ?? 0) !== 1 && (int) ($member['can_manage_notices'] ?? 0) !== 1): ?>-<?php endif; ?></td>
<td><?= (int) $member['is_active'] ? '사용중' : '비활성' ?></td>
<td><?= !empty($member['locked_until']) && strtotime($member['locked_until']) > time() ? e(format_datetime($member['locked_until'])) : '-' ?></td>
<td><div class="btn-row"><?php if (can_manage_member($pageUser, $member) && (int) $member['id'] !== (int) $pageUser['id']): ?><form action="<?= e(base_url('actions/toggle_user_active.php')) ?>" method="post" style="display:inline;"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= e((string) $member['id']) ?>"><input type="hidden" name="toggle_to" value="<?= (int) $member['is_active'] ? '0' : '1' ?>"><button type="submit" class="btn btn-sm <?= (int) $member['is_active'] ? 'btn-outline' : 'btn-secondary' ?>"><?= (int) $member['is_active'] ? '비활성화' : '활성화' ?></button></form><?php endif; ?><?php if (can_manage_member($pageUser, $member) || ((int) $member['id'] === (int) $pageUser['id'])): ?><a class="btn btn-sm btn-outline" href="<?= e(base_url('admin/user_edit.php?id=' . $member['id'])) ?>">수정</a><?php endif; ?></div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
