-- Add heading column to store collector heading (degrees 0-360)
ALTER TABLE `collector_locations`
ADD COLUMN `heading` FLOAT NULL AFTER `longitude`;
