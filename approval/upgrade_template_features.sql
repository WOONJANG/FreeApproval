SET NAMES utf8mb4;

ALTER TABLE `approval_approval_templates`
    ADD COLUMN `title_template` VARCHAR(150) DEFAULT NULL AFTER `category_id`,
    ADD COLUMN `content_template` MEDIUMTEXT DEFAULT NULL AFTER `title_template`;

CREATE TABLE IF NOT EXISTS `approval_approval_template_readers` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    reader_user_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_template_reader_pair (template_id, reader_user_id),
    CONSTRAINT fk_template_readers_template FOREIGN KEY (template_id) REFERENCES `approval_approval_templates` (id) ON DELETE CASCADE,
    CONSTRAINT fk_template_readers_user FOREIGN KEY (reader_user_id) REFERENCES `approval_users` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
