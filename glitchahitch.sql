-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 09, 2025 at 09:45 AM
-- Server version: 8.0.43-0ubuntu0.24.04.1
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `glitchahitch`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_errors`
--

CREATE TABLE `app_errors` (
  `id` bigint UNSIGNED NOT NULL,
  `page` varchar(255) NOT NULL,
  `endpoint` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `errno` int DEFAULT NULL,
  `file` varchar(255) DEFAULT NULL,
  `line` int DEFAULT NULL,
  `severity` varchar(32) DEFAULT NULL,
  `context_snip` text,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` bigint UNSIGNED NOT NULL,
  `ride_match_id` bigint UNSIGNED NOT NULL,
  `rater_user_id` bigint UNSIGNED NOT NULL,
  `ratee_user_id` bigint UNSIGNED NOT NULL,
  `role` enum('driver','passenger') NOT NULL,
  `rating` tinyint UNSIGNED NOT NULL,
  `comment` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` bigint UNSIGNED NOT NULL,
  `ip` varbinary(16) NOT NULL,
  `endpoint` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hits` int UNSIGNED NOT NULL DEFAULT '0',
  `window_start` timestamp NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rate_limits`
--

INSERT INTO `rate_limits` (`id`, `ip`, `endpoint`, `hits`, `window_start`) VALUES
(1, 0xac3bd01c, 'ride_create', 1, '2025-08-20 10:00:00'),
(2, 0xac3bd01c, 'ride_create', 1, '2025-08-20 16:19:00'),
(3, 0xac3bd01c, 'ride_create', 1, '2025-08-20 23:05:00');

-- --------------------------------------------------------

--
-- Table structure for table `rides`
--

CREATE TABLE `rides` (
  `id` bigint UNSIGNED NOT NULL,
  `type` enum('offer','request') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ride_datetime` datetime DEFAULT NULL,
  `ride_end_datetime` datetime DEFAULT NULL,
  `seats` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `package_only` tinyint(1) NOT NULL DEFAULT '0',
  `note` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('open','matched','inprogress','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `confirmed_match_id` bigint UNSIGNED DEFAULT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) NOT NULL DEFAULT '0',
  `from_lat` decimal(9,6) DEFAULT NULL,
  `from_lng` decimal(9,6) DEFAULT NULL,
  `to_lat` decimal(9,6) DEFAULT NULL,
  `to_lng` decimal(9,6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rides`
--

INSERT INTO `rides` (`id`, `type`, `from_text`, `to_text`, `ride_datetime`, `ride_end_datetime`, `seats`, `package_only`, `note`, `phone`, `whatsapp`, `status`, `confirmed_match_id`, `user_id`, `created_at`, `updated_at`, `deleted`, `from_lat`, `from_lng`, `to_lat`, `to_lng`) VALUES
(1, 'offer', 'boro park', 'monsey', NULL, NULL, 1, 0, '', '(845) 244-1202', '', 'cancelled', NULL, NULL, '2025-08-20 06:00:22', NULL, 1, NULL, NULL, NULL, NULL),
(2, 'request', 'monticello ny', 'monsey ny', NULL, NULL, 1, 0, 'i need a ride sometime next week. latest tuesday', '18454133056', '8452441202', 'cancelled', NULL, NULL, '2025-08-20 12:19:41', NULL, 1, NULL, NULL, NULL, NULL),
(3, 'offer', 'boro park', 'monsey ny', NULL, NULL, 1, 0, 'test', '', '8452441202', 'matched', NULL, NULL, '2025-08-20 19:05:16', NULL, 0, NULL, NULL, NULL, NULL),
(4, 'request', 'monticello ny', 'monsey ny', NULL, NULL, 1, 0, 'test ride with user id', '+18452441202', '8452441202', 'open', NULL, 2, '2025-08-21 06:56:02', NULL, 0, NULL, NULL, NULL, NULL),
(5, 'offer', 'boro park', 'monsey ny', NULL, NULL, 2, 0, '', '', '', 'cancelled', NULL, 2, '2025-08-21 07:55:40', NULL, 1, NULL, NULL, NULL, NULL),
(6, 'offer', 'boro park', 'monsey ny', '2025-08-21 04:06:00', NULL, 1, 0, '', '', '', 'matched', NULL, 2, '2025-08-21 08:07:05', NULL, 0, NULL, NULL, NULL, NULL),
(7, 'offer', 'boro park', 'monsey ny', NULL, NULL, 1, 0, '', '', '', 'open', NULL, 2, '2025-08-21 08:08:37', NULL, 0, NULL, NULL, NULL, NULL),
(8, 'offer', 'boro park', 'monsey ny', '2025-08-21 04:36:00', NULL, 1, 0, '', '', '', 'open', NULL, 2, '2025-08-21 08:36:24', NULL, 0, NULL, NULL, NULL, NULL),
(9, 'offer', 'boro park', 'monsey ny', NULL, NULL, 1, 0, '', '', '', 'open', NULL, 2, '2025-08-21 09:00:27', NULL, 0, NULL, NULL, NULL, NULL),
(10, 'offer', 'boro park', 'monsey ny', NULL, NULL, 1, 0, '', '', '', 'open', NULL, 2, '2025-08-21 09:01:29', NULL, 0, NULL, NULL, NULL, NULL),
(11, 'offer', 'boro park', 'monsey ny', NULL, NULL, 1, 0, '', '', '', 'open', NULL, 2, '2025-08-21 09:01:53', NULL, 0, NULL, NULL, NULL, NULL),
(12, 'offer', 'monticello ny', 'monsey ny', '2025-08-21 05:02:00', NULL, 2, 0, '', '8454133', '', 'open', NULL, 2, '2025-08-21 09:03:13', NULL, 0, NULL, NULL, NULL, NULL),
(13, 'offer', 'monsey', 'lakewood', '2025-10-06 09:33:00', NULL, 1, 0, '', '8454133056', '18452441202', 'cancelled', NULL, 3, '2025-10-06 01:34:03', NULL, 1, NULL, NULL, NULL, NULL),
(14, 'request', 'monsey', 'lakewood', '2025-10-06 09:21:00', NULL, 1, 0, '', '8454133056', '', 'completed', NULL, 3, '2025-10-06 01:35:41', '2025-10-09 09:16:02', 0, NULL, NULL, NULL, NULL),
(15, 'offer', 'monsey', 'lakewood', NULL, NULL, 1, 0, 'test', '8454133056', '', 'matched', 13, 4, '2025-10-06 06:02:31', '2025-10-09 02:29:41', 0, NULL, NULL, NULL, NULL),
(16, 'request', 'monsey', 'lakewood', '2025-10-06 03:26:00', NULL, 1, 0, '', '8454133056', '', 'open', NULL, 3, '2025-10-06 07:26:21', NULL, 0, NULL, NULL, NULL, NULL),
(17, 'offer', 'monsey', 'lakewood nj', '2025-10-08 22:42:00', NULL, 2, 0, 'im offering a ride from ...', '8454133056', '18452441202', 'open', NULL, 4, '2025-10-09 02:43:00', '2025-10-09 02:43:00', 0, NULL, NULL, NULL, NULL),
(18, 'offer', 'monsey', 'lakewood', NULL, NULL, 1, 0, NULL, '8454133056', NULL, 'open', NULL, 3, '2025-10-09 05:49:19', '2025-10-09 05:49:19', 0, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ride_matches`
--

CREATE TABLE `ride_matches` (
  `id` bigint UNSIGNED NOT NULL,
  `ride_id` bigint UNSIGNED NOT NULL,
  `driver_user_id` bigint UNSIGNED NOT NULL,
  `passenger_user_id` bigint UNSIGNED NOT NULL,
  `status` enum('pending','accepted','confirmed','inprogress','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `confirmed_at` timestamp NULL DEFAULT NULL
) ;

--
-- Dumping data for table `ride_matches`
--

INSERT INTO `ride_matches` (`id`, `ride_id`, `driver_user_id`, `passenger_user_id`, `status`, `created_at`, `updated_at`, `confirmed_at`) VALUES
(2, 6, 2, 3, 'accepted', '2025-10-06 04:28:38', '2025-10-06 04:28:38', NULL),
(3, 14, 4, 3, 'completed', '2025-10-06 04:31:51', '2025-10-09 09:16:02', NULL),
(4, 8, 2, 3, 'pending', '2025-10-06 04:41:28', '2025-10-06 04:41:28', NULL),
(5, 8, 3, 4, 'cancelled', '2025-10-06 06:00:35', '2025-10-09 03:31:20', NULL),
(6, 12, 2, 4, 'pending', '2025-10-06 06:02:11', '2025-10-06 06:02:11', NULL),
(7, 4, 4, 2, 'pending', '2025-10-06 06:02:52', '2025-10-06 06:02:52', NULL),
(8, 7, 2, 3, 'pending', '2025-10-06 07:33:01', '2025-10-09 01:47:04', NULL),
(9, 4, 3, 2, 'pending', '2025-10-06 07:41:58', '2025-10-06 07:41:58', NULL),
(13, 15, 4, 3, 'accepted', '2025-10-09 02:12:13', '2025-10-09 02:29:41', '2025-10-09 02:29:41'),
(14, 17, 4, 2, 'pending', '2025-10-09 06:44:03', '2025-10-09 06:44:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ride_ratings`
--

CREATE TABLE `ride_ratings` (
  `id` bigint UNSIGNED NOT NULL,
  `ride_id` bigint UNSIGNED NOT NULL,
  `rater_user_id` bigint UNSIGNED NOT NULL,
  `target_user_id` bigint UNSIGNED NOT NULL,
  `role` enum('driver','passenger') NOT NULL,
  `stars` tinyint UNSIGNED NOT NULL,
  `comment` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rides_offered_count` int NOT NULL DEFAULT '0',
  `rides_requested_count` int NOT NULL DEFAULT '0',
  `rides_given_count` int NOT NULL DEFAULT '0',
  `rides_received_count` int NOT NULL DEFAULT '0',
  `score` int NOT NULL DEFAULT '100',
  `driver_rating_sum` int NOT NULL DEFAULT '0',
  `driver_rating_count` int NOT NULL DEFAULT '0',
  `passenger_rating_sum` int NOT NULL DEFAULT '0',
  `passenger_rating_count` int NOT NULL DEFAULT '0',
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `username` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `display_name`, `rides_offered_count`, `rides_requested_count`, `rides_given_count`, `rides_received_count`, `score`, `driver_rating_sum`, `driver_rating_count`, `passenger_rating_sum`, `passenger_rating_count`, `is_admin`, `username`, `phone`, `whatsapp`, `created_at`) VALUES
(1, 'admin@shiyaswebsite.com', '$2y$10$eA3qk4x5v0osTTo9JQz2V.5t2s5sVZb6c8z2b3V4qUO8s2o7m4mIy', 'Site Admin', 0, 0, 0, 0, 100, 0, 0, 0, 0, 1, NULL, NULL, NULL, '2025-08-20 15:15:25'),
(2, 'shermanshiya@gmail.com', '$2y$10$Be4xsnD8R5.DPTtFlNDBi.u81ITZ5PrQLviJfy5p4Sr0dgIN2Ntru', 'shiya s', 0, 0, 0, 0, 100, 0, 0, 0, 0, 1, NULL, NULL, NULL, '2025-08-20 18:28:43'),
(3, 'shermanshiya+1@gmail.com', '$2y$10$kMLK94fg659GpS70vTVljeDQB7FMVlJBlRV0UHxbI6otHZX6HrIZy', 'Shiya Sherman', 1, 0, 0, 1, 100, 0, 0, 0, 0, 0, NULL, '8454133056', '8452441202', '2025-10-06 00:58:44'),
(4, 'shermanshiya+2@gmail.com', '$2y$10$tOtEXJv/z5JqSlP.VYJBneDQU1roki2Pe02k373H.3sI6K6dEW1ES', 'dev tester', 0, 0, 1, 0, 100, 0, 0, 0, 0, 0, NULL, NULL, NULL, '2025-10-06 04:31:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_errors`
--
ALTER TABLE `app_errors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_page` (`page`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_feedback_one_per_rater` (`ride_match_id`,`rater_user_id`),
  ADD KEY `fk_fb_rm` (`ride_match_id`),
  ADD KEY `idx_fb_ratee` (`ratee_user_id`),
  ADD KEY `fk_fb_rater` (`rater_user_id`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ip_endpoint_window` (`ip`,`endpoint`,`window_start`);

--
-- Indexes for table `rides`
--
ALTER TABLE `rides`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rides_type` (`type`),
  ADD KEY `idx_rides_status` (`status`),
  ADD KEY `idx_rides_datetime` (`ride_datetime`),
  ADD KEY `idx_rides_from_to` (`from_text`,`to_text`),
  ADD KEY `idx_rides_user_id` (`user_id`),
  ADD KEY `fk_rides_confirmed_match` (`confirmed_match_id`);

--
-- Indexes for table `ride_matches`
--
ALTER TABLE `ride_matches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ride_pair` (`ride_id`,`driver_user_id`,`passenger_user_id`),
  ADD UNIQUE KEY `ux_one_confirmed_per_ride` (((case when (`status` = _utf8mb4'confirmed') then `ride_id` else NULL end))),
  ADD KEY `fk_rm_ride` (`ride_id`),
  ADD KEY `idx_rm_driver` (`driver_user_id`),
  ADD KEY `idx_rm_passenger` (`passenger_user_id`),
  ADD KEY `idx_rm_ride_status_created` (`ride_id`,`status`,`created_at`);

--
-- Indexes for table `ride_ratings`
--
ALTER TABLE `ride_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_riderate` (`ride_id`,`rater_user_id`,`target_user_id`),
  ADD KEY `idx_target` (`target_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_uq` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `app_errors`
--
ALTER TABLE `app_errors`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rides`
--
ALTER TABLE `rides`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `ride_matches`
--
ALTER TABLE `ride_matches`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ride_ratings`
--
ALTER TABLE `ride_ratings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_fb_ratee` FOREIGN KEY (`ratee_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_fb_rater` FOREIGN KEY (`rater_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_fb_rm` FOREIGN KEY (`ride_match_id`) REFERENCES `ride_matches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rides`
--
ALTER TABLE `rides`
  ADD CONSTRAINT `fk_rides_confirmed_match` FOREIGN KEY (`confirmed_match_id`) REFERENCES `ride_matches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rides_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ride_matches`
--
ALTER TABLE `ride_matches`
  ADD CONSTRAINT `fk_rm_driver_user` FOREIGN KEY (`driver_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_rm_passenger_user` FOREIGN KEY (`passenger_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_rm_ride` FOREIGN KEY (`ride_id`) REFERENCES `rides` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
