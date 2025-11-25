-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2025 at 03:26 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tric_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `driver_attendance`
--

CREATE TABLE `driver_attendance` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('online','offline') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `rfid_uid` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `driver_reports`
--

CREATE TABLE `driver_reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` longtext NOT NULL,
  `status` varchar(50) DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rfid_drivers`
--

CREATE TABLE `rfid_drivers` (
  `driver_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `license_number` varchar(50) NOT NULL,
  `tricycle_info` varchar(255) NOT NULL,
  `or_cr_path` varchar(255) NOT NULL,
  `license_path` varchar(255) NOT NULL,
  `picture_path` varchar(255) NOT NULL,
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `is_online` tinyint(1) DEFAULT 0,
  `rfid_uid` varchar(255) DEFAULT NULL,
  `last_attendance` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rfid_drivers`
--

INSERT INTO `rfid_drivers` (`driver_id`, `user_id`, `license_number`, `tricycle_info`, `or_cr_path`, `license_path`, `picture_path`, `verification_status`, `is_online`, `rfid_uid`, `last_attendance`, `created_at`) VALUES
(2, 5, '12345', 'Sniper155', 'public/uploads/drivers/5_or_cr_1763700807_691ff047afcf2.jpg', 'public/uploads/drivers/5_license_1763700807_691ff047af294.jpg', 'public/uploads/drivers/5_picture_1763700807_691ff047b0564.jpg', 'verified', 1, 'E317A32A', '2025-11-25 06:12:57', '2025-11-21 04:53:27');

-- --------------------------------------------------------

--
-- Table structure for table `tricycle_bookings`
--

CREATE TABLE `tricycle_bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `pickup_lat` decimal(10,8) DEFAULT NULL,
  `pickup_lng` decimal(11,8) DEFAULT NULL,
  `dest_lat` decimal(10,8) DEFAULT NULL,
  `dest_lng` decimal(11,8) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `fare` decimal(10,2) DEFAULT NULL,
  `booking_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `driver_id` int(11) DEFAULT NULL,
  `status` enum('pending','accepted','in-transit','completed','cancelled') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `user_type` enum('passenger','driver','admin') NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive','suspended') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `user_type`, `name`, `email`, `phone`, `password`, `is_verified`, `is_active`, `created_at`, `status`) VALUES
(4, 'passenger', 'jc', 'jc@gmail.com', '09667168601', '$2y$10$ujqCg0S25FnC.BpSu2Mm2.J.LpeF6I8gTGDkDDCnWPeWDPYr9HUfe', 0, 1, '2025-11-21 04:52:37', 'active'),
(5, 'driver', 'Mark Anthony Bunsay', 'mark@gmail.com', '0922', '$2y$10$sbJXPd8BL2ilRq658H0slORssxzLoYmXsJJrcOgGPa87Val/rgR8m', 0, 1, '2025-11-21 04:53:27', 'active'),
(6, 'admin', 'vincet dipasupil', 'vincent@gmail.com', '09667168601', '$2y$10$WLIKba1tYe67IBoveb.6EepHeLjkuNhLyTJtprpnk0erw0o/dH05q', 0, 1, '2025-11-21 04:54:12', 'active'),
(7, 'passenger', 'JERICK ANDREI MOJICA HERRERA', 'jerickandreiherrera@gmail.com', '0997', '$2y$10$0TioMYtqnKKPUZtb9yPUtuzzrribdrs8CnuOvHQKFLE6IYe.emBQi', 0, 1, '2025-11-21 04:56:23', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `driver_attendance`
--
ALTER TABLE `driver_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_attendance_driver` (`driver_id`),
  ADD KEY `fk_attendance_user` (`user_id`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_rfid_uid` (`rfid_uid`);

--
-- Indexes for table `driver_reports`
--
ALTER TABLE `driver_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_report_user` (`user_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_read` (`is_read`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_read` (`is_read`);

--
-- Indexes for table `rfid_drivers`
--
ALTER TABLE `rfid_drivers`
  ADD PRIMARY KEY (`driver_id`),
  ADD UNIQUE KEY `rfid_uid` (`rfid_uid`),
  ADD KEY `fk_driver_user` (`user_id`),
  ADD KEY `idx_rfid_uid` (`rfid_uid`),
  ADD KEY `idx_verification` (`verification_status`);

--
-- Indexes for table `tricycle_bookings`
--
ALTER TABLE `tricycle_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_booking_user` (`user_id`),
  ADD KEY `fk_booking_driver` (`driver_id`),
  ADD KEY `idx_driver_status` (`driver_id`,`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `driver_attendance`
--
ALTER TABLE `driver_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `driver_reports`
--
ALTER TABLE `driver_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rfid_drivers`
--
ALTER TABLE `rfid_drivers`
  MODIFY `driver_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tricycle_bookings`
--
ALTER TABLE `tricycle_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `driver_attendance`
--
ALTER TABLE `driver_attendance`
  ADD CONSTRAINT `fk_attendance_driver` FOREIGN KEY (`driver_id`) REFERENCES `rfid_drivers` (`driver_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `driver_reports`
--
ALTER TABLE `driver_reports`
  ADD CONSTRAINT `fk_report_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `rfid_drivers`
--
ALTER TABLE `rfid_drivers`
  ADD CONSTRAINT `fk_driver_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `tricycle_bookings`
--
ALTER TABLE `tricycle_bookings`
  ADD CONSTRAINT `fk_booking_driver` FOREIGN KEY (`driver_id`) REFERENCES `rfid_drivers` (`driver_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
