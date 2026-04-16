<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageUser = require_login($pdo);
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$companyIdFilter = is_super_admin($pageUser) ? max(0, (int) ($_GET['company_id'] ?? 0)) : 0;
$globalNotices = get_global_notices($pdo, $keyword);
$companyNotices = get_company_notice_list($pdo, $pageUser, $companyIdFilter, $keyword);
$companies = is_super_admin($pageUser) ? get_company_options($pdo) : [];
$pageTitle = '공지사항';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <form method="get" class="form-grid">
        <?php if (is_super_admin($pageUser)): ?>
        <div class="form-group">
            <label for="company_id">회사</label>
            <select id="company_id" name="company_id">
                <option value="0">전체 회사</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= e((string) $company['id']) ?>"<?= $companyIdFilter === (int) $company['id'] ? ' selected' : '' ?>><?= e($company['company_name']) ?> (<?= e($company['company_code']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
            <label for="keyword">검색</label>
            <input type="search" id="keyword" name="keyword" value="<?= e($keyword) ?>" placeholder="제목, 내용, 작성자">
        </div>
        <div class="form-group">
            <label>&nbsp;</label>
            <div class="btn-row">
                <button type="submit" class="btn-primary">검색</button>
                <a href="<?= e(base_url('notices.php')) ?>" class="btn-secondary">초기화</a>
                <?php if (can_manage_notices($pageUser)): ?><a href="<?= e(base_url('notice_form.php')) ?>" class="btn-outline">글쓰기</a><?php endif; ?>
            </div>
        </div>
        <div class="form-group full">
            <div class="muted"><?= e(notice_scope_summary($pageUser, $companyIdFilter)) ?></div>
        </div>
    </form>
</div>

<section class="card" style="margin-top:18px;">
    <div class="section-head">
        <h3>프로젝트 전체 공지</h3>
        <span class="badge badge-blue"><?= e((string) count($globalNotices)) ?>건</span>
    </div>
    <?php if (!$globalNotices): ?>
        <div class="empty-state">등록된 프로젝트 전체 공지가 없습니다.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>구분</th><th>제목</th><th>작성자</th><th>조회수</th><th>등록일</th><th>보기</th></tr></thead>
                <tbody>
                <?php foreach ($globalNotices as $notice): ?>
                    <tr>
                        <td><span class="badge badge-blue">전체 공지</span></td>
                        <td><strong><?= e($notice['title']) ?></strong><div class="muted"><?= e(mb_strimwidth(strip_tags((string) $notice['content']), 0, 100, '...')) ?></div></td>
                        <td><?= e($notice['writer_name']) ?><div class="muted"><?= e($notice['writer_job_title'] ?: '프로젝트 관리자') ?></div></td>
                        <td><?= e((string) $notice['view_count']) ?></td>
                        <td><?= e(format_datetime($notice['created_at'])) ?></td>
                        <td><a class="btn btn-sm btn-outline" href="<?= e(base_url('notice_view.php?id=' . $notice['id'])) ?>">보기</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mobile-list">
            <?php foreach ($globalNotices as $notice): ?>
                <div class="mobile-card">
                    <span class="badge badge-blue">전체 공지</span>
                    <h3><?= e($notice['title']) ?></h3>
                    <div class="mobile-meta">
                        <span>작성자: <?= e($notice['writer_name']) ?> / <?= e($notice['writer_job_title'] ?: '프로젝트 관리자') ?></span>
                        <span>조회수: <?= e((string) $notice['view_count']) ?></span>
                        <span>등록일: <?= e(format_datetime($notice['created_at'])) ?></span>
                    </div>
                    <div class="btn-row" style="margin-top:12px;"><a class="btn btn-sm btn-outline" href="<?= e(base_url('notice_view.php?id=' . $notice['id'])) ?>">보기</a></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card" style="margin-top:18px;">
    <div class="section-head">
        <h3><?= is_super_admin($pageUser) ? '기업별 공지사항' : '우리 회사 공지사항' ?></h3>
        <span class="badge badge-gray"><?= e((string) count($companyNotices)) ?>건</span>
    </div>
    <?php if (!$companyNotices): ?>
        <div class="empty-state">등록된 회사 공지사항이 없습니다.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>구분</th><?php if (is_super_admin($pageUser)): ?><th>회사</th><?php endif; ?><th>제목</th><th>작성자</th><th>조회수</th><th>등록일</th><th>보기</th></tr></thead>
                <tbody>
                <?php foreach ($companyNotices as $notice): ?>
                    <tr>
                        <td><span class="<?= e((int) $notice['is_notice'] ? 'badge badge-amber' : ((int) ($notice['is_banner'] ?? 0) === 1 ? 'badge badge-blue' : ((int) ($notice['is_popup'] ?? 0) === 1 ? 'badge badge-red' : 'badge badge-gray'))) ?>"><?= e(notice_type_label($notice)) ?></span></td>
                        <?php if (is_super_admin($pageUser)): ?><td><strong><?= e($notice['company_name']) ?></strong><div class="muted"><?= e($notice['company_code']) ?></div></td><?php endif; ?>
                        <td><strong><?= e($notice['title']) ?></strong><div class="muted"><?= e(mb_strimwidth(strip_tags((string) $notice['content']), 0, 100, '...')) ?></div></td>
                        <td><?= e($notice['writer_name']) ?><div class="muted"><?= e($notice['writer_job_title'] ?: '직급 미설정') ?></div></td>
                        <td><?= e((string) $notice['view_count']) ?></td>
                        <td><?= e(format_datetime($notice['created_at'])) ?></td>
                        <td><a class="btn btn-sm btn-outline" href="<?= e(base_url('notice_view.php?id=' . $notice['id'])) ?>">보기</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mobile-list">
            <?php foreach ($companyNotices as $notice): ?>
                <div class="mobile-card">
                    <span class="<?= e((int) $notice['is_notice'] ? 'badge badge-amber' : 'badge badge-gray') ?>"><?= e((int) $notice['is_notice'] ? '공지' : '일반') ?></span>
                    <h3><?= e($notice['title']) ?></h3>
                    <div class="mobile-meta">
                        <?php if (is_super_admin($pageUser)): ?><span>회사: <?= e($notice['company_name']) ?> (<?= e($notice['company_code']) ?>)</span><?php endif; ?>
                        <span>작성자: <?= e($notice['writer_name']) ?> / <?= e($notice['writer_job_title'] ?: '직급 미설정') ?></span>
                        <span>조회수: <?= e((string) $notice['view_count']) ?></span>
                        <span>등록일: <?= e(format_datetime($notice['created_at'])) ?></span>
                    </div>
                    <div class="btn-row" style="margin-top:12px;"><a class="btn btn-sm btn-outline" href="<?= e(base_url('notice_view.php?id=' . $notice['id'])) ?>">보기</a></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
