-- Migration: add fields to support edited and unsent chat messages in `chat_messages`
-- Run this in MySQL or phpMyAdmin

ALTER TABLE `chat_messages`
  ADD COLUMN IF NOT EXISTS `edited_at` DATETIME NULL AFTER `created_at`,
  ADD COLUMN IF NOT EXISTS `is_unsent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `edited_at`,
  ADD COLUMN IF NOT EXISTS `unsent_at` DATETIME NULL AFTER `is_unsent`;

-- If your MySQL version doesn't support ADD COLUMN IF NOT EXISTS, add columns manually:
-- ALTER TABLE `chat_messages` ADD COLUMN `edited_at` DATETIME NULL AFTER `created_at`;
-- ALTER TABLE `chat_messages` ADD COLUMN `is_unsent` TINYINT(1) NOT NULL DEFAULT 0 AFTER `edited_at`;
-- ALTER TABLE `chat_messages` ADD COLUMN `unsent_at` DATETIME NULL AFTER `is_unsent`;
