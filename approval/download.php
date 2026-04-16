<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageUser = require_login($pdo);
$fileId = (int) ($_GET['file_id'] ?? 0);
$stmt = $pdo->prepare('SELECT df.*, d.id AS document_id FROM `approval_document_files` df INNER JOIN `approval_documents` d ON d.id = df.document_id WHERE df.id = ? LIMIT 1');
$stmt->execute([$fileId]);
$file = $stmt->fetch();
if (!$file || !is_file($file['file_path'])) { http_response_code(404); exit('파일을 찾을 수 없습니다.'); }
$document = get_document($pdo, (int) $file['document_id']);
if (!$document || !user_can_view_document($pdo, $pageUser, $document)) { http_response_code(403); exit('이 파일을 볼 수 없습니다.'); }
$pdo->prepare('UPDATE `approval_document_files` SET download_count = download_count + 1 WHERE id = ?')->execute([$fileId]);
header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($file['file_path']));
header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"');
readfile($file['file_path']);
exit;
