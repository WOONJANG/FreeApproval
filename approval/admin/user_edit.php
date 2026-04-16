<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_member_manager($pdo);
$userId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT u.*, c.company_name, c.company_code FROM `approval_users` u LEFT JOIN `approval_companies` c ON c.id = u.company_id WHERE u.id = ? LIMIT 1');
$stmt->execute([$userId]);
$member = $stmt->fetch();
if (!$member) {
    http_response_code(404);
    exit('회원을 찾을 수 없습니다.');
}
if (!can_manage_member($pageUser, $member) && (int) $member['id'] !== (int) $pageUser['id']) {
    http_response_code(403);
    exit('이 회원을 수정할 수 없습니다.');
}
$canAssignDelegation = can_assign_management_permissions($pageUser);
$canEditApprovalLine = $canAssignDelegation && $member['role'] !== 'super_admin' && !empty($member['company_id']);
$selectedApproverIds = $canEditApprovalLine ? get_user_assigned_approver_ids($pdo, $userId) : [];
$approverOptions = $canEditApprovalLine ? get_assignable_approvers($pdo, $userId, (int) $member['company_id']) : [];
$pageTitle = '회원 수정';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
<form action="<?= e(base_url('actions/save_user.php')) ?>" method="post" class="form-grid">
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="user_id" value="<?= e((string) $member['id']) ?>">
<div class="form-group"><label for="name">이름</label><input type="text" id="name" name="name" value="<?= e($member['name']) ?>" required maxlength="50"></div>
<div class="form-group"><label for="email">이메일</label><input type="email" id="email" name="email" value="<?= e($member['email']) ?>" maxlength="120"></div>
<div class="form-group"><label for="phone">전화번호</label><input type="tel" id="phone" name="phone" value="<?= e($member['phone']) ?>" maxlength="20" placeholder="01012345678"></div>
<?php if (!empty($member['login_id'])): ?><div class="form-group"><label for="login_id_display">로그인 아이디</label><input type="text" id="login_id_display" value="<?= e($member['login_id']) ?>" readonly></div><?php endif; ?>
<div class="form-group"><label>회사</label><input type="text" value="<?= e(($member['company_name'] ?: '프로젝트 관리자') . (!empty($member['company_code']) ? ' (' . $member['company_code'] . ')' : '')) ?>" readonly></div>
<?php if ($member['role'] !== 'super_admin'): ?><div class="form-group"><label for="level_no">레벨</label><input type="number" id="level_no" name="level_no" value="<?= e((string) $member['level_no']) ?>" min="1" required></div><?php endif; ?>
<div class="form-group"><label for="job_title">직급명</label><input type="text" id="job_title" name="job_title" value="<?= e($member['job_title']) ?>" maxlength="50"></div>
<?php if (is_super_admin($pageUser)): ?>
<div class="form-group"><label for="role">권한</label><select id="role" name="role" <?= $member['role'] === 'super_admin' ? 'disabled' : '' ?>><option value="user"<?= $member['role'] === 'user' ? ' selected' : '' ?>>user</option><option value="admin"<?= $member['role'] === 'admin' ? ' selected' : '' ?>>admin</option><?php if ($member['role'] === 'super_admin'): ?><option value="super_admin" selected>super_admin</option><?php endif; ?></select><?php if ($member['role'] === 'super_admin'): ?><input type="hidden" name="role" value="super_admin"><?php endif; ?></div>
<?php else: ?>
<div class="form-group"><label>권한</label><input type="text" value="<?= e($member['role']) ?>" readonly><div class="muted">회사 대표계정만 결재 외 운영 권한을 위임할 수 있습니다. 위임 운영자는 회사 내부 관리만 하고 결재 승인/반려는 할 수 없습니다.</div></div>
<?php endif; ?>
<div class="form-group"><label for="new_password">비밀번호 변경</label><input type="password" id="new_password" name="new_password" minlength="8" maxlength="100" placeholder="입력 시 변경"></div>
<div class="form-group full"><?php if ((int) $member['id'] === (int) $pageUser['id']): ?><input type="hidden" name="is_active" value="1"><?php endif; ?><label class="checkbox-inline"><input type="checkbox" name="is_active" value="1" <?= (int) $member['is_active'] ? 'checked' : '' ?> <?= ((int) $member['id'] === (int) $pageUser['id']) ? 'disabled' : '' ?>> 계정 사용중</label><div class="muted">현재 잠금: <?= !empty($member['locked_until']) && strtotime($member['locked_until']) > time() ? e(format_datetime($member['locked_until'])) : '없음' ?></div></div>
<?php if ($member['role'] !== 'super_admin' && $canAssignDelegation && !empty($member['company_id'])): ?>
<div class="form-group full">
    <label>운영 권한 위임</label>
    <label class="checkbox-inline"><input type="checkbox" name="can_manage_members" value="1" <?= (int) ($member['can_manage_members'] ?? 0) === 1 ? 'checked' : '' ?>> 회원관리 권한 (레벨/직급명/활성관리)</label>
    <label class="checkbox-inline"><input type="checkbox" name="can_manage_notices" value="1" <?= (int) ($member['can_manage_notices'] ?? 0) === 1 ? 'checked' : '' ?>> 공지관리 권한</label>
    <div class="muted">회사 대표계정이 회사 내부 운영 권한만 위임합니다. 결재 승인/반려, 휴지통, 결재자 재지정 권한은 포함되지 않습니다.</div>
</div>
<?php endif; ?>
<?php if ($canEditApprovalLine): ?>
<div class="form-group full"><label for="approver_ids">결재 가능 인원 지정 (낮은 레벨 → 높은 레벨 자동 정렬)</label><select id="approver_ids" name="approver_ids[]" multiple size="8"><?php foreach ($approverOptions as $approver): ?><option value="<?= e((string) $approver['id']) ?>"<?= in_array((int) $approver['id'], $selectedApproverIds, true) ? ' selected' : '' ?>><?= e($approver['name']) ?> / <?= e($approver['job_title'] ?: '직급 미설정') ?> / Level <?= e((string) $approver['level_no']) ?></option><?php endforeach; ?></select><div class="muted">같은 회사의 활성 사용자만 선택됩니다. 이 항목은 회사 대표 또는 최상위 관리자만 관리할 수 있습니다.</div></div>
<?php endif; ?>
<div class="form-group full"><div class="btn-row"><button type="submit" class="btn-primary">저장</button><a href="<?= e(base_url('admin/users.php')) ?>" class="btn-secondary">목록</a></div></div>
</form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
