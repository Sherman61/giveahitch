-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 30, 2026 at 03:09 AM
-- Server version: 8.0.45-0ubuntu0.24.04.1
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
-- Table structure for table `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `api_tokens`
--

INSERT INTO `api_tokens` (`id`, `user_id`, `token_hash`, `expires_at`, `created_at`) VALUES
(1, 2, '2b84b2fa7e4bab4014f7633773b1de75b021407a0a0df26efc7a2049ab96061d', '2025-11-12 01:04:08', '2025-11-11 19:04:08'),
(2, 2, '153529a8e9c2f5fd86f476355c06fa0ea4479daf53fb044c9296fb47dd2f63bd', '2025-11-12 01:13:09', '2025-11-11 19:13:09'),
(3, 2, 'cadb5778bbb8a74adb87574d26943c78d0c7c25c20a871ff0d8e6e320d890a07', '2025-11-12 01:27:52', '2025-11-11 19:27:52'),
(4, 2, '54035f763d55c9ed0547543582033276ffd718026019437543a3c178d53ced11', '2025-11-12 23:21:41', '2025-11-12 17:21:41'),
(5, 2, 'b22c81042084c35998d0d47758fb65763efd86ba106c22a020e44691438cb89a', '2025-11-12 23:36:39', '2025-11-12 17:36:39'),
(6, 2, '8a58c9d36242e3e0fcba2cb30db8c6974dc9e7fccd4d65594eab3475f7d53ee2', '2025-11-12 23:50:48', '2025-11-12 17:50:48'),
(7, 2, 'a19ed3b934133fd4ac21577492a9f3e3330b1fc891eba0bb3ab68d54c64b4ffe', '2025-11-13 00:07:03', '2025-11-12 18:07:03');

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

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `body`, `ride_id`, `match_id`, `actor_user_id`, `actor_display_name`, `metadata`, `is_read`, `created_at`, `read_at`) VALUES
(1, 4, 'ride_update', 'Ride confirmed', 'Your ride for tonight is confirmed.', 123, NULL, 7, 'Dispatcher', '{\"url\": \"/rides.php?id=123\", \"priority\": \"high\"}', 1, '2025-10-23 04:47:37', '2025-10-23 04:49:27'),
(2, 4, 'ride_update', 'Ride confirmed', 'Your ride for tonight is confirmed.', 123, NULL, 7, 'Testing', '{\"url\": \"/rides.php?id=123\", \"priority\": \"high\"}', 1, '2025-10-23 04:51:30', '2025-12-29 00:23:01'),
(3, 4, 'ride_match_requested', 'New request for your ride', 'Shiya Sherman asked to join monsey → lakewood.', 33, 51, 3, 'Shiya Sherman', '{\"status\": \"pending\", \"match_id\": 51, \"ride_type\": \"offer\"}', 1, '2025-10-23 05:00:40', '2025-10-23 07:29:10'),
(4, 4, 'ride_update', 'Ride Notification', 'Your ride for tonight is confirmed.', 123, NULL, 7, 'Dispatcher', '{\"url\": \"/rides.php?id=123\", \"priority\": \"high\"}', 1, '2025-10-23 07:28:58', '2025-12-28 23:59:03'),
(5, 4, 'ride_match_withdrawn', 'Ride request withdrawn', 'Shiya Sherman withdrew their request for monsey → lakewood.', 33, 51, 3, 'Shiya Sherman', '{\"status\": \"cancelled\", \"match_id\": 51, \"ride_type\": \"offer\"}', 1, '2025-10-28 06:03:51', '2025-12-29 00:23:01'),
(6, 2, 'ride_match_requested', 'New request for your ride', 'Shiya Sherman asked to join monsey → crown heights.', 34, 52, 3, 'Shiya Sherman', '{\"status\": \"pending\", \"match_id\": 52, \"ride_type\": \"offer\"}', 1, '2025-10-28 09:03:34', '2025-10-28 09:34:39'),
(7, 4, 'ride_match_requested', 'New request for your ride', 'Shiya Sherman asked to join monsey → lakewood.', 31, 55, 3, 'Shiya Sherman', '{\"url\": \"/manage_ride.php?id=31\", \"status\": \"pending\", \"match_id\": 55, \"ride_type\": \"offer\"}', 1, '2025-10-28 09:15:10', '2025-10-28 09:15:25'),
(8, 4, 'ride_match_requested', 'New request for your ride', 'shiya s asked to join monsey → lakewood.', 31, 56, 2, 'shiya s', '{\"url\": \"/manage_ride.php?id=31\", \"status\": \"pending\", \"match_id\": 56, \"ride_type\": \"offer\"}', 1, '2025-11-12 23:37:10', '2025-11-13 05:12:36'),
(9, 2, 'ride_match_requested', 'New request for your ride', 'dev tester asked to join Monsey → Crown heights.', 36, 58, 4, 'dev tester', '{\"url\": \"/manage_ride.php?id=36\", \"status\": \"pending\", \"match_id\": 58, \"ride_type\": \"request\"}', 1, '2025-11-13 05:13:29', '2025-12-29 01:43:41'),
(10, 2, 'ride_match_requested', 'New request for your ride', 'dev tester asked to join Monsey → Boro park.', 35, 60, 4, 'dev tester', '{\"url\": \"/manage_ride.php?id=35\", \"status\": \"pending\", \"match_id\": 60, \"ride_type\": \"offer\"}', 1, '2025-11-13 05:14:23', '2025-12-29 01:43:41'),
(11, 2, 'system_push_test', 'Push notification test', 'Push notification test from the admin console.', NULL, NULL, NULL, NULL, '{\"url\": \"/notifications.php\", \"source\": \"mobile_push_test\"}', 1, '2025-11-13 09:58:30', '2025-12-29 01:43:41'),
(12, 2, 'system_push_test', 'Push notification test', 'Push notification test from the admin console.', NULL, NULL, NULL, NULL, '{\"url\": \"/notifications.php\", \"source\": \"mobile_push_test\"}', 1, '2025-11-13 09:58:31', '2025-12-29 01:43:41'),
(13, 2, 'system_push_test', 'Push notification test', 'Push notification test from the admin console.', NULL, NULL, NULL, NULL, '{\"url\": \"/notifications.php\", \"source\": \"mobile_push_test\"}', 1, '2025-11-13 09:58:38', '2025-12-29 01:43:41'),
(14, 2, 'system_push_test', 'Push notification test', 'Push notification test from the admin console.', NULL, NULL, NULL, NULL, '{\"url\": \"/notifications.php\", \"source\": \"mobile_push_test\"}', 1, '2025-11-13 10:11:40', '2025-12-29 01:43:41'),
(15, 2, 'system_push_test', 'Push notification test', 'Push notification test from the admin console.', NULL, NULL, NULL, NULL, '{\"url\": \"/notifications.php\", \"source\": \"mobile_push_test\"}', 1, '2025-11-13 10:20:31', '2025-12-29 01:43:41'),
(16, 2, 'system_push_test', 'Push notification test', 'Push notification test from the admin console.', NULL, NULL, NULL, NULL, '{\"url\": \"/notifications.php\", \"source\": \"mobile_push_test\"}', 1, '2025-11-13 10:20:35', '2025-12-29 01:43:41'),
(17, 2, 'system_push_test', 'Push notification test', 'Push notification test from the admin console.', NULL, NULL, NULL, NULL, '{\"url\": \"/notifications.php\", \"source\": \"mobile_push_test\"}', 1, '2025-12-29 00:22:13', '2025-12-29 01:43:41'),
(18, 2, 'system_push_test', 'Push notification test', 'Push notification test from the admin console.', NULL, NULL, NULL, NULL, '{\"url\": \"/notifications.php\", \"source\": \"mobile_push_test\"}', 1, '2025-12-29 00:22:29', '2025-12-29 01:43:41'),
(19, 4, 'ride_match_requested', 'New request for your ride', 'Shiya s asked to join monsey → lakewood nj.', 41, 61, 2, 'Shiya s', '{\"url\": \"/manage_ride.php?id=41\", \"status\": \"pending\", \"match_id\": 61, \"ride_type\": \"offer\"}', 1, '2025-12-30 05:17:13', '2025-12-30 05:46:46'),
(20, 2, 'ride_match_requested', 'New request for your ride', 'dev tester asked to join Bp → Monsey.', 42, 62, 4, 'dev tester', '{\"url\": \"/manage_ride.php?id=42\", \"status\": \"pending\", \"match_id\": 62, \"ride_type\": \"offer\"}', 1, '2025-12-30 05:45:45', '2026-03-30 01:28:14'),
(21, 2, 'system_push_test', 'Push notification test', 'Push notification test from the admin console.', NULL, NULL, NULL, NULL, '{\"url\": \"/notifications.php\", \"source\": \"mobile_push_test\"}', 0, '2026-03-24 02:21:38', NULL),
(22, 2, 'system_push_test', 'Push notification test', 'Push notification test from the admin console.', NULL, NULL, NULL, NULL, '{\"url\": \"/notifications.php\", \"source\": \"mobile_push_test\"}', 0, '2026-03-24 04:15:31', NULL),
(23, 2, 'system_push_test', 'Push notification test', 'Push notification test from the admin console.', NULL, NULL, NULL, NULL, '{\"url\": \"/notifications.php\", \"source\": \"mobile_push_test\"}', 0, '2026-03-24 04:20:45', NULL),
(24, 2, 'system_push_test', 'Push notification test', 'Push notification test from the admin console.', NULL, NULL, NULL, NULL, '{\"url\": \"/notifications.php\", \"source\": \"mobile_push_test\"}', 1, '2026-03-24 04:28:51', '2026-03-25 02:06:36'),
(25, 4, 'ride_match_requested', 'New request for your ride', 'Shiya s asked to join Boro park → Monsey.', 45, 64, 2, 'Shiya s', '{\"url\": \"/manage_ride.php?id=45\", \"status\": \"pending\", \"match_id\": 64, \"ride_type\": \"offer\"}', 1, '2026-03-24 04:34:32', '2026-03-24 04:35:14'),
(26, 2, 'ride_match_requested', 'New request for your ride', 'dev tester asked to join Boro park → Monsey.', 46, 66, 4, 'dev tester', '{\"url\": \"/manage_ride.php?id=46\", \"status\": \"pending\", \"match_id\": 66, \"ride_type\": \"offer\"}', 1, '2026-03-30 02:41:36', '2026-03-30 02:43:08'),
(27, 4, 'ride_match_requested', 'New request for your ride', 'Shiya s asked to join Boro park → Monsey.', 47, 68, 2, 'Shiya s', '{\"url\": \"/manage_ride.php?id=47\", \"status\": \"pending\", \"match_id\": 68, \"ride_type\": \"request\"}', 0, '2026-03-30 02:48:14', NULL);

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
(4, 1, 1, '2025-10-23 04:36:58');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` char(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `ip` varbinary(16) DEFAULT NULL,
  `ua` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `email`, `code`, `attempts`, `ip`, `ua`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 2, 'shermanshiya@gmail.com', '538234', 5, 0x18be4ece, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-11 11:23:12', NULL, '2025-11-11 06:08:12'),
(2, 2, 'shermanshiya@gmail.com', '367646', 0, 0x18be4ece, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-11 11:26:13', '2025-11-11 06:14:48', '2025-11-11 06:11:13'),
(3, 2, 'shermanshiya@gmail.com', '546845', 0, 0x18be4ece, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-11 11:30:33', '2025-11-11 06:15:56', '2025-11-11 06:15:33'),
(4, 2, 'shermanshiya@gmail.com', '553503', 0, 0x18be4ece, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-11 11:31:44', '2025-11-11 06:17:00', '2025-11-11 06:16:44'),
(5, 2, 'shermanshiya@gmail.com', '588913', 0, 0x18be4ece, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-11 11:33:36', '2025-11-11 06:19:00', '2025-11-11 06:18:36');

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

--
-- Dumping data for table `push_subscriptions`
--

INSERT INTO `push_subscriptions` (`id`, `user_id`, `endpoint`, `p256dh`, `auth`, `ua`, `created_at`, `updated_at`) VALUES
(26, 5, 'https://fcm.googleapis.com/fcm/send/fmW9RfYaPOo:APA91bHCPJv5hSY1nsIrbQUr-fQei8ApqozDG_Wtn1SZdhYrHqqz06Lgoj47yReT0PgDT9o-GZf_OUMU-YnuXonI3O5Phh5JToRayWzWoFd0o8vR_s6yTjFlvyCnpoouk6Bzm4sE9owY', 'BGTf5xpYml_s18akvwvE6w5zP61OMSkdaO2D0Qt766ivkwW8WQ2A_t5zF48Ha0IqU0UXTO-Wn-r8Q8GoHpZQs0E', 'aiqnbnX97hqI2Ps2wCxLOw', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36', '2025-10-28 09:10:27', '2025-10-28 09:10:27'),
(39, 2, 'ExponentPushToken[XQsR-XHJmQvDxBCM1pv4DU]', 'BP2A.250605.031.A3:ExponentPushToken[XQsR-XHJmQvDxBCM1pv4DU]', 'ExponentPushToken[XQsR-XHJmQvDxB', 'SM-S711U', '2025-11-13 09:21:02', '2025-11-13 10:20:07'),
(57, 2, 'ExponentPushToken[mXttuLO2ZhPvcKRBbQG5HM]', 'BP2A.250605.031.A3:ExponentPushToken[mXttuLO2ZhPvcKRBbQG5HM]', 'ExponentPushToken[mXttuLO2ZhPvcK', 'SM-S711U', '2025-12-29 00:21:54', '2026-03-24 04:27:50'),
(78, 2, 'https://fcm.googleapis.com/fcm/send/exv173IUyY4:APA91bF6R_5JGfXtdxvXvXLxZ9U1Gl8gqirqHzG_Wh0ZW9nyLKn144d2FtzYoBLV8RO-cXFvfs5cR6qWpfeoa-c0GJeM6L4IyQFUHwvxnvVmjDbRpjXq800xutVcJtRoIzENnxzdnvdF', 'BIYTFdP-KDxLw9r5BLtRgpRlxcAymvrKNLA7iRL3b8VQyQstLfY5OTZH9aNjgT6u0PGdXXmxokgxRbFI4cmM3MM', 'AWRCDQoz8GZ6vWQdNHbSeg', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 04:18:58', '2026-03-30 01:27:56'),
(86, 2, 'https://fcm.googleapis.com/fcm/send/eL61-Eg7ibI:APA91bFeUZirVO6R71HF1YRQCETWCaeZgaEYA5glZFTGsmaeDFyfjZ7S55ZQ3esknqbZFYpvYrFQpoTo5VQPhrLkJRhnZCYohrPLEFUAOqpGI44AmNClbUVcALcQc7kZkTkVIAxDwubH', 'BJRxBGdU1kYu0WjI1yXsZX0nS3MsEO3PGaeTxfcIU7JJuQA1m6LmdMPG-U2Uhd8kNcXn4zkWd8Q0EQoJTCL5BIg', 'DNdFzc_UX-kkUXavHZDeWQ', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-25 02:06:32', '2026-03-25 02:06:32');

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
(32, 'request', 'monsey', 'crown heights', '2025-10-22 22:30:00', NULL, 1, 0, 'notifications after setting =1', '8454133056', NULL, 'open', NULL, 4, '2025-10-23 02:30:36', '2025-10-23 02:30:36', 0, NULL, NULL, NULL, NULL),
(33, 'offer', 'monsey', 'lakewood', NULL, NULL, 1, 0, 'i dougbt it works lets see notification', '8454133056', NULL, 'open', NULL, 4, '2025-10-23 04:54:18', '2025-10-23 04:54:18', 0, NULL, NULL, NULL, NULL),
(34, 'offer', 'monsey', 'crown heights', '2025-10-28 18:30:00', NULL, 1, 0, 'Test mobile edit', '7181234566', '8452441222', 'cancelled', NULL, 2, '2025-10-28 08:53:03', '2025-12-30 05:47:28', 1, NULL, NULL, NULL, NULL),
(35, 'offer', 'Monsey', 'Boro park', NULL, NULL, 1, 0, 'React', '8454133056', NULL, 'open', NULL, 2, '2025-11-12 23:09:28', '2025-11-12 23:09:28', 0, NULL, NULL, NULL, NULL),
(36, 'request', 'Monsey', 'Crown heights', NULL, NULL, 1, 0, 'React native app using expo', '8454133056', '8452441202', 'open', NULL, 2, '2025-11-12 23:59:21', '2025-11-12 23:59:21', 0, NULL, NULL, NULL, NULL),
(37, 'request', 'Boro park', 'Monsey', NULL, NULL, 1, 0, 'Test react native expo', '8454133056', NULL, 'open', NULL, 2, '2025-11-13 00:24:53', '2025-11-13 00:24:53', 0, NULL, NULL, NULL, NULL),
(38, 'request', 'Boro park', 'Monsey', '2025-11-12 21:45:00', '2025-11-13 22:00:00', 2, 0, 'React native ...', '8454133056', '8452441202', 'open', NULL, 2, '2025-11-13 00:37:23', '2025-11-13 00:37:23', 0, NULL, NULL, NULL, NULL),
(39, 'request', 'Bp', 'Monsey', '2025-11-13 00:19:00', '2025-11-14 05:19:00', 1, 0, '1220', '8454133056', '8452441202', 'open', NULL, 2, '2025-11-13 05:20:06', '2025-11-13 05:20:06', 0, NULL, NULL, NULL, NULL),
(40, 'offer', 'monsey', 'crown heights', '2025-11-13 03:04:00', '2025-11-14 03:04:00', 1, 0, 'web check', '8454133056', NULL, 'open', NULL, 4, '2025-11-13 08:04:47', '2025-11-13 08:04:47', 0, NULL, NULL, NULL, NULL),
(41, 'offer', 'monsey', 'lakewood nj', NULL, NULL, 2, 0, 'test', '8454133056', '99999999999999', 'matched', 61, 4, '2025-12-29 00:00:44', '2025-12-30 05:46:48', 0, NULL, NULL, NULL, NULL),
(42, 'offer', 'Bp', 'Monsey', '2025-12-30 00:44:00', '2025-12-31 00:44:00', 1, 0, 'Test from react mobile expo', '8451239876', NULL, 'cancelled', NULL, 2, '2025-12-30 05:44:47', '2026-03-25 02:06:59', 1, NULL, NULL, NULL, NULL),
(43, 'offer', 'boro park', 'monsey ny', NULL, NULL, 2, 0, 'test from website', '8454133056', '8452441202', 'open', NULL, 2, '2026-01-31 21:01:49', '2026-01-31 21:01:49', 0, NULL, NULL, NULL, NULL),
(44, 'request', 'Boro park', 'Monsey', '2026-03-23 21:25:00', NULL, 1, 0, 'Test', '8454133056', NULL, 'open', NULL, 4, '2026-03-24 01:25:54', '2026-03-24 01:25:54', 0, NULL, NULL, NULL, NULL),
(45, 'offer', 'Boro park', 'Monsey', NULL, NULL, 1, 0, 'Test from mobile', '8452441202', NULL, 'open', NULL, 4, '2026-03-24 04:33:56', '2026-03-24 04:33:56', 0, NULL, NULL, NULL, NULL),
(46, 'offer', 'Boro park', 'Monsey', '2026-03-29 21:51:00', '2026-03-30 21:47:00', 2, 0, 'Test', '8454133056', '8452441202', 'completed', 66, 2, '2026-03-30 01:47:35', '2026-03-30 02:44:37', 0, NULL, NULL, NULL, NULL),
(47, 'request', 'Boro park', 'Monsey', NULL, '2026-03-30 22:30:00', 1, 0, 'Testing', '8454133056', NULL, 'open', NULL, 4, '2026-03-30 02:40:13', '2026-03-30 02:40:13', 0, NULL, NULL, NULL, NULL);

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
(49, 30, 3, 4, 'completed', '2025-10-22 21:29:46', '2025-10-23 02:04:49', '2025-10-23 02:03:22'),
(51, 33, 4, 3, 'cancelled', '2025-10-23 05:00:40', '2025-10-28 06:03:51', NULL),
(52, 34, 2, 3, 'pending', '2025-10-28 09:03:34', '2025-10-28 09:03:34', NULL),
(55, 31, 4, 3, 'pending', '2025-10-28 09:15:10', '2025-10-28 09:15:10', NULL),
(56, 31, 4, 2, 'pending', '2025-11-12 23:37:10', '2025-11-12 23:37:10', NULL),
(58, 36, 4, 2, 'pending', '2025-11-13 05:13:29', '2025-11-13 05:13:29', NULL),
(60, 35, 2, 4, 'pending', '2025-11-13 05:14:23', '2025-11-13 05:14:23', NULL),
(61, 41, 4, 2, 'confirmed', '2025-12-30 05:17:13', '2025-12-30 05:46:48', '2025-12-30 05:46:48'),
(62, 42, 2, 4, 'pending', '2025-12-30 05:45:45', '2025-12-30 05:45:45', NULL),
(64, 45, 4, 2, 'pending', '2026-03-24 04:34:32', '2026-03-24 04:34:32', NULL),
(66, 46, 2, 4, 'completed', '2026-03-30 02:41:36', '2026-03-30 02:44:37', '2026-03-30 02:43:15'),
(68, 47, 2, 4, 'pending', '2026-03-30 02:48:14', '2026-03-30 02:48:14', NULL);

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
(9, 30, 49, 4, 3, 'passenger', 'driver', 5, NULL, '2025-10-23 02:05:17'),
(10, 46, 66, 2, 4, 'driver', 'passenger', 5, NULL, '2026-03-30 02:46:22'),
(11, 46, 66, 4, 2, 'passenger', 'driver', 5, NULL, '2026-03-30 02:46:39');

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
  `reset_token_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `reset_token_attempts` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `display_name`, `rides_offered_count`, `rides_requested_count`, `rides_given_count`, `rides_received_count`, `score`, `driver_rating_sum`, `driver_rating_count`, `passenger_rating_sum`, `passenger_rating_count`, `is_admin`, `username`, `phone`, `whatsapp`, `contact_privacy`, `message_privacy`, `reset_token_hash`, `reset_token_expires_at`, `reset_token_attempts`, `created_at`) VALUES
(1, 'admin@shiyaswebsite.com', '$2y$10$eA3qk4x5v0osTTo9JQz2V.5t2s5sVZb6c8z2b3V4qUO8s2o7m4mIy', 'Site_Admin', 0, 0, 0, 0, 100, 0, 0, 0, 0, 1, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-08-20 15:15:25'),
(2, 'shermanshiya@gmail.com', '$2y$10$GSz92QypdeI0opkUUi3Bx.77aD84NsI8axMpxtlZWI.5pPgK7pgja', 'Shiya s', 6, 4, 1, 0, 400, 5, 1, 0, 0, 1, NULL, '8454133056', '8452441202', 2, 1, '$2y$10$rFpW7KYxsIK7ZUabE.Atpe1//cfTtvmv2ej3P36C1ATL6C4r1TL36', '2025-10-30 10:15:09', 1, '2025-08-20 18:28:43'),
(3, 'shermanshiya+1@gmail.com', '$2y$10$kMLK94fg659GpS70vTVljeDQB7FMVlJBlRV0UHxbI6otHZX6HrIZy', 'Shiya Sherman', 2, 0, 1, 3, 700, 5, 1, 16, 4, 0, NULL, '8454133056', '8452441202', 1, 1, NULL, NULL, 0, '2025-10-06 00:58:44'),
(4, 'shermanshiya+2@gmail.com', '$2y$10$tOtEXJv/z5JqSlP.VYJBneDQU1roki2Pe02k373H.3sI6K6dEW1ES', 'dev tester', 6, 4, 3, 2, 1000, 5, 1, 10, 2, 0, NULL, '8454133056', NULL, 3, 1, NULL, NULL, 0, '2025-10-06 04:31:37'),
(5, 'rafaelblum1@gmail.com', '$2y$10$Be4xsnD8R5.DPTtFlNDBi.u81ITZ5PrQLviJfy5p4Sr0dgIN2Ntru', 'Rafael Blum', 1, 1, 1, 1, 400, 0, 0, 0, 0, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-10-10 06:09:56'),
(6, 'shia4454@gmail.com', '$2y$10$MxCWTzRiqQe8n65SI.9k6e1q9Pb7Z23y0qyG2YPxPXgfWLwjF9j7.', 'Shia', 0, 1, 1, 1, 500, 5, 1, 5, 1, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-10-10 06:19:47'),
(7, 'shermanshiya+3@gmail.com', '$2y$10$nbopzZ1N.E//OZj4lXR/W.SWzG89dyYKPKyQ7fz30DZvNWRt68OlS', 'dev tester 2', 1, 1, 0, 0, 100, 0, 0, 0, 0, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-10-10 09:37:50'),
(8, '1inboxinvite@gmail.com', '$2y$10$QvTSmdFoV9Q52HzletbXCe0FoZ1kh34g7Dhx/Nw1YdTwVoyO1qUZq', 'DYM', 3, 0, 0, 0, 100, 0, 0, 0, 0, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-10-10 19:47:14'),
(9, 'mottyatias15@gmail.com', '$2y$10$t55ACpnX2mRcuGZJiH49SeGWpOrH7dIlM4Z2lnmQ3fQdC1yV/W/1e', 'Shia atias', 0, 0, 0, 0, 100, 0, 0, 0, 0, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-10-12 01:18:26'),
(13, 'koshersssss@gmail.com', '$2y$10$8iSTNFCiwzLKZLDemHyCyOKY0.ClMXCL71LL69DRAy7.zEgBchXoe', 'dev tester 3', 0, 0, 0, 0, 100, 0, 0, 0, 0, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-10-30 10:59:30'),
(14, 'shermanshiya+4@gmail.com', '$2y$10$Pbwo82BxCI3sDzmgPU7qaOJuzZ7mIJd88rlNebuy4J5eV34VM60bC', 'Shiya_Sherman', 0, 0, 0, 0, 100, 0, 0, 0, 0, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-10-30 11:29:31'),
(15, 'shermanshiya+glitchahitch@gmail.com', '$2y$10$SBonmiVeKfKwhRT718XL4ejxHvnWofZm4dVbkBslE3NjJ.TrCTMqG', 'Shiya Sherman 2', 0, 0, 0, 0, 100, 0, 0, 0, 0, 0, NULL, NULL, NULL, 1, 1, NULL, NULL, 0, '2025-11-11 11:20:22');

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

--
-- Dumping data for table `user_messages`
--

INSERT INTO `user_messages` (`id`, `thread_id`, `sender_user_id`, `body`, `created_at`, `read_at`) VALUES
(61, 2, 4, 'hi', '2025-10-23 04:53:44', '2025-12-30 05:18:30'),
(62, 3, 3, 'hi', '2025-10-23 05:01:44', '2025-10-23 05:02:09'),
(63, 3, 4, 'hhf', '2025-10-23 05:02:16', '2025-10-23 05:02:22'),
(64, 3, 3, 'hi', '2025-10-23 07:25:59', '2025-10-23 07:26:23'),
(65, 3, 4, 'hey', '2025-10-23 07:35:27', '2025-10-23 07:35:30'),
(66, 3, 3, 'heyy', '2025-10-23 07:35:43', '2025-10-23 07:35:48'),
(67, 3, 3, 'h', '2025-10-28 08:51:54', '2025-10-28 09:31:18'),
(82, 6, 3, 'l', '2025-10-28 08:57:37', '2025-10-28 08:57:41'),
(83, 6, 3, 'l', '2025-10-28 08:57:41', '2025-10-28 08:57:41'),
(84, 6, 3, '1', '2025-10-28 08:57:51', '2025-10-28 08:57:53'),
(85, 6, 3, '2', '2025-10-28 08:57:53', '2025-10-28 08:57:53'),
(86, 6, 3, '3', '2025-10-28 08:57:58', '2025-10-28 08:57:59'),
(87, 6, 3, '4', '2025-10-28 08:58:04', '2025-10-28 08:58:05'),
(88, 3, 4, 'j', '2025-11-13 06:05:40', NULL),
(92, 2, 7, 'Hi', '2025-12-30 05:18:34', '2025-12-30 05:18:54'),
(93, 2, 7, 'G', '2025-12-30 05:18:47', '2025-12-30 05:18:54'),
(94, 2, 4, 'hh', '2025-12-30 05:18:59', '2025-12-30 05:19:01'),
(101, 4, 2, 'Hi', '2026-03-24 04:13:59', '2026-03-24 05:23:01'),
(116, 4, 7, 'B', '2026-03-24 05:23:07', '2026-03-24 05:23:30'),
(117, 4, 2, 'j', '2026-03-24 05:23:41', '2026-03-24 05:23:46'),
(118, 4, 7, 'Ss', '2026-03-24 05:23:59', '2026-03-24 05:24:03'),
(119, 4, 2, 'jj', '2026-03-24 05:24:00', '2026-03-24 05:24:04'),
(122, 4, 7, 'J', '2026-03-24 05:26:29', '2026-03-24 05:26:38'),
(123, 4, 2, 'F', '2026-03-24 23:56:47', NULL),
(142, 5, 4, 'h', '2026-03-25 01:50:06', '2026-03-25 01:50:07'),
(143, 5, 2, 'r', '2026-03-30 01:30:26', '2026-03-30 02:45:13'),
(144, 5, 2, 'dd', '2026-03-30 01:30:35', '2026-03-30 02:45:13'),
(145, 5, 2, 'H', '2026-03-30 01:30:38', '2026-03-30 02:45:13'),
(146, 5, 4, 'Hey', '2026-03-30 02:45:16', '2026-03-30 02:45:27'),
(147, 5, 2, 'm', '2026-03-30 02:45:36', '2026-03-30 02:45:37');

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
(2, 4, 7, '2025-10-11 23:54:54', '2025-12-30 05:19:01', 94, '2025-12-30 05:18:59', 0, 0),
(3, 3, 4, '2025-10-12 00:00:25', '2025-11-13 06:05:40', 88, '2025-11-13 06:05:40', 1, 0),
(4, 2, 7, '2025-10-12 04:22:09', '2026-03-24 23:56:47', 123, '2026-03-24 23:56:47', 0, 1),
(5, 2, 4, '2025-10-12 05:25:30', '2026-03-30 02:45:37', 147, '2026-03-30 02:45:36', 0, 0),
(6, 2, 3, '2025-10-28 08:53:16', '2025-10-28 08:58:05', 87, '2025-10-28 08:58:04', 0, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_token_hash` (`token_hash`),
  ADD KEY `idx_user_expires` (`user_id`,`expires_at`);

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
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_expires` (`email`,`expires_at`),
  ADD KEY `idx_user_expires` (`user_id`,`expires_at`);

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
-- AUTO_INCREMENT for table `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `app_errors`
--
ALTER TABLE `app_errors`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `rides`
--
ALTER TABLE `rides`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `ride_matches`
--
ALTER TABLE `ride_matches`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ride_ratings`
--
ALTER TABLE `ride_ratings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `user_messages`
--
ALTER TABLE `user_messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=148;

--
-- AUTO_INCREMENT for table `user_message_threads`
--
ALTER TABLE `user_message_threads`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD CONSTRAINT `fk_api_tokens_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
