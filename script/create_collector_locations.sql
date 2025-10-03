-- Create table to store last-known collector locations
CREATE TABLE IF NOT EXISTS `collector_locations` (
  `collector_id` INT NOT NULL PRIMARY KEY,
  `latitude` DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
