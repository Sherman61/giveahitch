-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 23, 2025 at 03:02 AM
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
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ride_id` bigint UNSIGNED DEFAULT NULL,
  `match_id` bigint UNSIGNED DEFAULT NULL,
  `actor_user_id` bigint UNSIGNED DEFAULT NULL,
  `actor_display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_settings`
--

CREATE TABLE `notification_settings` (
  `user_id` bigint UNSIGNED NOT NULL,
  `ride_activity` tinyint(1) NOT NULL DEFAULT '1',
  `match_activity` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_settings`
--

INSERT INTO `notification_settings` (`user_id`, `ride_activity`, `match_activity`, `updated_at`) VALUES
(4, 1, 1, '2025-10-23 02:29:58');

-- --------------------------------------------------------

--
-- Table structure for table `push_subscriptions`
--

CREATE TABLE `push_subscriptions` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `endpoint` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint_hash` binary(32) GENERATED ALWAYS AS (unhex(sha2(`endpoint`,256))) STORED,
  `p256dh` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auth` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ua` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(29, 'offer', 'monsey', 'lakewood', '2025-10-22 17:30:00', '2025-10-23 17:30:00', 1, 0, 'dev tester logged in', '8454133056', NULL, 'completed', 47, 4, '2025-10-22 21:20:30', '2025-10-22 21:26:17', 0, NULL, NULL, NULL, NULL),
(30, 'request', 'boro park', 'crown heights', '2025-10-22 17:28:00', '2025-10-23 17:28:00', 1, 0, 'request by dev tester', '8454133056', '8452441202', 'completed', 49, 4, '2025-10-22 21:28:58', '2025-10-23 02:04:49', 0, NULL, NULL, NULL, NULL),
(31, 'offer', 'monsey', 'lakewood', NULL, NULL, 1, 0, 'notifications work?', '8454133056', NULL, 'open', NULL, 4, '2025-10-23 02:23:32', '2025-10-23 02:23:32', 0, NULL, NULL, NULL, NULL),
(32, 'request', 'monsey', 'crown heights', '2025-10-22 22:30:00', NULL, 1, 0, 'notifications after setting =1', '8454133056', NULL, 'open', NULL, 4, '2025-10-23 02:30:36', '2025-10-23 02:30:36', 0, NULL, NULL, NULL, NULL);

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
(47, 29, 4, 3, 'completed', '2025-10-22 21:20:52', '2025-10-22 21:26:17', '2025-10-22 21:25:16'),
(49, 30, 3, 4, 'completed', '2025-10-22 21:29:46', '2025-10-23 02:04:49', '2025-10-23 02:03:22');

-- --------------------------------------------------------

--
-- Table structure for table `ride_ratings`
--

CREATE TABLE `ride_ratings` (
  `id` bigint UNSIGNED NOT NULL,
  `ride_id` bigint UNSIGNED NOT NULL,
  `match_id` bigint UNSIGNED NOT NULL,
  `rater_user_id` bigint UNSIGNED NOT NULL,
  `rated_user_id` bigint UNSIGNED NOT NULL,
  `rater_role` enum('driver','passenger') NOT NULL,
  `rated_role` enum('driver','passenger') NOT NULL DEFAULT 'driver',
  `stars` tinyint UNSIGNED NOT NULL,
  `comment` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `ride_ratings`
--

INSERT INTO `ride_ratings` (`id`, `ride_id`, `match_id`, `rater_user_id`, `rated_user_id`, `rater_role`, `rated_role`, `stars`, `comment`, `created_at`) VALUES
(6, 29, 47, 3, 4, 'passenger', 'driver', 5, 'thanks a million for the ride.', '2025-10-22 21:26:53'),
(7, 29, 47, 4, 3, 'driver', 'passenger', 5, 'chill dude, we had a nice convo', '2025-10-22 21:27:25'),
(8, 30, 49, 3, 4, 'driver', 'passenger', 5, NULL, '2025-10-23 02:05:10'),
(9, 30, 49, 4, 3, 'passenger', 'driver', 5, NULL, '2025-10-23 02:05:17');

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
  `contact_privacy` tinyint NOT NULL DEFAULT '1',
  `message_privacy` tinyint NOT NULL DEFAULT '1',
  `reset_token_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `reset_token_attempts` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `display_name`, `rides_offered_count`, `rides_requested_count`, `rides_given_count`, `rides_received_count`, `score`, `driver_rating_sum`, `driver_rating_count`, `passenger_rating_sum`, `passenger_rating_count`, `is_admin`, `username`, `phone`, `whatsapp`, `contact_privacy`, `message_privacy`, `reset_token_hash`, `reset_token_expires_at`, `reset_token_attempts`, `created_at`) VALUES
(1, 'admin@shiyaswebsite.com', '$2y$10$eA3qk4x5v0osTTo9JQz2V.5t2s5sVZb6c8z2b3V4qUO8s2o7m4mIy', 'Site Admin', 0, 0, 0, 0, 100, 0, 0, 0, 0, 1, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-08-20 15:15:25'),
(2, 'shermanshiya@gmail.com', '$2y$10$Be4xsnD8R5.DPTtFlNDBi.u81ITZ5PrQLviJfy5p4Sr0dgIN2Ntru', 'shiya s', 1, 0, 0, 0, 100, 0, 0, 0, 0, 0, NULL, '7181234567', NULL, 1, 1, NULL, NULL, 0, '2025-08-20 18:28:43'),
(3, 'shermanshiya+1@gmail.com', '$2y$10$kMLK94fg659GpS70vTVljeDQB7FMVlJBlRV0UHxbI6otHZX6HrIZy', 'Shiya Sherman', 2, 0, 1, 3, 700, 5, 1, 16, 4, 0, NULL, '8454133056', '8452441202', 1, 1, NULL, NULL, 0, '2025-10-06 00:58:44'),
(4, 'shermanshiya+2@gmail.com', '$2y$10$tOtEXJv/z5JqSlP.VYJBneDQU1roki2Pe02k373H.3sI6K6dEW1ES', 'dev tester', 2, 2, 3, 1, 700, 5, 1, 5, 1, 0, NULL, '8454133056', NULL, 1, 1, NULL, NULL, 0, '2025-10-06 04:31:37'),
(5, 'rafaelblum1@gmail.com', '$2y$10$SxS7K1p4t4DO4L0/YjBASOOEJFTVP6HVv3Q5V175L89baNUNTj4lq', 'Rafael Blum', 1, 1, 1, 1, 400, 0, 0, 0, 0, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-10-10 06:09:56'),
(6, 'shia4454@gmail.com', '$2y$10$MxCWTzRiqQe8n65SI.9k6e1q9Pb7Z23y0qyG2YPxPXgfWLwjF9j7.', 'Shia', 0, 1, 1, 1, 500, 5, 1, 5, 1, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-10-10 06:19:47'),
(7, 'shermanshiya+3@gmail.com', '$2y$10$nbopzZ1N.E//OZj4lXR/W.SWzG89dyYKPKyQ7fz30DZvNWRt68OlS', 'dev tester 2', 1, 1, 0, 0, 100, 0, 0, 0, 0, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-10-10 09:37:50'),
(8, '1inboxinvite@gmail.com', '$2y$10$QvTSmdFoV9Q52HzletbXCe0FoZ1kh34g7Dhx/Nw1YdTwVoyO1qUZq', 'DYM', 3, 0, 0, 0, 100, 0, 0, 0, 0, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-10-10 19:47:14'),
(9, 'mottyatias15@gmail.com', '$2y$10$t55ACpnX2mRcuGZJiH49SeGWpOrH7dIlM4Z2lnmQ3fQdC1yV/W/1e', 'Shia atias', 0, 0, 0, 0, 100, 0, 0, 0, 0, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-10-12 01:18:26');

-- --------------------------------------------------------

--
-- Table structure for table `user_messages`
--

CREATE TABLE `user_messages` (
  `id` bigint UNSIGNED NOT NULL,
  `thread_id` bigint UNSIGNED NOT NULL,
  `sender_user_id` bigint UNSIGNED NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_message_threads`
--

CREATE TABLE `user_message_threads` (
  `id` bigint UNSIGNED NOT NULL,
  `user_a_id` bigint UNSIGNED NOT NULL,
  `user_b_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_message_id` bigint UNSIGNED DEFAULT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `user_a_unread` int NOT NULL DEFAULT '0',
  `user_b_unread` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_message_threads`
--

INSERT INTO `user_message_threads` (`id`, `user_a_id`, `user_b_id`, `created_at`, `updated_at`, `last_message_id`, `last_message_at`, `user_a_unread`, `user_b_unread`) VALUES
(1, 7, 8, '2025-10-11 23:52:20', '2025-10-11 23:52:27', 1, '2025-10-11 23:52:27', 0, 1),
(2, 4, 7, '2025-10-11 23:54:54', '2025-10-12 09:08:34', 60, '2025-10-12 09:08:30', 0, 0),
(3, 3, 4, '2025-10-12 00:00:25', '2025-10-12 05:02:50', 39, '2025-10-12 05:02:50', 1, 0),
(4, 2, 7, '2025-10-12 04:22:09', '2025-10-12 05:25:13', 42, '2025-10-12 05:25:13', 0, 1),
(5, 2, 4, '2025-10-12 05:25:30', '2025-10-12 05:39:31', 56, '2025-10-12 05:39:27', 0, 0);

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
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_notifications_unread` (`user_id`,`is_read`);

--
-- Indexes for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_endpoint_hash` (`endpoint_hash`),
  ADD KEY `idx_user` (`user_id`);

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
  ADD UNIQUE KEY `uq_riderate` (`ride_id`,`rater_user_id`,`rated_user_id`),
  ADD UNIQUE KEY `uq_rr_rater_per_match` (`match_id`,`rater_user_id`),
  ADD KEY `idx_target` (`rated_user_id`),
  ADD KEY `idx_rr_match` (`match_id`),
  ADD KEY `idx_rr_rated` (`rated_user_id`),
  ADD KEY `idx_rr_rater` (`rater_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_uq` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_messages`
--
ALTER TABLE `user_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_message_thread` (`thread_id`),
  ADD KEY `idx_message_sender` (`sender_user_id`),
  ADD KEY `idx_message_created` (`created_at`);

--
-- Indexes for table `user_message_threads`
--
ALTER TABLE `user_message_threads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_message_pair` (`user_a_id`,`user_b_id`),
  ADD KEY `idx_thread_last_message` (`last_message_at`),
  ADD KEY `idx_thread_user_a` (`user_a_id`),
  ADD KEY `idx_thread_user_b` (`user_b_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `app_errors`
--
ALTER TABLE `app_errors`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `ride_matches`
--
ALTER TABLE `ride_matches`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ride_ratings`
--
ALTER TABLE `ride_ratings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `user_messages`
--
ALTER TABLE `user_messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `user_message_threads`
--
ALTER TABLE `user_message_threads`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD CONSTRAINT `fk_notification_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD CONSTRAINT `fk_ps_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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

--
-- Constraints for table `ride_ratings`
--
ALTER TABLE `ride_ratings`
  ADD CONSTRAINT `fk_rr_match` FOREIGN KEY (`match_id`) REFERENCES `ride_matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rr_rated` FOREIGN KEY (`rated_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_rr_rater` FOREIGN KEY (`rater_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `user_messages`
--
ALTER TABLE `user_messages`
  ADD CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_message_thread` FOREIGN KEY (`thread_id`) REFERENCES `user_message_threads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_message_threads`
--
ALTER TABLE `user_message_threads`
  ADD CONSTRAINT `fk_thread_user_a` FOREIGN KEY (`user_a_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_thread_user_b` FOREIGN KEY (`user_b_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(255) NOT NULL,
  code CHAR(6) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ip VARBINARY(16) DEFAULT NULL,
  ua VARCHAR(255) DEFAULT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_email_expires (email, expires_at),
  KEY idx_user_expires (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
