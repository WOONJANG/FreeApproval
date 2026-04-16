<?php
declare(strict_types=1);

define('APP_NAME', 'Approval');
define('APP_TIMEZONE', 'Asia/Seoul');
define('TABLE_PREFIX', 'approval_');
define('DOC_NO_PREFIX', 'APR');
define('DEFAULT_LOCALE', 'ko');
define('PASSWORD_RESET_TOKEN_MINUTES', 20);

define('DB_HOST', 'localhost');
define('DB_NAME', 'wooniverse');
define('DB_USER', 'wooniverse');
define('DB_PASS', 'jw950518@');
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', '/approval');
define('UPLOAD_DIR', __DIR__ . '/../storage/uploads');

define('MAX_FILE_SIZE', 10 * 1024 * 1024);
define('MAX_ATTACHMENTS', 5);
define('ALLOWED_EXTENSIONS', [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'hwp', 'hwpx', 'jpg', 'jpeg', 'png', 'webp', 'gif',
    'zip', 'txt', 'csv'
]);

define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 15);

define('ADFIT_TOP_HTML', '<ins class="kakao_ad_area" style="display:none;"
data-ad-unit = "DAN-qsOMdw33DMRqUXFf"
data-ad-width = "320"
data-ad-height = "100"></ins>
<script type="text/javascript" src="//t1.daumcdn.net/kas/static/ba.min.js" async></script>');
define('ADFIT_SIDEBAR_HTML', '<ins class="kakao_ad_area" style="display:none;"
data-ad-unit = "DAN-JPB9pawuqOR8tk6E"
data-ad-width = "250"
data-ad-height = "250"></ins>
<script type="text/javascript" src="//t1.daumcdn.net/kas/static/ba.min.js" async></script>');
define('ADFIT_BOTTOM_HTML', '<ins class="kakao_ad_area" style="display:none;"
data-ad-unit = "DAN-VsJV4IQ44os8Khh3"
data-ad-width = "728"
data-ad-height = "90"></ins>
<script type="text/javascript" src="//t1.daumcdn.net/kas/static/ba.min.js" async></script>');
