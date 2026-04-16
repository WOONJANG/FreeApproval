<?php
declare(strict_types=1);

function notice_type_label(array $notice): string
{
    if ((int) ($notice['is_global'] ?? 0) === 1) {
        return '전체 공지';
    }
    if ((int) ($notice['is_banner'] ?? 0) === 1) {
        return '배너';
    }
    if ((int) ($notice['is_popup'] ?? 0) === 1) {
        return '팝업';
    }
    if ((int) ($notice['is_notice'] ?? 0) === 1) {
        return '공지';
    }
    return '일반';
}

function can_view_notice(array $user, array $notice): bool
{
    if (is_super_admin($user)) {
        return true;
    }
    if ((int) ($notice['is_global'] ?? 0) === 1) {
        return true;
    }
    return !empty($user['company_id']) && (int) $user['company_id'] === (int) ($notice['company_id'] ?? 0);
}

function can_manage_notice(array $user, array $notice): bool
{
    if (is_super_admin($user)) {
        return true;
    }
    if (!can_manage_notices($user)) {
        return false;
    }
    return !empty($user['company_id']) && (int) $user['company_id'] === (int) ($notice['company_id'] ?? 0) && (int) ($notice['is_global'] ?? 0) === 0;
}

function get_notice_by_id(PDO $pdo, int $noticeId): ?array
{
    $stmt = $pdo->prepare('
        SELECT n.*, u.name AS writer_name, u.job_title AS writer_job_title, u.role AS writer_role,
               c.company_name, c.company_code
        FROM `approval_notices` n
        INNER JOIN `approval_users` u ON u.id = n.writer_user_id
        LEFT JOIN `approval_companies` c ON c.id = n.company_id
        WHERE n.id = ?
        LIMIT 1
    ');
    $stmt->execute([$noticeId]);
    $notice = $stmt->fetch();
    return $notice ?: null;
}

function increment_notice_view_count(PDO $pdo, int $noticeId): void
{
    $stmt = $pdo->prepare('UPDATE `approval_notices` SET view_count = view_count + 1 WHERE id = ?');
    $stmt->execute([$noticeId]);
}

function get_global_notices(PDO $pdo, string $keyword = ''): array
{
    $sql = '
        SELECT n.*, u.name AS writer_name, u.job_title AS writer_job_title, u.role AS writer_role,
               c.company_name, c.company_code
        FROM `approval_notices` n
        INNER JOIN `approval_users` u ON u.id = n.writer_user_id
        LEFT JOIN `approval_companies` c ON c.id = n.company_id
        WHERE n.is_global = 1
    ';
    $params = [];
    if ($keyword !== '') {
        $sql .= ' AND (n.title LIKE ? OR n.content LIKE ? OR u.name LIKE ?)';
        $like = '%' . $keyword . '%';
        $params = [$like, $like, $like];
    }
    $sql .= ' ORDER BY n.is_global DESC, n.is_notice DESC, n.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_company_notice_list(PDO $pdo, array $user, int $companyFilter = 0, string $keyword = ''): array
{
    $conditions = ['n.is_global = 0'];
    $params = [];

    if (is_super_admin($user)) {
        if ($companyFilter > 0) {
            $conditions[] = 'n.company_id = ?';
            $params[] = $companyFilter;
        }
    } else {
        $conditions[] = 'n.company_id = ?';
        $params[] = (int) $user['company_id'];
    }

    if ($keyword !== '') {
        $conditions[] = '(n.title LIKE ? OR n.content LIKE ? OR u.name LIKE ?)';
        $like = '%' . $keyword . '%';
        array_push($params, $like, $like, $like);
    }

    $sql = '
        SELECT n.*, u.name AS writer_name, u.job_title AS writer_job_title, u.role AS writer_role,
               c.company_name, c.company_code
        FROM `approval_notices` n
        INNER JOIN `approval_users` u ON u.id = n.writer_user_id
        LEFT JOIN `approval_companies` c ON c.id = n.company_id
        WHERE ' . implode(' AND ', $conditions) . '
        ORDER BY n.is_global DESC, n.is_notice DESC, n.id DESC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_recent_visible_notices(PDO $pdo, array $user, int $limit = 5): array
{
    $global = get_global_notices($pdo);
    $company = get_company_notice_list($pdo, $user);
    $merged = array_merge(array_slice($global, 0, $limit), array_slice($company, 0, $limit));
    usort($merged, static fn(array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
    return array_slice($merged, 0, $limit);
}

function notice_scope_summary(array $user, int $companyFilter = 0): string
{
    if (is_super_admin($user)) {
        return $companyFilter > 0 ? '선택한 회사 공지와 프로젝트 전체 공지를 함께 봅니다.' : '전체 회사 공지와 프로젝트 전체 공지를 함께 봅니다.';
    }
    return '프로젝트 전체 공지와 내 회사 공지를 함께 봅니다.';
}
