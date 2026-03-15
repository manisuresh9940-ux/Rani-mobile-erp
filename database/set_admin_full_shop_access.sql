-- ============================================================
-- Grant Full Admin Access (All Shop View)
-- ============================================================
-- Usage:
-- 1) Replace 'admin' with your username if needed.
-- 2) Run this file in MySQL/phpMyAdmin.

USE `rani_erp`;

-- Ensure Admin role exists.
INSERT INTO `roles` (`name`)
SELECT 'Admin'
WHERE NOT EXISTS (
  SELECT 1 FROM `roles` WHERE `name` = 'Admin'
);

-- Give full admin access and activate user.
UPDATE `users` u
JOIN `roles` r ON r.`name` = 'Admin'
SET u.`role_id` = r.`id`,
    u.`is_active` = 1
WHERE u.`username` = 'admin';

-- Verify.
SELECT u.`id`, u.`username`, u.`full_name`, r.`name` AS `role`, u.`is_active`
FROM `users` u
JOIN `roles` r ON r.`id` = u.`role_id`
WHERE u.`username` = 'admin';
