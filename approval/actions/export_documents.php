<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageUser = require_login($pdo);
$view = (string) ($_GET['view'] ?? 'all');
$filters = [
    'keyword' => trim((string) ($_GET['keyword'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'company_id' => max(0, (int) ($_GET['company_id'] ?? 0)),
    'category_id' => max(0, (int) ($_GET['category_id'] ?? 0)),
];
$documents = get_documents_for_scope($pdo, $pageUser, $view, $filters);
$rows = [];
foreach ($documents as $document) {
    $rows[] = [
        $document['doc_no'] ?: '-',
        document_status_label((string) $document['status']),
        $document['company_name'] ?? '-',
        $document['category_name'] ?? '-',
        $document['template_name'] ?? '-',
        $document['title'],
        $document['writer_name'],
        $document['writer_job_title'] ?: '-',
        current_step_text($document),
        waiting_days_text($document['submitted_at']),
        (string) $document['attachment_count'],
        'v' . (string) $document['version_no'],
        format_datetime($document['created_at']),
        format_datetime($document['updated_at']),
    ];
}
export_csv('approval-documents-' . date('Ymd-His') . '.csv', ['문서번호','상태','회사','분류','템플릿','제목','작성자','직급명','현재 단계','결재 대기','첨부','버전','작성일','최근수정'], $rows);
