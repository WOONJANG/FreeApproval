SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `approval_notifications`;
DROP TABLE IF EXISTS `approval_notices`;
DROP TABLE IF EXISTS `approval_password_reset_tokens`;
DROP TABLE IF EXISTS `approval_revisions`;
DROP TABLE IF EXISTS `approval_activity_logs`;
DROP TABLE IF EXISTS `approval_approval_logs`;
DROP TABLE IF EXISTS `approval_approval_steps`;
DROP TABLE IF EXISTS `approval_document_readers`;
DROP TABLE IF EXISTS `approval_document_files`;
DROP TABLE IF EXISTS `approval_documents`;
DROP TABLE IF EXISTS `approval_approval_template_readers`;
DROP TABLE IF EXISTS `approval_approval_template_steps`;
DROP TABLE IF EXISTS `approval_approval_templates`;
DROP TABLE IF EXISTS `approval_user_approvers`;
DROP TABLE IF EXISTS `approval_document_categories`;
DROP TABLE IF EXISTS `approval_users`;
DROP TABLE IF EXISTS `approval_companies`;

CREATE TABLE `approval_companies` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_code CHAR(6) NOT NULL,
    company_name VARCHAR(120) NOT NULL,
    owner_name VARCHAR(50) NOT NULL,
    owner_phone VARCHAR(20) NOT NULL,
    service_status ENUM('trial','active','suspended','expired') NOT NULL DEFAULT 'trial',
    plan_name VARCHAR(50) NOT NULL DEFAULT 'Free',
    member_limit INT DEFAULT NULL,
    document_limit INT DEFAULT NULL,
    trial_ends_at DATETIME DEFAULT NULL,
    adfree_until DATETIME DEFAULT NULL,
    allow_bulk_approval TINYINT(1) NOT NULL DEFAULT 0,
    locale VARCHAR(10) NOT NULL DEFAULT 'ko',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_companies_code (company_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_users` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED DEFAULT NULL,
    login_id VARCHAR(50) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(50) NOT NULL,
    level_no INT NOT NULL DEFAULT 1,
    job_title VARCHAR(50) DEFAULT NULL,
    role ENUM('super_admin','admin','user') NOT NULL DEFAULT 'user',
    can_manage_members TINYINT(1) NOT NULL DEFAULT 0,
    can_manage_notices TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_login_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_login_id (login_id),
    UNIQUE KEY uq_users_phone (phone),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_company_id (company_id),
    KEY idx_users_role (role),
    CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES `approval_companies` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_document_categories` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_company_category_name (company_id, name),
    KEY idx_categories_company (company_id),
    CONSTRAINT fk_categories_company FOREIGN KEY (company_id) REFERENCES `approval_companies` (id) ON DELETE CASCADE,
    CONSTRAINT fk_categories_user FOREIGN KEY (created_by) REFERENCES `approval_users` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_approval_templates` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    category_id INT UNSIGNED DEFAULT NULL,
    title_template VARCHAR(150) DEFAULT NULL,
    content_template MEDIUMTEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_templates_company (company_id),
    CONSTRAINT fk_templates_company FOREIGN KEY (company_id) REFERENCES `approval_companies` (id) ON DELETE CASCADE,
    CONSTRAINT fk_templates_category FOREIGN KEY (category_id) REFERENCES `approval_document_categories` (id) ON DELETE SET NULL,
    CONSTRAINT fk_templates_user FOREIGN KEY (created_by) REFERENCES `approval_users` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_approval_template_steps` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    step_no INT NOT NULL,
    approver_user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_template_step (template_id, step_no),
    CONSTRAINT fk_template_steps_template FOREIGN KEY (template_id) REFERENCES `approval_approval_templates` (id) ON DELETE CASCADE,
    CONSTRAINT fk_template_steps_user FOREIGN KEY (approver_user_id) REFERENCES `approval_users` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_approval_template_readers` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    reader_user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_template_reader_pair (template_id, reader_user_id),
    CONSTRAINT fk_template_readers_template FOREIGN KEY (template_id) REFERENCES `approval_approval_templates` (id) ON DELETE CASCADE,
    CONSTRAINT fk_template_readers_user FOREIGN KEY (reader_user_id) REFERENCES `approval_users` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_user_approvers` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    approver_user_id INT UNSIGNED NOT NULL,
    position_no INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_approver_pair (user_id, approver_user_id),
    KEY idx_user_approvers_user_id (user_id),
    KEY idx_user_approvers_approver_user_id (approver_user_id),
    CONSTRAINT fk_user_approvers_user FOREIGN KEY (user_id) REFERENCES `approval_users` (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_approvers_approver FOREIGN KEY (approver_user_id) REFERENCES `approval_users` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_documents` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED DEFAULT NULL,
    template_id INT UNSIGNED DEFAULT NULL,
    doc_no VARCHAR(30) DEFAULT NULL,
    writer_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    status ENUM('draft','submitted','rejected','approved','cancelled','deleted') NOT NULL DEFAULT 'draft',
    current_step INT DEFAULT NULL,
    version_no INT NOT NULL DEFAULT 1,
    status_before_delete VARCHAR(20) DEFAULT NULL,
    submitted_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    cancel_reason TEXT DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    deleted_by INT UNSIGNED DEFAULT NULL,
    delete_reason TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_documents_doc_no (doc_no),
    KEY idx_documents_company_id (company_id),
    KEY idx_documents_writer_id (writer_id),
    KEY idx_documents_status (status),
    KEY idx_documents_category_id (category_id),
    CONSTRAINT fk_documents_company FOREIGN KEY (company_id) REFERENCES `approval_companies` (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_category FOREIGN KEY (category_id) REFERENCES `approval_document_categories` (id) ON DELETE SET NULL,
    CONSTRAINT fk_documents_template FOREIGN KEY (template_id) REFERENCES `approval_approval_templates` (id) ON DELETE SET NULL,
    CONSTRAINT fk_documents_writer FOREIGN KEY (writer_id) REFERENCES `approval_users` (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_deleted_by FOREIGN KEY (deleted_by) REFERENCES `approval_users` (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_document_readers` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id INT UNSIGNED NOT NULL,
    reader_user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_document_reader (document_id, reader_user_id),
    CONSTRAINT fk_document_readers_doc FOREIGN KEY (document_id) REFERENCES `approval_documents` (id) ON DELETE CASCADE,
    CONSTRAINT fk_document_readers_user FOREIGN KEY (reader_user_id) REFERENCES `approval_users` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_document_files` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    saved_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL DEFAULT 0,
    mime_type VARCHAR(100) DEFAULT NULL,
    download_count INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_document_files_document_id (document_id),
    CONSTRAINT fk_document_files_document FOREIGN KEY (document_id) REFERENCES `approval_documents` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_approval_steps` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id INT UNSIGNED NOT NULL,
    step_no INT NOT NULL,
    approver_user_id INT UNSIGNED NOT NULL,
    required_level_no INT NOT NULL,
    status ENUM('waiting','pending','approved','rejected') NOT NULL DEFAULT 'waiting',
    acted_by_user_id INT UNSIGNED DEFAULT NULL,
    acted_at DATETIME DEFAULT NULL,
    comment TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_approval_steps_document_id (document_id),
    KEY idx_approval_steps_approver_user_id (approver_user_id),
    KEY idx_approval_steps_required_level_no (required_level_no),
    KEY idx_approval_steps_status (status),
    CONSTRAINT fk_approval_steps_document FOREIGN KEY (document_id) REFERENCES `approval_documents` (id) ON DELETE CASCADE,
    CONSTRAINT fk_approval_steps_approver_user FOREIGN KEY (approver_user_id) REFERENCES `approval_users` (id) ON DELETE CASCADE,
    CONSTRAINT fk_approval_steps_user FOREIGN KEY (acted_by_user_id) REFERENCES `approval_users` (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_approval_logs` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    action_type VARCHAR(30) NOT NULL,
    comment TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_approval_logs_document_id (document_id),
    KEY idx_approval_logs_user_id (user_id),
    CONSTRAINT fk_approval_logs_document FOREIGN KEY (document_id) REFERENCES `approval_documents` (id) ON DELETE CASCADE,
    CONSTRAINT fk_approval_logs_user FOREIGN KEY (user_id) REFERENCES `approval_users` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_activity_logs` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED DEFAULT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    target_type VARCHAR(40) DEFAULT NULL,
    target_id INT UNSIGNED DEFAULT NULL,
    action_key VARCHAR(50) NOT NULL,
    description VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_activity_company (company_id),
    KEY idx_activity_user (user_id),
    CONSTRAINT fk_activity_company FOREIGN KEY (company_id) REFERENCES `approval_companies` (id) ON DELETE SET NULL,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES `approval_users` (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_revisions` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id INT UNSIGNED NOT NULL,
    version_no INT NOT NULL,
    title_snapshot VARCHAR(150) NOT NULL,
    content_snapshot MEDIUMTEXT NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_revisions_document_id (document_id),
    CONSTRAINT fk_revisions_document FOREIGN KEY (document_id) REFERENCES `approval_documents` (id) ON DELETE CASCADE,
    CONSTRAINT fk_revisions_user FOREIGN KEY (created_by) REFERENCES `approval_users` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_notices` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED DEFAULT NULL,
    writer_user_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    is_notice TINYINT(1) NOT NULL DEFAULT 0,
    is_global TINYINT(1) NOT NULL DEFAULT 0,
    is_popup TINYINT(1) NOT NULL DEFAULT 0,
    is_banner TINYINT(1) NOT NULL DEFAULT 0,
    view_count INT NOT NULL DEFAULT 0,
    active_from DATETIME DEFAULT NULL,
    active_until DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_notices_company_id (company_id),
    KEY idx_notices_writer_user_id (writer_user_id),
    KEY idx_notices_is_notice (is_notice),
    KEY idx_notices_is_global (is_global),
    CONSTRAINT fk_notices_company FOREIGN KEY (company_id) REFERENCES `approval_companies` (id) ON DELETE CASCADE,
    CONSTRAINT fk_notices_writer FOREIGN KEY (writer_user_id) REFERENCES `approval_users` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_notifications` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    type_key VARCHAR(30) NOT NULL,
    title VARCHAR(120) NOT NULL,
    message VARCHAR(255) NOT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    read_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_notifications_user_id (user_id),
    KEY idx_notifications_is_read (is_read),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES `approval_users` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `approval_password_reset_tokens` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_reset_user (user_id),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES `approval_users` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `approval_users` (company_id, login_id, email, phone, password_hash, name, level_no, job_title, role, can_manage_members, can_manage_notices, is_active, failed_login_attempts, locked_until, created_at, updated_at)
VALUES
(NULL, 'admin', NULL, NULL, '$2y$12$z0MJwTGwuvaZ2rM0BUjx8eFutAuqeJYuVMGN1/yURDy2q2HtUj1Gm', '최상위 관리자', 999, '프로젝트 관리자', 'super_admin', 1, 1, 1, 0, NULL, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
