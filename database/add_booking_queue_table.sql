-- Add a table to track the booking queue and driver assignments
CREATE TABLE IF NOT EXISTS `driver_booking_queue` (
  `queue_id` int(11) NOT NULL AUTO_INCREMENT,
  `driver_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `queue_position` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `tap_in_time` timestamp NULL DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`queue_id`),
  KEY `fk_queue_driver` (`driver_id`),
  KEY `fk_queue_user` (`user_id`),
  KEY `fk_queue_booking` (`booking_id`),
  KEY `idx_active_drivers` (`is_active`, `queue_position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add column to tricycle_bookings if it doesn't exist to track queue assignment
ALTER TABLE `tricycle_bookings` ADD COLUMN `queue_assigned` tinyint(1) DEFAULT 0 AFTER `status`;
ALTER TABLE `tricycle_bookings` ADD COLUMN `assigned_driver_id` int(11) DEFAULT NULL AFTER `queue_assigned`;

-- Foreign key constraints
ALTER TABLE `driver_booking_queue` ADD CONSTRAINT `fk_queue_driver` FOREIGN KEY (`driver_id`) REFERENCES `rfid_drivers` (`driver_id`) ON DELETE CASCADE;
ALTER TABLE `driver_booking_queue` ADD CONSTRAINT `fk_queue_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
ALTER TABLE `driver_booking_queue` ADD CONSTRAINT `fk_queue_booking` FOREIGN KEY (`booking_id`) REFERENCES `tricycle_bookings` (`id`) ON DELETE SET NULL;
