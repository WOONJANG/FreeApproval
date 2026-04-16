ALTER TABLE `approval_users`
    ADD COLUMN IF NOT EXISTS `can_manage_members` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`,
    ADD COLUMN IF NOT EXISTS `can_manage_notices` TINYINT(1) NOT NULL DEFAULT 0 AFTER `can_manage_members`;

UPDATE `approval_users`
SET `can_manage_members` = CASE WHEN `role` IN ("super_admin", "admin") THEN 1 ELSE `can_manage_members` END,
    `can_manage_notices` = CASE WHEN `role` IN ("super_admin", "admin") THEN 1 ELSE `can_manage_notices` END;
