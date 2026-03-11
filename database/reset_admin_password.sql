-- Run this script if the default admin login does not work.
-- It resets the admin password to: Admin@1234
USE `rani_erp`;
UPDATE `users`
SET `password` = '$2y$12$XykbVfRmHZnIMvTP6XnCCOsozriSIMlGD65Y0iG07rNCnE4XYFEZ6'
WHERE `username` = 'admin';
