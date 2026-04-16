<?php
declare(strict_types=1);

function generate_company_code(PDO $pdo): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $stmt = $pdo->prepare('SELECT id FROM `approval_companies` WHERE company_code = ? LIMIT 1');
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

function find_company_by_code(PDO $pdo, string $companyCode): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM `approval_companies` WHERE company_code = ? LIMIT 1');
    $stmt->execute([normalize_company_code($companyCode)]);
    return $stmt->fetch() ?: null;
}

function find_company_by_lookup(PDO $pdo, string $companyName, string $ownerName, string $ownerPhone): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM `approval_companies` WHERE company_name = ? AND owner_name = ? AND owner_phone = ? LIMIT 1');
    $stmt->execute([trim($companyName), trim($ownerName), normalize_phone($ownerPhone)]);
    return $stmt->fetch() ?: null;
}

function get_company_name_for_code(PDO $pdo, string $companyCode): ?string
{
    $stmt = $pdo->prepare('SELECT company_name FROM `approval_companies` WHERE company_code = ? LIMIT 1');
    $stmt->execute([normalize_company_code($companyCode)]);
    $name = $stmt->fetchColumn();
    return $name !== false ? (string) $name : null;
}

function get_company_options(PDO $pdo): array
{
    return $pdo->query('SELECT id, company_name, company_code FROM `approval_companies` ORDER BY company_name ASC, id ASC')->fetchAll();
}

function save_revision_snapshot(PDO $pdo, int $documentId, int $versionNo, string $title, string $content, int $createdBy): void
{
    $stmt = $pdo->prepare('INSERT INTO `approval_revisions` (document_id, version_no, title_snapshot, content_snapshot, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$documentId, $versionNo, $title, $content, $createdBy]);
}

function log_document_action(PDO $pdo, int $documentId, int $userId, string $actionType, string $comment = ''): void
{
    $stmt = $pdo->prepare('INSERT INTO `approval_approval_logs` (document_id, user_id, action_type, comment, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$documentId, $userId, $actionType, $comment]);
}

function create_notification(PDO $pdo, int $userId, string $typeKey, string $title, string $message, string $linkUrl = ''): void
{
    $stmt = $pdo->prepare('INSERT INTO `approval_notifications` (user_id, type_key, title, message, link_url, is_read, created_at, read_at) VALUES (?, ?, ?, ?, ?, 0, NOW(), NULL)');
    $stmt->execute([$userId, $typeKey, $title, $message, $linkUrl]);
}

function unread_notification_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_notifications` WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function get_notifications(PDO $pdo, int $userId, int $limit = 50): array
{
    $stmt = $pdo->prepare('SELECT * FROM `approval_notifications` WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function mark_all_notifications_read(PDO $pdo, int $userId): void
{
    $pdo->prepare('UPDATE `approval_notifications` SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0')->execute([$userId]);
}

function mark_document_notifications_read(PDO $pdo, int $userId, int $documentId): void
{
    $needle = '%document_view.php?id=' . $documentId . '%';
    $pdo->prepare('UPDATE `approval_notifications` SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0 AND link_url LIKE ?')->execute([$userId, $needle]);
}

function generate_document_no(int $documentId): string
{
    return DOC_NO_PREFIX . '-' . date('Ymd') . '-' . str_pad((string) $documentId, 4, '0', STR_PAD_LEFT);
}

function ensure_document_number(PDO $pdo, int $documentId): string
{
    $docNo = generate_document_no($documentId);
    $pdo->prepare('UPDATE `approval_documents` SET doc_no = ? WHERE id = ? AND (doc_no IS NULL OR doc_no = "")')->execute([$docNo, $documentId]);
    return $docNo;
}

function get_document(PDO $pdo, int $documentId): ?array
{
    $stmt = $pdo->prepare('
        SELECT d.*, u.name AS writer_name, u.job_title AS writer_job_title, u.company_id AS writer_company_id,
               c.company_name, c.company_code, c.allow_bulk_approval, c.plan_name, c.service_status, c.locale,
               cat.name AS category_name, tpl.name AS template_name,
               del.name AS deleted_by_name
        FROM `approval_documents` d
        INNER JOIN `approval_users` u ON u.id = d.writer_id
        INNER JOIN `approval_companies` c ON c.id = d.company_id
        LEFT JOIN `approval_document_categories` cat ON cat.id = d.category_id
        LEFT JOIN `approval_approval_templates` tpl ON tpl.id = d.template_id
        LEFT JOIN `approval_users` del ON del.id = d.deleted_by
        WHERE d.id = ?
        LIMIT 1
    ');
    $stmt->execute([$documentId]);
    return $stmt->fetch() ?: null;
}

function user_can_view_document(PDO $pdo, array $user, array $document): bool
{
    if (is_super_admin($user)) {
        return true;
    }
    if ((int) $user['company_id'] !== (int) $document['company_id']) {
        return false;
    }
    if (is_company_admin($user)) {
        return true;
    }
    if ((int) $user['id'] === (int) $document['writer_id']) {
        return true;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM `approval_approval_steps` WHERE document_id = ? AND approver_user_id = ? LIMIT 1');
    $stmt->execute([(int) $document['id'], (int) $user['id']]);
    if ($stmt->fetchColumn()) {
        return true;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM `approval_document_readers` WHERE document_id = ? AND reader_user_id = ? LIMIT 1');
    $stmt->execute([(int) $document['id'], (int) $user['id']]);
    return (bool) $stmt->fetchColumn();
}

function get_document_attachments(PDO $pdo, int $documentId): array
{
    $stmt = $pdo->prepare('SELECT * FROM `approval_document_files` WHERE document_id = ? ORDER BY id ASC');
    $stmt->execute([$documentId]);
    return $stmt->fetchAll();
}

function get_document_steps(PDO $pdo, int $documentId): array
{
    $stmt = $pdo->prepare('
        SELECT s.*, approver.name AS approver_name, approver.job_title AS approver_job_title,
               approver.is_active AS approver_is_active,
               actor.name AS acted_by_name, actor.job_title AS acted_by_job_title
        FROM `approval_approval_steps` s
        INNER JOIN `approval_users` approver ON approver.id = s.approver_user_id
        LEFT JOIN `approval_users` actor ON actor.id = s.acted_by_user_id
        WHERE s.document_id = ?
        ORDER BY s.step_no ASC
    ');
    $stmt->execute([$documentId]);
    return $stmt->fetchAll();
}

function get_document_logs(PDO $pdo, int $documentId): array
{
    $stmt = $pdo->prepare('
        SELECT l.*, u.name, u.job_title
        FROM `approval_approval_logs` l
        INNER JOIN `approval_users` u ON u.id = l.user_id
        WHERE l.document_id = ?
        ORDER BY l.id DESC
    ');
    $stmt->execute([$documentId]);
    return $stmt->fetchAll();
}

function get_assignable_approvers(PDO $pdo, int $excludeUserId = 0, ?int $companyId = null): array
{
    $sql = 'SELECT id, name, email, phone, login_id, company_id, level_no, job_title, role, is_active FROM `approval_users` WHERE is_active = 1 AND role <> "super_admin"';
    $params = [];
    if ($companyId !== null) {
        $sql .= ' AND company_id = ?';
        $params[] = $companyId;
    }
    if ($excludeUserId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeUserId;
    }
    $sql .= ' ORDER BY level_no ASC, name ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_user_assigned_approvers(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('
        SELECT ua.approver_user_id, ua.position_no, u.company_id, u.name, u.email, u.phone, u.login_id, u.level_no, u.job_title, u.role, u.is_active
        FROM `approval_user_approvers` ua
        INNER JOIN `approval_users` u ON u.id = ua.approver_user_id
        WHERE ua.user_id = ?
        ORDER BY u.level_no ASC, ua.position_no ASC, u.name ASC, u.id ASC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function get_user_assigned_approver_ids(PDO $pdo, int $userId): array
{
    return array_map(static fn(array $row): int => (int) $row['approver_user_id'], get_user_assigned_approvers($pdo, $userId));
}

function sync_user_approvers(PDO $pdo, int $userId, array $approverIds): void
{
    $normalized = [];
    foreach ($approverIds as $approverId) {
        $approverId = (int) $approverId;
        if ($approverId > 0 && $approverId !== $userId && !in_array($approverId, $normalized, true)) {
            $normalized[] = $approverId;
        }
    }
    $pdo->prepare('DELETE FROM `approval_user_approvers` WHERE user_id = ?')->execute([$userId]);
    if (!$normalized) {
        return;
    }
    $insert = $pdo->prepare('INSERT INTO `approval_user_approvers` (user_id, approver_user_id, position_no, created_at) VALUES (?, ?, ?, NOW())');
    foreach ($normalized as $index => $approverId) {
        $insert->execute([$userId, $approverId, $index + 1]);
    }
}

function get_writer_level(PDO $pdo, int $writerId): ?int
{
    $writerStmt = $pdo->prepare('SELECT level_no FROM `approval_users` WHERE id = ? LIMIT 1');
    $writerStmt->execute([$writerId]);
    $level = $writerStmt->fetchColumn();
    return $level === false ? null : (int) $level;
}

function get_raw_assigned_approval_line(PDO $pdo, int $writerId, ?int $templateId = null): array
{
    $rows = [];
    if ($templateId) {
        $steps = get_template_steps($pdo, $templateId);
        if ($steps) {
            $rows = array_map(static function (array $row): array {
                return [
                    'approver_user_id' => $row['approver_user_id'],
                    'position_no' => $row['step_no'],
                    'level_no' => $row['level_no'],
                    'name' => $row['name'],
                    'job_title' => $row['job_title'],
                ];
            }, $steps);
        }
    }
    if (!$rows) {
        $stmt = $pdo->prepare('
        SELECT ua.approver_user_id, ua.position_no, u.level_no, u.name, u.job_title, u.email, u.phone, u.role, u.is_active, u.company_id
        FROM `approval_user_approvers` ua
        INNER JOIN `approval_users` u ON u.id = ua.approver_user_id
        WHERE ua.user_id = ? AND u.is_active = 1
        ORDER BY u.level_no ASC, ua.position_no ASC, u.name ASC, u.id ASC
    ');
        $stmt->execute([$writerId]);
        $rows = $stmt->fetchAll();
    }

    return $rows ?: [];
}

function normalize_assigned_approval_line(array $rows, int $writerLevel): array
{
    $normalized = [];
    $seen = [];
    foreach ($rows as $row) {
        $approverId = (int) ($row['approver_user_id'] ?? 0);
        $levelNo = (int) ($row['level_no'] ?? 0);
        if ($approverId <= 0 || $levelNo <= $writerLevel) {
            continue;
        }
        if (in_array($approverId, $seen, true)) {
            continue;
        }
        $seen[] = $approverId;
        $row['auto_approve_self'] = 0;
        $normalized[] = $row;
    }

    return array_values($normalized);
}

function get_assigned_approval_line(PDO $pdo, int $writerId, ?int $templateId = null): array
{
    $writerLevel = get_writer_level($pdo, $writerId);
    if ($writerLevel === null) {
        return [];
    }

    return normalize_assigned_approval_line(get_raw_assigned_approval_line($pdo, $writerId, $templateId), $writerLevel);
}

function will_auto_approve_submission(PDO $pdo, int $writerId, ?int $templateId = null): bool
{
    $writerLevel = get_writer_level($pdo, $writerId);
    if ($writerLevel === null) {
        return false;
    }

    $rawLine = get_raw_assigned_approval_line($pdo, $writerId, $templateId);
    if (!$rawLine) {
        return false;
    }

    return normalize_assigned_approval_line($rawLine, $writerLevel) === [];
}

function has_document_approval_started(PDO $pdo, int $documentId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM `approval_approval_steps` WHERE document_id = ? AND status IN ("approved", "rejected") LIMIT 1');
    $stmt->execute([$documentId]);
    return (bool) $stmt->fetchColumn();
}

function can_edit_document(PDO $pdo, array $user, array $document): bool
{
    if ((int) $user['id'] !== (int) $document['writer_id']) {
        return false;
    }
    if (!same_company($user, (int) $document['company_id'])) {
        return false;
    }
    if (in_array($document['status'], ['draft', 'rejected'], true)) {
        return true;
    }
    if ($document['status'] === 'submitted') {
        return !has_document_approval_started($pdo, (int) $document['id']);
    }
    return false;
}

function can_cancel_document(PDO $pdo, array $user, array $document): bool
{
    if ((int) $user['id'] !== (int) $document['writer_id']) {
        return false;
    }
    if (!same_company($user, (int) $document['company_id'])) {
        return false;
    }
    if ($document['status'] === 'draft') {
        return true;
    }
    return $document['status'] === 'submitted' && !has_document_approval_started($pdo, (int) $document['id']);
}

function can_delete_document(array $user, array $document): bool
{
    return is_admin($user) && same_company($user, (int) $document['company_id']);
}

function can_restore_document(array $user, array $document): bool
{
    return can_delete_document($user, $document) && $document['status'] === 'deleted';
}

function find_pending_step_for_user(PDO $pdo, int $documentId, int $approverUserId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM `approval_approval_steps` WHERE document_id = ? AND status = "pending" AND approver_user_id = ? LIMIT 1');
    $stmt->execute([$documentId, $approverUserId]);
    return $stmt->fetch() ?: null;
}

function can_approve_document(PDO $pdo, array $user, array $document): bool
{
    if ($document['status'] !== 'submitted') {
        return false;
    }
    if (!same_company($user, (int) $document['company_id'])) {
        return false;
    }
    return (bool) find_pending_step_for_user($pdo, (int) $document['id'], (int) $user['id']);
}

function can_reassign_step(array $user, array $document): bool
{
    return is_admin($user) && same_company($user, (int) $document['company_id']) && $document['status'] === 'submitted';
}

function should_auto_approve_step(PDO $pdo, int $documentId, array $step): bool
{
    $approverUserId = (int) ($step['approver_user_id'] ?? 0);
    if ($approverUserId <= 0) {
        return false;
    }
    $docStmt = $pdo->prepare('SELECT writer_id FROM `approval_documents` WHERE id = ? LIMIT 1');
    $docStmt->execute([$documentId]);
    $writerId = (int) $docStmt->fetchColumn();
    if ($writerId <= 0 || $writerId !== $approverUserId) {
        return false;
    }
    $maxStmt = $pdo->prepare('SELECT MAX(step_no) FROM `approval_approval_steps` WHERE document_id = ?');
    $maxStmt->execute([$documentId]);
    $lastStepNo = (int) $maxStmt->fetchColumn();
    return $lastStepNo > 0 && $lastStepNo === (int) ($step['step_no'] ?? 0);
}

function purge_document_files_from_disk(PDO $pdo, int $documentId): void
{
    foreach (get_document_attachments($pdo, $documentId) as $attachment) {
        $path = (string) ($attachment['file_path'] ?? '');
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}

function empty_trash_for_scope(PDO $pdo, array $user, int $companyId = 0): int
{
    if (!is_admin($user)) {
        return 0;
    }
    $sql = 'SELECT id FROM `approval_documents` WHERE status = "deleted"';
    $params = [];
    if (is_super_admin($user)) {
        if ($companyId > 0) {
            $sql .= ' AND company_id = ?';
            $params[] = $companyId;
        }
    } else {
        $sql .= ' AND company_id = ?';
        $params[] = (int) ($user['company_id'] ?? 0);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $documentIds = array_map(static fn(array $row): int => (int) $row['id'], $stmt->fetchAll());
    if (!$documentIds) {
        return 0;
    }

    $pdo->beginTransaction();
    try {
        $deleteStmt = $pdo->prepare('DELETE FROM `approval_documents` WHERE id = ?');
        foreach ($documentIds as $documentId) {
            purge_document_files_from_disk($pdo, $documentId);
            $deleteStmt->execute([$documentId]);
        }
        log_activity($pdo, $user, is_super_admin($user) ? ($companyId > 0 ? $companyId : null) : (int) ($user['company_id'] ?? 0), 'trash_emptied', '휴지통 비우기 (' . count($documentIds) . '건)', 'document', null);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return count($documentIds);
}

function advance_document_approval_flow(PDO $pdo, int $documentId, bool $notify = true): array
{
    while (true) {
        $pendingStmt = $pdo->prepare('SELECT * FROM `approval_approval_steps` WHERE document_id = ? AND status = "pending" ORDER BY step_no ASC LIMIT 1');
        $pendingStmt->execute([$documentId]);
        $pending = $pendingStmt->fetch();

        if ($pending) {
            if (should_auto_approve_step($pdo, $documentId, $pending)) {
                $pdo->prepare('UPDATE `approval_approval_steps` SET status = "approved", acted_by_user_id = ?, acted_at = NOW(), comment = COALESCE(NULLIF(comment, ""), "기안자 최종 결재 자동 승인") WHERE id = ?')->execute([(int) $pending['approver_user_id'], (int) $pending['id']]);
                continue;
            }
            $pdo->prepare('UPDATE `approval_documents` SET status = "submitted", current_step = ?, completed_at = NULL, updated_at = NOW() WHERE id = ?')->execute([(int) $pending['step_no'], $documentId]);
            if ($notify) {
                notify_next_pending_approver($pdo, $documentId);
            }
            return ['status' => 'submitted', 'current_step' => (int) $pending['step_no']];
        }

        $waitingStmt = $pdo->prepare('SELECT * FROM `approval_approval_steps` WHERE document_id = ? AND status = "waiting" ORDER BY step_no ASC LIMIT 1');
        $waitingStmt->execute([$documentId]);
        $waiting = $waitingStmt->fetch();
        if ($waiting) {
            if (should_auto_approve_step($pdo, $documentId, $waiting)) {
                $pdo->prepare('UPDATE `approval_approval_steps` SET status = "approved", acted_by_user_id = ?, acted_at = NOW(), comment = COALESCE(NULLIF(comment, ""), "기안자 최종 결재 자동 승인") WHERE id = ?')->execute([(int) $waiting['approver_user_id'], (int) $waiting['id']]);
                continue;
            }
            $pdo->prepare('UPDATE `approval_approval_steps` SET status = "pending" WHERE id = ?')->execute([(int) $waiting['id']]);
            $pdo->prepare('UPDATE `approval_documents` SET status = "submitted", current_step = ?, completed_at = NULL, updated_at = NOW() WHERE id = ?')->execute([(int) $waiting['step_no'], $documentId]);
            if ($notify) {
                notify_next_pending_approver($pdo, $documentId);
            }
            return ['status' => 'submitted', 'current_step' => (int) $waiting['step_no']];
        }

        $doc = get_document($pdo, $documentId);
        if ($doc && $doc['status'] !== 'approved') {
            $pdo->prepare('UPDATE `approval_documents` SET status = "approved", current_step = NULL, completed_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([$documentId]);
            if ($notify) {
                notify_writer($pdo, $documentId, 'approved', '문서 결재가 완료되었습니다', '[' . ($doc['doc_no'] ?: '-') . '] ' . $doc['title'] . ' 문서가 최종 승인되었습니다.');
            }
        }
        return ['status' => 'approved', 'current_step' => null];
    }
}

function create_approval_steps(PDO $pdo, int $documentId, int $writerId, ?int $templateId = null): array
{
    $pdo->prepare('DELETE FROM `approval_approval_steps` WHERE document_id = ?')->execute([$documentId]);
    $approvers = get_assigned_approval_line($pdo, $writerId, $templateId);
    if (!$approvers) {
        if (will_auto_approve_submission($pdo, $writerId, $templateId)) {
            return ['status' => 'approved', 'current_step' => null, 'step_count' => 0, 'auto_approved' => true];
        }
        throw new RuntimeException('적용 가능한 상위 결재선이 없습니다. 결재선 템플릿 또는 개인 결재 가능 인원을 다시 설정하세요.');
    }
    $insert = $pdo->prepare('INSERT INTO `approval_approval_steps` (document_id, step_no, approver_user_id, required_level_no, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    foreach ($approvers as $index => $approver) {
        $insert->execute([
            $documentId,
            $index + 1,
            (int) $approver['approver_user_id'],
            (int) $approver['level_no'],
            $index === 0 ? 'pending' : 'waiting',
        ]);
    }
    $flow = advance_document_approval_flow($pdo, $documentId, false);
    $flow['step_count'] = count($approvers);
    return $flow;
}

function persist_uploaded_files(PDO $pdo, int $documentId, array $files): array
{
    $savedIds = [];
    if (empty($files['name']) || !is_array($files['name'])) {
        return $savedIds;
    }
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_document_files` WHERE document_id = ?');
    $countStmt->execute([$documentId]);
    $existingCount = (int) $countStmt->fetchColumn();
    $newCount = 0;
    foreach ($files['name'] as $idx => $name) {
        if (($files['error'][$idx] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newCount++;
        }
    }
    if ($existingCount + $newCount > MAX_ATTACHMENTS) {
        throw new RuntimeException('첨부파일은 최대 ' . MAX_ATTACHMENTS . '개까지 업로드할 수 있습니다.');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $stmt = $pdo->prepare('INSERT INTO `approval_document_files` (document_id, original_name, saved_name, file_path, file_size, mime_type, download_count, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())');
    foreach ($files['name'] as $i => $originalName) {
        $errorCode = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException(upload_error_message($errorCode));
        }
        $tmpPath = (string) $files['tmp_name'][$i];
        $size = (int) $files['size'][$i];
        $ext = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
        if ($size <= 0 || $size > MAX_FILE_SIZE) {
            throw new RuntimeException('파일당 최대 10MB까지 업로드할 수 있습니다.');
        }
        if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('허용되지 않는 파일 형식입니다: ' . $originalName);
        }
        $savedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPath = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $savedName;
        if (!move_uploaded_file($tmpPath, $targetPath)) {
            throw new RuntimeException('첨부파일 저장에 실패했습니다.');
        }
        $mimeType = mime_content_type($targetPath) ?: 'application/octet-stream';
        $stmt->execute([$documentId, (string) $originalName, $savedName, $targetPath, $size, $mimeType]);
        $savedIds[] = (int) $pdo->lastInsertId();
    }
    return $savedIds;
}

function get_document_filters_from_request(): array
{
    return [
        'keyword' => trim((string) ($_GET['keyword'] ?? '')),
        'status' => trim((string) ($_GET['status'] ?? '')),
        'date_from' => trim((string) ($_GET['date_from'] ?? '')),
        'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        'company_id' => max(0, (int) ($_GET['company_id'] ?? 0)),
        'category_id' => max(0, (int) ($_GET['category_id'] ?? 0)),
    ];
}

function get_documents_for_scope(PDO $pdo, array $user, string $scope, array $filters = []): array
{
    $filters = array_merge(['keyword' => '', 'status' => '', 'date_from' => '', 'date_to' => '', 'company_id' => 0, 'category_id' => 0], $filters);
    $conditions = [];
    $params = [];
    if (is_super_admin($user)) {
        if ((int) $filters['company_id'] > 0) {
            $conditions[] = 'd.company_id = ?';
            $params[] = (int) $filters['company_id'];
        }
    } else {
        $conditions[] = 'd.company_id = ?';
        $params[] = (int) $user['company_id'];
    }
    if ($scope === 'my') {
        $conditions[] = 'd.writer_id = ?';
        $params[] = (int) $user['id'];
    } elseif ($scope === 'completed') {
        $conditions[] = 'd.status = "approved"';
    } elseif ($scope === 'rejected') {
        $conditions[] = 'd.status = "rejected"';
    } elseif ($scope === 'waiting') {
        $conditions[] = 'd.status = "submitted"';
        $conditions[] = 'EXISTS (SELECT 1 FROM `approval_approval_steps` s WHERE s.document_id = d.id AND s.status = "pending" AND s.approver_user_id = ?)';
        $params[] = (int) $user['id'];
    } elseif ($scope === 'trash') {
        $conditions[] = is_admin($user) ? 'd.status = "deleted"' : '1 = 0';
    } else {
        $conditions[] = 'd.status <> "deleted"';
    }
    if ($filters['status'] !== '') {
        $conditions[] = 'd.status = ?';
        $params[] = $filters['status'];
    }
    if ((int) $filters['category_id'] > 0) {
        $conditions[] = 'd.category_id = ?';
        $params[] = (int) $filters['category_id'];
    }
    if ($filters['keyword'] !== '') {
        $conditions[] = '(d.doc_no LIKE ? OR d.title LIKE ? OR d.content LIKE ? OR u.name LIKE ? OR c.company_name LIKE ? OR cat.name LIKE ?)';
        $like = '%' . $filters['keyword'] . '%';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
    if ($filters['date_from'] !== '') {
        $conditions[] = 'DATE(d.created_at) >= ?';
        $params[] = $filters['date_from'];
    }
    if ($filters['date_to'] !== '') {
        $conditions[] = 'DATE(d.created_at) <= ?';
        $params[] = $filters['date_to'];
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $sql = '
        SELECT d.*, c.company_name, c.company_code, c.allow_bulk_approval, u.name AS writer_name, u.job_title AS writer_job_title,
               cat.name AS category_name, tpl.name AS template_name,
               (SELECT step_no FROM `approval_approval_steps` s1 WHERE s1.document_id = d.id AND s1.status = "pending" ORDER BY s1.step_no ASC LIMIT 1) AS pending_step_no,
               (SELECT COUNT(*) FROM `approval_approval_steps` s2 WHERE s2.document_id = d.id) AS total_steps,
               (SELECT approver.name FROM `approval_approval_steps` s3 INNER JOIN `approval_users` approver ON approver.id = s3.approver_user_id WHERE s3.document_id = d.id AND s3.status = "pending" ORDER BY s3.step_no ASC LIMIT 1) AS pending_approver_name,
               (SELECT approver.job_title FROM `approval_approval_steps` s4 INNER JOIN `approval_users` approver ON approver.id = s4.approver_user_id WHERE s4.document_id = d.id AND s4.status = "pending" ORDER BY s4.step_no ASC LIMIT 1) AS pending_approver_job_title,
               (SELECT COUNT(*) FROM `approval_document_files` f WHERE f.document_id = d.id) AS attachment_count
        FROM `approval_documents` d
        INNER JOIN `approval_users` u ON u.id = d.writer_id
        INNER JOIN `approval_companies` c ON c.id = d.company_id
        LEFT JOIN `approval_document_categories` cat ON cat.id = d.category_id
        LEFT JOIN `approval_approval_templates` tpl ON tpl.id = d.template_id
        ' . $where . '
        ORDER BY d.updated_at DESC, d.id DESC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_scope_counts(PDO $pdo, array $user, array $filters = []): array
{
    $baseFilters = $filters;
    $baseFilters['status'] = '';
    return [
        'all' => count(get_documents_for_scope($pdo, $user, 'all', $baseFilters)),
        'my' => count(get_documents_for_scope($pdo, $user, 'my', $baseFilters)),
        'waiting' => count(get_documents_for_scope($pdo, $user, 'waiting', $baseFilters)),
        'completed' => count(get_documents_for_scope($pdo, $user, 'completed', $baseFilters)),
        'rejected' => count(get_documents_for_scope($pdo, $user, 'rejected', $baseFilters)),
        'trash' => is_admin($user) ? count(get_documents_for_scope($pdo, $user, 'trash', $baseFilters)) : 0,
    ];
}

function scope_label(string $scope): string
{
    return match ($scope) {
        'my' => '내 기안서',
        'completed' => '결재 완료',
        'waiting' => '결재 대기',
        'rejected' => '반려함',
        'trash' => '휴지통',
        default => '기안서 통합',
    };
}

function current_step_text(array $document): string
{
    if ($document['status'] === 'approved') return '완료';
    if ($document['status'] === 'rejected') return '반려됨';
    if ($document['status'] === 'cancelled') return '기안취소';
    if ($document['status'] === 'draft') return '임시저장';
    if ($document['status'] === 'deleted') return '휴지통';
    if (!empty($document['pending_step_no'])) {
        $text = $document['pending_step_no'] . '차 결재 진행중';
        if (!empty($document['pending_approver_name'])) {
            $text .= ' · ' . $document['pending_approver_name'];
            if (!empty($document['pending_approver_job_title'])) {
                $text .= ' / ' . $document['pending_approver_job_title'];
            }
        }
        return $text;
    }
    return '-';
}

function notify_next_pending_approver(PDO $pdo, int $documentId): void
{
    $stmt = $pdo->prepare('SELECT s.approver_user_id, d.title, d.doc_no FROM `approval_approval_steps` s INNER JOIN `approval_documents` d ON d.id = s.document_id WHERE s.document_id = ? AND s.status = "pending" LIMIT 1');
    $stmt->execute([$documentId]);
    $row = $stmt->fetch();
    if (!$row) return;
    create_notification($pdo, (int) $row['approver_user_id'], 'approval_waiting', '결재 차례가 도착했습니다', '[' . $row['doc_no'] . '] ' . $row['title'] . ' 문서를 확인하세요.', base_url('document_view.php?id=' . $documentId));
}

function notify_writer(PDO $pdo, int $documentId, string $typeKey, string $title, string $message): void
{
    $stmt = $pdo->prepare('SELECT writer_id FROM `approval_documents` WHERE id = ? LIMIT 1');
    $stmt->execute([$documentId]);
    $writerId = (int) $stmt->fetchColumn();
    if ($writerId > 0) {
        create_notification($pdo, $writerId, $typeKey, $title, $message, base_url('document_view.php?id=' . $documentId));
    }
}

function get_dashboard_stats(PDO $pdo, array $user): array
{
    $stats = [
        'total_docs' => 0,
        'my_docs' => 0,
        'waiting_for_me' => 0,
        'approved_docs' => 0,
        'rejected_docs' => 0,
        'trash_docs' => 0,
        'unread_notifications' => unread_notification_count($pdo, (int) $user['id']),
        'active_users' => 0,
        'inactive_users' => 0,
        'company_count' => 0,
        'today_docs' => 0,
        'today_approved' => 0,
        'old_waiting' => 0,
    ];
    if (is_super_admin($user)) {
        $stats['company_count'] = (int) $pdo->query('SELECT COUNT(*) FROM `approval_companies`')->fetchColumn();
        $stats['total_docs'] = (int) $pdo->query('SELECT COUNT(*) FROM `approval_documents` WHERE status <> "deleted"')->fetchColumn();
        $stats['approved_docs'] = (int) $pdo->query('SELECT COUNT(*) FROM `approval_documents` WHERE status = "approved"')->fetchColumn();
        $stats['rejected_docs'] = (int) $pdo->query('SELECT COUNT(*) FROM `approval_documents` WHERE status = "rejected"')->fetchColumn();
        $stats['trash_docs'] = (int) $pdo->query('SELECT COUNT(*) FROM `approval_documents` WHERE status = "deleted"')->fetchColumn();
        $stats['waiting_for_me'] = (int) $pdo->query('SELECT COUNT(*) FROM `approval_documents` WHERE status = "submitted"')->fetchColumn();
        $stats['active_users'] = (int) $pdo->query('SELECT COUNT(*) FROM `approval_users` WHERE is_active = 1 AND role <> "super_admin"')->fetchColumn();
        $stats['inactive_users'] = (int) $pdo->query('SELECT COUNT(*) FROM `approval_users` WHERE is_active = 0 AND role <> "super_admin"')->fetchColumn();
        $stats['today_docs'] = (int) $pdo->query('SELECT COUNT(*) FROM `approval_documents` WHERE DATE(created_at) = CURDATE()')->fetchColumn();
        $stats['today_approved'] = (int) $pdo->query('SELECT COUNT(*) FROM `approval_documents` WHERE status = "approved" AND DATE(completed_at) = CURDATE()')->fetchColumn();
        $stats['old_waiting'] = (int) $pdo->query('SELECT COUNT(*) FROM `approval_documents` WHERE status = "submitted" AND submitted_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
    } else {
        $companyId = (int) $user['company_id'];
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_documents` WHERE company_id = ? AND status <> "deleted"');
        $stmt->execute([$companyId]);
        $stats['total_docs'] = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_documents` WHERE company_id = ? AND status = "approved"');
        $stmt->execute([$companyId]);
        $stats['approved_docs'] = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_documents` WHERE company_id = ? AND status = "rejected"');
        $stmt->execute([$companyId]);
        $stats['rejected_docs'] = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_documents` WHERE company_id = ? AND status = "deleted"');
        $stmt->execute([$companyId]);
        $stats['trash_docs'] = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_documents` WHERE company_id = ? AND status = "submitted"');
        $stmt->execute([$companyId]);
        $stats['waiting_for_me'] = is_company_admin($user) ? (int) $stmt->fetchColumn() : count(get_documents_for_scope($pdo, $user, 'waiting'));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_users` WHERE company_id = ? AND is_active = 1');
        $stmt->execute([$companyId]);
        $stats['active_users'] = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_users` WHERE company_id = ? AND is_active = 0');
        $stmt->execute([$companyId]);
        $stats['inactive_users'] = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_documents` WHERE company_id = ? AND DATE(created_at) = CURDATE()');
        $stmt->execute([$companyId]);
        $stats['today_docs'] = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_documents` WHERE company_id = ? AND status = "approved" AND DATE(completed_at) = CURDATE()');
        $stmt->execute([$companyId]);
        $stats['today_approved'] = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_documents` WHERE company_id = ? AND status = "submitted" AND submitted_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $stmt->execute([$companyId]);
        $stats['old_waiting'] = (int) $stmt->fetchColumn();
        if (!is_company_admin($user)) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM `approval_documents` WHERE writer_id = ? AND status <> "deleted"');
            $stmt->execute([(int) $user['id']]);
            $stats['my_docs'] = (int) $stmt->fetchColumn();
        }
    }
    return $stats;
}

function get_company_admin_stats(PDO $pdo, string $keyword = ''): array
{
    $conditions = [];
    $params = [];
    if ($keyword !== '') {
        $conditions[] = '(c.company_name LIKE ? OR c.company_code LIKE ? OR c.owner_name LIKE ? OR c.owner_phone LIKE ?)';
        $like = '%' . $keyword . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $sql = '
        SELECT c.*,
               (SELECT COUNT(*) FROM `approval_users` u WHERE u.company_id = c.id) AS user_count,
               (SELECT COUNT(*) FROM `approval_documents` d WHERE d.company_id = c.id AND d.status <> "deleted") AS document_count,
               (SELECT COUNT(*) FROM `approval_documents` d2 WHERE d2.company_id = c.id AND d2.status = "submitted") AS pending_count,
               (SELECT COUNT(*) FROM `approval_documents` d3 WHERE d3.company_id = c.id AND d3.status = "approved") AS approved_count
        FROM `approval_companies` c
        ' . $where . '
        ORDER BY c.id DESC
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
