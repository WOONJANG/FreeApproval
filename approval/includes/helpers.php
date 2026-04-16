<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function app_timezone_init(): void
{
    date_default_timezone_set(APP_TIMEZONE);
}

function now_string(): string
{
    return date('Y-m-d H:i:s');
}

function base_url(string $path = ''): string
{
    $base = rtrim(BASE_URL, '/');
    $path = ltrim($path, '/');
    if ($base === '') {
        return $path === '' ? '/' : '/' . $path;
    }
    return $path === '' ? $base : $base . '/' . $path;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function redirect_back(string $fallback = 'documents.php?view=all'): void
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer !== '') {
        header('Location: ' . $referer);
        exit;
    }
    redirect_to($fallback);
}

function set_flash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function get_flash(string $key): ?string
{
    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }
    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $message;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf_or_fail(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('잘못된 요청입니다. 새로고침 후 다시 시도하세요.');
    }
}

function current_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return strtok($uri, '?') ?: '/';
}

function is_active_menu(string $needle): bool
{
    return str_contains(current_path(), $needle);
}

function normalize_phone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function normalize_email(?string $email): string
{
    return trim(mb_strtolower((string) $email));
}

function normalize_company_code(string $code): string
{
    return strtoupper(preg_replace('/[^A-Z0-9]/', '', $code) ?? '');
}

function format_phone(?string $phone): string
{
    $digits = normalize_phone((string) $phone);
    if ($digits === '') {
        return '-';
    }
    if (strlen($digits) == 11) {
        return preg_replace('/^(\d{3})(\d{4})(\d{4})$/', '$1-$2-$3', $digits) ?: $digits;
    }
    if (strlen($digits) == 10) {
        return preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '$1-$2-$3', $digits) ?: $digits;
    }
    return $digits;
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }
    return date('Y-m-d H:i', strtotime($value));
}

function format_date(?string $value): string
{
    if (!$value) {
        return '-';
    }
    return date('Y-m-d', strtotime($value));
}

function document_status_label(string $status): string
{
    return match ($status) {
        'draft' => '임시저장',
        'submitted' => '결재 진행중',
        'rejected' => '반려',
        'approved' => '결재 완료',
        'cancelled' => '기안취소',
        'deleted' => '휴지통',
        default => $status,
    };
}

function step_status_label(string $status): string
{
    return match ($status) {
        'waiting' => '대기',
        'pending' => '진행중',
        'approved' => '승인',
        'rejected' => '반려',
        default => $status,
    };
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'draft' => 'badge badge-gray',
        'submitted', 'pending' => 'badge badge-blue',
        'approved' => 'badge badge-green',
        'rejected' => 'badge badge-red',
        'waiting' => 'badge badge-amber',
        'cancelled' => 'badge badge-gray',
        'deleted' => 'badge badge-dark',
        default => 'badge badge-gray',
    };
}

function log_action_label(string $actionType): string
{
    return match ($actionType) {
        'submitted' => '기안',
        'resubmitted' => '재기안',
        'saved' => '임시저장',
        'approved' => '승인',
        'rejected' => '반려',
        'cancelled' => '기안취소',
        'deleted' => '휴지통 이동',
        'restored' => '복구',
        'reassigned' => '결재자 재지정',
        default => $actionType,
    };
}

function upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '파일 용량이 너무 큽니다.',
        UPLOAD_ERR_PARTIAL => '파일 업로드가 중간에 끊겼습니다.',
        UPLOAD_ERR_NO_FILE => '업로드된 파일이 없습니다.',
        default => '파일 업로드 중 오류가 발생했습니다.',
    };
}

function render_adfit_slot(string $slot, string $label = ''): string
{
    if (function_exists('current_user')) {
        try {
            $u = current_user(db());
            if ($u && !empty($u['adfree_until']) && strtotime((string) $u['adfree_until']) > time()) {
                return '';
            }
            if ($u && strtolower((string) ($u['plan_name'] ?? '')) === 'premium') {
                return '';
            }
        } catch (Throwable $e) {
        }
    }
    $html = '';
    if ($slot === 'top') {
        $html = ADFIT_TOP_HTML;
    } elseif ($slot === 'sidebar') {
        $html = ADFIT_SIDEBAR_HTML;
    } elseif ($slot === 'bottom') {
        $html = ADFIT_BOTTOM_HTML;
    }

    if (trim($html) !== '') {
        return '<div class="adfit-slot adfit-' . e($slot) . '">' . $html . '</div>';
    }

    $guide = $label !== '' ? $label : strtoupper($slot) . ' AdFit 자리';
    return '<div class="adfit-slot adfit-' . e($slot) . ' placeholder"><div><strong>' . e($guide) . '</strong><p>카카오 AdFit 코드 넣는 자리입니다.</p></div></div>';
}


function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $value = (string) $_SERVER[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $value);
                return trim($parts[0]);
            }
            return trim($value);
        }
    }
    return '';
}

function current_locale(?array $user = null): string
{
    if (isset($_GET['lang'])) {
        $lang = in_array($_GET['lang'], ['ko', 'en'], true) ? $_GET['lang'] : DEFAULT_LOCALE;
        $_SESSION['lang'] = $lang;
    }
    if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], ['ko', 'en'], true)) {
        return $_SESSION['lang'];
    }
    if ($user && !empty($user['locale']) && in_array($user['locale'], ['ko', 'en'], true)) {
        return $user['locale'];
    }
    return DEFAULT_LOCALE;
}

function t(string $key, array $replacements = [], ?array $user = null): string
{
    static $messages = [
        'ko' => [
            'app_name' => APP_NAME,
            'dashboard' => '대시보드',
            'documents' => '기안서',
            'create_document' => '기안서 작성',
            'notices' => '공지사항',
            'company_settings' => '회사 설정',
            'users' => '회원 관리',
            'login' => '로그인',
            'logout' => '로그아웃',
            'forgot_password' => '비밀번호 재설정',
        ],
        'en' => [
            'app_name' => APP_NAME,
            'dashboard' => 'Dashboard',
            'documents' => 'Documents',
            'create_document' => 'Create document',
            'notices' => 'Notices',
            'company_settings' => 'Company settings',
            'users' => 'Users',
            'login' => 'Login',
            'logout' => 'Logout',
            'forgot_password' => 'Reset password',
        ],
    ];
    $locale = current_locale($user);
    $text = $messages[$locale][$key] ?? $messages['ko'][$key] ?? $key;
    foreach ($replacements as $name => $value) {
        $text = str_replace(':' . $name, (string) $value, $text);
    }
    return $text;
}

function export_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function waiting_days_text(?string $submittedAt): string
{
    if (!$submittedAt) {
        return '-';
    }
    $days = max(0, (int) floor((time() - strtotime($submittedAt)) / 86400));
    return $days . '일';
}

function company_status_label(string $status): string
{
    return match ($status) {
        'trial' => '체험중',
        'active' => '사용중',
        'suspended' => '정지',
        'expired' => '만료',
        default => $status,
    };
}

function company_status_badge(string $status): string
{
    return match ($status) {
        'trial' => 'badge badge-amber',
        'active' => 'badge badge-green',
        'suspended' => 'badge badge-red',
        'expired' => 'badge badge-gray',
        default => 'badge badge-gray',
    };
}

function plan_badge_class(string $plan): string
{
    return match (strtolower($plan)) {
        'premium' => 'badge badge-blue',
        'standard' => 'badge badge-green',
        default => 'badge badge-gray',
    };
}

function log_activity(PDO $pdo, ?array $user, ?int $companyId, string $actionKey, string $description, ?string $targetType = null, ?int $targetId = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO `approval_activity_logs` (company_id, user_id, target_type, target_id, action_key, description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $companyId,
            $user ? (int) $user['id'] : null,
            $targetType,
            $targetId,
            $actionKey,
            $description,
            client_ip() ?: null,
        ]);
    } catch (Throwable $e) {
    }
}
