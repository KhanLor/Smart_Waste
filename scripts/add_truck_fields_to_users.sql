-- Add truck tracking fields to users table for collectors
-- Run this once in your database (e.g., via phpMyAdmin or mysql CLI)

ALTER TABLE `users`
  ADD COLUMN `num_trucks` INT NOT NULL DEFAULT 0 AFTER `phone`,
  ADD COLUMN `truck_equipment` VARCHAR(255) DEFAULT NULL AFTER `num_trucks`;

-- Optional: if you want only collectors to use these fields that's enforced in the application logic
-- Backup your users table before running this migration.
