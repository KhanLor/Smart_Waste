-- Migration: add fields to support edited and unsent messages
-- Run this in your MySQL / phpMyAdmin or via CLI:

ALTER TABLE `messages`
  ADD COLUMN IF NOT EXISTS `edited_at` DATETIME NULL AFTER `created_at`,
  ADD COLUMN IF NOT EXISTS `is_unsent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `edited_at`,
  ADD COLUMN IF NOT EXISTS `unsent_at` DATETIME NULL AFTER `is_unsent`;

-- Note: If your MySQL version doesn't support ADD COLUMN IF NOT EXISTS, run the following safely (it will fail if column exists):
-- ALTER TABLE `messages` ADD COLUMN `edited_at` DATETIME NULL AFTER `created_at`;
-- ALTER TABLE `messages` ADD COLUMN `is_unsent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `edited_at`;
-- ALTER TABLE `messages` ADD COLUMN `unsent_at` DATETIME NULL AFTER `is_unsent`;
