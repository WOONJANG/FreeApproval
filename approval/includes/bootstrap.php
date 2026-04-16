<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/document_service.php';
require_once __DIR__ . '/company_service.php';
require_once __DIR__ . '/notice_service.php';

app_timezone_init();
$pdo = db();
