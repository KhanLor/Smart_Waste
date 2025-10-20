-- Migration: add phone column to users table if it doesn't exist
-- Run this once on your smart_waste database to add the phone column used by the app.

USE smart_waste;

-- Add column if not exists (MySQL 8+ supports this syntax)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL AFTER address;

-- If your MySQL version doesn't support ADD COLUMN IF NOT EXISTS, run the following safe check:
-- SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone');
-- SET @s = CONCAT('ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER address');
-- PREPARE stmt FROM @s;
-- IF @col_exists = 0 THEN EXECUTE stmt; END IF;
-- DEALLOCATE PREPARE stmt;

-- After running the migration, verify:
-- DESCRIBE users;