-- Driver Locations Table
-- This table stores real-time GPS coordinates for drivers
-- Used for admin tracking and driver location history

CREATE TABLE IF NOT EXISTS `driver_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `driver_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `accuracy` float DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_driver_id` (`driver_id`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_driver_timestamp` (`driver_id`, `timestamp`),
  FOREIGN KEY (`driver_id`) REFERENCES `rfid_drivers` (`driver_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
