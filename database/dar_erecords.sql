-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 20, 2026 at 02:13 AM
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
-- Database: `dar_erecords`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `entity` varchar(40) DEFAULT NULL,
  `entity_id` varchar(40) DEFAULT NULL,
  `details` varchar(500) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `username`, `action`, `entity`, `entity_id`, `details`, `ip`, `created_at`) VALUES
(1, NULL, 'admin@example.gov.ph', 'login_failed', 'user', NULL, 'unknown username', '::1', '2026-10-14 03:45:01'),
(2, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 03:45:46'),
(3, 1, 'admin', 'password_changed', 'user', '1', NULL, '::1', '2026-10-14 03:48:30'),
(4, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-14 03:49:56'),
(5, 1, 'admin', 'logout', NULL, NULL, NULL, '::1', '2026-10-14 03:51:08'),
(6, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 03:52:49'),
(7, 1, 'admin', 'logout', NULL, NULL, NULL, '::1', '2026-10-14 03:54:10'),
(8, NULL, 'admin', 'login_failed', 'user', '1', 'attempt 1', '::1', '2026-10-14 04:21:33'),
(9, NULL, 'admin', 'login_failed', 'user', '1', 'attempt 2', '::1', '2026-10-14 04:21:39'),
(10, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 04:21:48'),
(11, 1, 'admin', 'logout', NULL, NULL, NULL, '::1', '2026-10-14 04:22:03'),
(12, NULL, 'admin', 'login_failed', 'user', '1', 'attempt 1', '::1', '2026-10-14 04:23:59'),
(13, NULL, 'admin', 'login_failed', 'user', '1', 'attempt 2', '::1', '2026-10-14 04:24:05'),
(14, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 04:24:12'),
(15, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-14 04:25:17'),
(16, 1, 'admin', 'user_created', 'user', '2', 'finance as records_officer', '::1', '2026-10-14 04:27:02'),
(17, 1, 'admin', 'logout', NULL, NULL, NULL, '::1', '2026-10-14 04:41:08'),
(18, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 04:42:00'),
(19, 1, 'admin', 'logout', NULL, NULL, NULL, '::1', '2026-10-14 04:43:21'),
(20, NULL, 'finance', 'login_failed', 'user', '2', 'attempt 1', '::1', '2026-10-14 04:43:31'),
(21, NULL, 'finance', 'login_failed', 'user', '2', 'attempt 2', '::1', '2026-10-14 04:44:17'),
(22, 2, 'finance', 'login', 'user', '2', NULL, '::1', '2026-10-14 04:44:26'),
(23, 2, 'finance', 'password_changed', 'user', '2', NULL, '::1', '2026-10-14 04:44:52'),
(24, NULL, 'admin', 'login_failed', 'user', '1', 'attempt 1', '::1', '2026-10-14 04:45:24'),
(25, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 04:45:31'),
(26, 1, 'admin', 'task_assigned', 'task', '1', 'Sorting of Documents -> finance', '::1', '2026-10-14 04:46:03'),
(27, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-14 04:46:57'),
(28, 1, 'admin', 'logout', NULL, NULL, NULL, '::1', '2026-10-14 05:00:37'),
(29, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 05:04:27'),
(30, 2, 'finance', 'logout', NULL, NULL, NULL, '::1', '2026-10-14 05:19:01'),
(31, NULL, 'jdkada', 'login_failed', 'user', NULL, 'unknown username', '::1', '2026-10-14 05:19:16'),
(32, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 05:27:45'),
(33, 1, 'admin', 'logout', NULL, NULL, NULL, '::1', '2026-10-14 05:42:26'),
(34, NULL, 'admin', 'login_failed', 'user', '1', 'attempt 1', '::1', '2026-10-14 05:42:32'),
(35, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 05:42:44'),
(36, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 05:54:31'),
(37, 1, 'admin', 'logout', NULL, NULL, NULL, '::1', '2026-10-14 05:55:41'),
(38, NULL, 'finance', 'login_failed', 'user', '2', 'attempt 1', '::1', '2026-10-14 05:55:48'),
(39, NULL, 'finance', 'login_failed', 'user', '2', 'attempt 2', '::1', '2026-10-14 05:55:54'),
(40, NULL, 'finance', 'login_failed', 'user', '2', 'attempt 3', '::1', '2026-10-14 05:55:59'),
(41, 1, 'admin', 'task_assigned', 'task', '2', 'Archiving Documents -> finance', '::1', '2026-10-14 05:57:31'),
(42, 1, 'admin', 'logout', NULL, NULL, NULL, '::1', '2026-10-14 05:58:04'),
(43, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 05:58:13'),
(44, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 06:17:48'),
(45, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-14 06:20:46'),
(46, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-14 06:20:49'),
(47, 1, 'admin', 'report_viewed', 'report', 'progress', NULL, '::1', '2026-10-14 06:20:53'),
(48, 1, 'admin', 'task_assigned', 'task', '3', 'document scanning -> finance', '::1', '2026-10-14 06:21:21'),
(49, NULL, 'finance', 'login_failed', 'user', '2', 'attempt 4', '::1', '2026-10-14 06:21:48'),
(50, NULL, 'finance', 'login_failed', 'user', '2', 'account locked after repeated failures', '::1', '2026-10-14 06:21:54'),
(51, NULL, 'finance', 'login_blocked', 'user', '2', 'account locked', '::1', '2026-10-14 06:22:05'),
(52, NULL, 'finance', 'login_blocked', 'user', '2', 'account locked', '::1', '2026-10-14 06:22:12'),
(53, NULL, 'finance', 'login_blocked', 'user', '2', 'account locked', '::1', '2026-10-14 06:22:19'),
(54, NULL, 'finance', 'login_blocked', 'user', '2', 'account locked', '::1', '2026-10-14 06:22:26'),
(55, NULL, 'finance', 'login_blocked', 'user', '2', 'account locked', '::1', '2026-10-14 06:22:31'),
(56, 1, 'admin', 'user_unlocked', 'user', '2', NULL, '::1', '2026-10-14 06:22:41'),
(57, 1, 'admin', 'password_reset', 'user', '2', NULL, '::1', '2026-10-14 06:23:12'),
(58, 2, 'finance', 'login', 'user', '2', NULL, '::1', '2026-10-14 06:23:21'),
(59, 2, 'finance', 'password_changed', 'user', '2', NULL, '::1', '2026-10-14 06:23:37'),
(60, 2, 'finance', 'record_created', 'record', '1', 'DAR-FD-2026-0001', '::1', '2026-10-14 06:28:15'),
(61, 2, 'finance', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-14 06:30:08'),
(62, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 07:29:59'),
(63, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 07:35:36'),
(64, 1, 'admin', 'record_viewed', 'record', '1', 'DAR-FD-2026-0001', '::1', '2026-10-14 07:36:27'),
(65, 1, 'admin', 'record_viewed', 'record', '1', 'DAR-FD-2026-0001', '::1', '2026-10-14 07:41:39'),
(66, 1, 'admin', 'record_viewed', 'record', '1', 'DAR-FD-2026-0001', '::1', '2026-10-14 08:18:06'),
(67, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 08:36:02'),
(68, 1, 'admin', 'file_uploaded', 'file', '1', 'DAR-FD-2026-0001 v1', '::1', '2026-10-14 08:43:50'),
(69, 1, 'admin', 'file_viewed', 'file', '1', 'DAR-FD-2026-0001 v1', '::1', '2026-10-14 08:43:54'),
(70, 1, 'admin', 'status_change', 'record', '1', 'scanning -> for_verification', '::1', '2026-10-14 09:04:44'),
(71, 1, 'admin', 'file_viewed', 'file', '1', 'DAR-FD-2026-0001 v1', '::1', '2026-10-14 09:04:54'),
(72, 2, 'finance', 'login', 'user', '2', NULL, '::1', '2026-10-14 09:06:21'),
(73, 1, 'admin', 'user_created', 'user', '3', 'verifier as verifier', '::1', '2026-10-14 09:09:10'),
(74, 2, 'finance', 'logout', NULL, NULL, NULL, '::1', '2026-10-14 09:09:22'),
(75, 3, 'verifier', 'login', 'user', '3', NULL, '::1', '2026-10-14 09:09:29'),
(76, 3, 'verifier', 'password_changed', 'user', '3', NULL, '::1', '2026-10-14 09:09:51'),
(77, 3, 'verifier', 'verified_pass', 'record', '1', 'DAR-FD-2026-0001', '::1', '2026-10-14 09:10:06'),
(78, 3, 'verifier', 'archived', 'record', '1', '1', '::1', '2026-10-14 09:13:54'),
(79, 3, 'verifier', 'search', NULL, NULL, '{\"q\":\"daf\"}', '::1', '2026-10-14 09:14:16'),
(80, 3, 'verifier', 'search', NULL, NULL, '{\"q\":\"dar\"}', '::1', '2026-10-14 09:14:23'),
(81, 3, 'verifier', 'file_viewed', 'file', '1', 'DAR-FD-2026-0001 v1', '::1', '2026-10-14 09:14:29'),
(82, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-14 09:14:55'),
(83, 1, 'admin', 'custody_release', 'record', '1', 'RS-2026-0001', '::1', '2026-10-14 09:17:54'),
(84, 1, 'admin', 'slip_printed', 'custody', '1', 'RS-2026-0001', '::1', '2026-10-14 09:17:54'),
(85, 1, 'admin', 'custody_return', 'record', '1', 'RS-2026-0001', '::1', '2026-10-14 09:18:14'),
(86, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-14 09:21:12'),
(87, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-14 09:21:15'),
(88, 1, 'admin', 'report_viewed', 'report', 'access', NULL, '::1', '2026-10-14 09:21:22'),
(89, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-14 09:21:26'),
(90, 1, 'admin', 'record_created', 'record', '2', 'DAR-FD-2026-0002', '::1', '2026-10-14 09:40:08'),
(91, 1, 'admin', 'file_uploaded', 'file', '2', 'DAR-FD-2026-0002 v1', '::1', '2026-10-14 09:40:08'),
(92, 1, 'admin', 'file_viewed', 'file', '2', 'DAR-FD-2026-0002 v1', '::1', '2026-10-14 09:40:17'),
(93, 1, 'admin', 'batch_created', 'batch', '1', 'SB-2026-0001', '::1', '2026-10-14 09:42:07'),
(94, 1, 'admin', 'file_viewed', 'file', '2', 'DAR-FD-2026-0002 v1', '::1', '2026-10-14 09:43:36'),
(95, 1, 'admin', 'record_viewed', 'record', '1', 'DAR-FD-2026-0001', '::1', '2026-10-14 09:45:50'),
(96, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-14 22:14:15'),
(97, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-14 22:18:31'),
(98, 1, 'admin', 'record_viewed', 'record', '2', 'DAR-FD-2026-0002', '::1', '2026-10-14 22:30:09'),
(99, 1, 'admin', 'file_viewed', 'file', '2', 'DAR-FD-2026-0002 v1', '::1', '2026-10-14 22:30:14'),
(100, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-14 22:35:23'),
(101, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-15 03:02:50'),
(102, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-15 03:48:57'),
(103, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-15 04:45:47'),
(104, 1, 'admin', 'record_condition', 'record', '2', 'ok', '::1', '2026-10-15 04:55:03'),
(105, 1, 'admin', 'record_condition', 'record', '2', 'damaged', '::1', '2026-10-15 04:55:17'),
(106, 1, 'admin', 'scan_flagged', 'file', '2', 'duplicate', '::1', '2026-10-15 04:55:24'),
(107, 1, 'admin', 'record_condition', 'record', '1', 'ok', '::1', '2026-10-15 04:55:34'),
(108, 1, 'admin', 'file_viewed', 'file', '2', 'DAR-FD-2026-0002 v1', '::1', '2026-10-15 05:00:44'),
(109, 1, 'admin', 'file_viewed', 'file', '1', 'DAR-FD-2026-0001 v1', '::1', '2026-10-15 05:06:18'),
(110, 1, 'admin', 'record_viewed', 'record', '2', 'DAR-FD-2026-0002', '::1', '2026-10-15 05:15:09'),
(111, 1, 'admin', 'search', NULL, NULL, '{\"q\":\"finance\"}', '::1', '2026-10-15 05:29:35'),
(112, 1, 'admin', 'search', NULL, NULL, '{\"q\":\"dar\"}', '::1', '2026-10-15 05:29:53'),
(113, 1, 'admin', 'search', NULL, NULL, '{\"q\":\"dar\"}', '::1', '2026-10-15 05:31:12'),
(114, 1, 'admin', 'search', NULL, NULL, '{\"q\":\"dar\",\"division_id\":3}', '::1', '2026-10-15 05:31:19'),
(115, 1, 'admin', 'slip_printed', 'custody', '1', 'RS-2026-0001', '::1', '2026-10-15 05:33:08'),
(116, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-15 05:34:45'),
(117, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-15 05:34:49'),
(118, 1, 'admin', 'report_viewed', 'report', 'progress', NULL, '::1', '2026-10-15 05:34:57'),
(119, 1, 'admin', 'report_viewed', 'report', 'verification', NULL, '::1', '2026-10-15 05:35:02'),
(120, 1, 'admin', 'record_condition', 'record', '2', 'ok', '::1', '2026-10-15 05:48:04'),
(121, 2, 'finance', 'login', 'user', '2', NULL, '::1', '2026-10-15 05:49:06'),
(122, 2, 'finance', 'logout', NULL, NULL, NULL, '::1', '2026-10-15 05:49:44'),
(123, NULL, 'verifier', 'login_failed', 'user', '3', 'attempt 1', '::1', '2026-10-15 05:49:50'),
(124, NULL, 'verifier', 'login_failed', 'user', '3', 'attempt 2', '::1', '2026-10-15 05:50:01'),
(125, 1, 'admin', 'password_reset', 'user', '3', NULL, '::1', '2026-10-15 05:50:15'),
(126, 3, 'verifier', 'login', 'user', '3', NULL, '::1', '2026-10-15 05:50:28'),
(127, 3, 'verifier', 'password_changed', 'user', '3', NULL, '::1', '2026-10-15 05:51:33'),
(128, 1, 'admin', 'record_created', 'record', '3', 'DAR-LD-2026-0001', '::1', '2026-10-15 05:53:42'),
(129, 1, 'admin', 'file_uploaded', 'file', '3', 'DAR-LD-2026-0001 v1', '::1', '2026-10-15 05:53:42'),
(130, 1, 'admin', 'status_change', 'record', '3', 'scanning -> for_verification', '::1', '2026-10-15 05:54:19'),
(131, 3, 'verifier', 'file_viewed', 'file', '3', 'DAR-LD-2026-0001 v1', '::1', '2026-10-15 05:55:09'),
(132, 3, 'verifier', 'file_viewed', 'file', '3', 'DAR-LD-2026-0001 v1', '::1', '2026-10-15 05:55:23'),
(133, 3, 'verifier', 'verified_fail', 'record', '3', 'DAR-LD-2026-0001', '::1', '2026-10-15 05:55:53'),
(134, 3, 'verifier', 'file_viewed', 'file', '3', 'DAR-LD-2026-0001 v1', '::1', '2026-10-15 05:56:31'),
(135, NULL, 'finance', 'login_failed', 'user', '2', 'attempt 1', '::1', '2026-10-15 06:11:18'),
(136, 2, 'finance', 'login', 'user', '2', NULL, '::1', '2026-10-15 06:11:24'),
(137, 3, 'verifier', 'record_viewed', 'record', '3', 'DAR-LD-2026-0001', '::1', '2026-10-15 06:18:40'),
(138, 1, 'admin', 'storage_category_added', 'storage_category', '4', 'Filing Cabinet: Leasehold Documentation Folder', '::1', '2026-10-15 06:40:44'),
(139, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-15 06:56:36'),
(140, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-15 07:07:27'),
(141, 1, 'admin', 'user_created', 'user', '4', 'johncarlo23 as division_staff', '::1', '2026-10-15 07:18:44'),
(142, 4, 'johncarlo23', 'login', 'user', '4', NULL, '::1', '2026-10-15 07:19:09'),
(143, 4, 'johncarlo23', 'password_changed', 'user', '4', NULL, '::1', '2026-10-15 07:19:33'),
(144, 1, 'admin', 'user_created', 'user', '5', 'jc01 as records_officer', '::1', '2026-10-15 07:20:52'),
(145, 4, 'johncarlo23', 'logout', NULL, NULL, NULL, '::1', '2026-10-15 07:20:56'),
(146, 5, 'jc01', 'login', 'user', '5', NULL, '::1', '2026-10-15 07:21:05'),
(147, 5, 'jc01', 'password_changed', 'user', '5', NULL, '::1', '2026-10-15 07:21:33'),
(148, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-15 07:27:31'),
(149, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-15 07:30:34'),
(150, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-15 07:30:39'),
(151, 1, 'admin', 'record_viewed', 'record', '2', 'DAR-FD-2026-0002', '::1', '2026-10-15 07:46:19'),
(152, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-15 07:49:15'),
(153, 1, 'admin', 'record_viewed', 'record', '2', 'DAR-FD-2026-0002', '::1', '2026-10-15 07:49:55'),
(154, 1, 'admin', 'file_viewed', 'file', '2', 'DAR-FD-2026-0002 v1', '::1', '2026-10-15 07:50:01'),
(155, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-19 23:17:39'),
(156, 1, 'admin', 'logout', NULL, NULL, NULL, '::1', '2026-10-19 23:19:48'),
(157, 1, 'admin', 'login', 'user', '1', NULL, '::1', '2026-10-19 23:21:14'),
(158, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-20 00:05:13'),
(159, 1, 'admin', 'record_viewed', 'record', '1', 'DAR-FD-2026-0001', '::1', '2026-10-20 00:05:18'),
(160, 1, 'admin', 'slip_printed', 'custody', '1', 'RS-2026-0001', '::1', '2026-10-20 00:05:31'),
(161, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-20 00:05:46'),
(162, 1, 'admin', 'report_viewed', 'report', 'inventory', NULL, '::1', '2026-10-20 00:05:49'),
(163, 1, 'admin', 'record_viewed', 'record', '1', 'DAR-FD-2026-0001', '::1', '2026-10-20 00:05:55'),
(164, 1, 'admin', 'file_viewed', 'file', '1', 'DAR-FD-2026-0001 v1', '::1', '2026-10-20 00:06:01'),
(165, 1, 'admin', 'record_viewed', 'record', '3', 'DAR-LD-2026-0001', '::1', '2026-10-20 00:06:25'),
(166, 1, 'admin', 'file_viewed', 'file', '3', 'DAR-LD-2026-0001 v1', '::1', '2026-10-20 00:06:30'),
(167, 1, 'admin', 'file_viewed', 'file', '3', 'DAR-LD-2026-0001 v1', '::1', '2026-10-20 00:06:33'),
(168, 1, 'admin', 'record_viewed', 'record', '3', 'DAR-LD-2026-0001', '::1', '2026-10-20 00:08:06');

--
-- Triggers `audit_logs`
--
DELIMITER $$
CREATE TRIGGER `audit_no_delete` BEFORE DELETE ON `audit_logs` FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit logs cannot be deleted'; END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `audit_no_update` BEFORE UPDATE ON `audit_logs` FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit logs are read-only'; END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `backups`
--

CREATE TABLE `backups` (
  `id` int(11) NOT NULL,
  `filename` varchar(120) NOT NULL,
  `kind` enum('database','files') NOT NULL,
  `size_bytes` bigint(20) NOT NULL DEFAULT 0,
  `sha256` char(64) DEFAULT NULL,
  `status` enum('ok','failed') NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_verified_at` datetime DEFAULT NULL,
  `verify_result` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `prefix` varchar(10) NOT NULL,
  `default_access` enum('open','restricted') NOT NULL DEFAULT 'restricted',
  `naming_convention` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `prefix`, `default_access`, `naming_convention`, `is_active`) VALUES
(1, 'Memorandum', 'MEMO', 'open', 'DIV-MEMO-YYYY-NNN', 1),
(2, 'Land Title', 'LT', 'restricted', 'LT-PROVINCE-NNNN', 1),
(3, 'Contract', 'CON', 'restricted', 'CON-YYYY-NNN', 1),
(4, 'Voucher', 'VCH', 'restricted', 'VCH-YYYY-NNNN', 1),
(5, 'Case Folder', 'CASE', 'restricted', 'CASE-YYYY-NNNN', 1),
(6, 'Report', 'RPT', 'open', 'RPT-DIV-YYYY-NN', 1);

-- --------------------------------------------------------

--
-- Table structure for table `class_requests`
--

CREATE TABLE `class_requests` (
  `id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `old_level` enum('open','restricted') NOT NULL,
  `new_level` enum('open','restricted') NOT NULL,
  `basis` varchar(255) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `counters`
--

CREATE TABLE `counters` (
  `name` varchar(40) NOT NULL,
  `yr` smallint(6) NOT NULL,
  `val` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `counters`
--

INSERT INTO `counters` (`name`, `yr`, `val`) VALUES
('inv-FD', 2026, 2),
('inv-LD', 2026, 1),
('scanbatch', 2026, 1),
('slip', 2026, 1);

-- --------------------------------------------------------

--
-- Table structure for table `custody`
--

CREATE TABLE `custody` (
  `id` int(11) NOT NULL,
  `slip_no` varchar(30) NOT NULL,
  `record_id` int(11) NOT NULL,
  `borrower_id` int(11) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `released_by` int(11) NOT NULL,
  `released_at` datetime NOT NULL,
  `due_date` date NOT NULL,
  `received_by` int(11) DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `status` enum('out','overdue','returned') NOT NULL DEFAULT 'out',
  `overdue_notified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `custody`
--

INSERT INTO `custody` (`id`, `slip_no`, `record_id`, `borrower_id`, `purpose`, `released_by`, `released_at`, `due_date`, `received_by`, `returned_at`, `status`, `overdue_notified`) VALUES
(1, 'RS-2026-0001', 1, 2, 'request by client', 1, '2026-10-14 17:17:54', '2026-10-21', 1, '2026-10-14 17:18:14', 'returned', 0);

-- --------------------------------------------------------

--
-- Table structure for table `digital_files`
--

CREATE TABLE `digital_files` (
  `id` bigint(20) NOT NULL,
  `record_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(80) NOT NULL,
  `mime` varchar(80) NOT NULL,
  `size_bytes` bigint(20) NOT NULL,
  `page_count` int(11) NOT NULL DEFAULT 1,
  `sha256` char(64) NOT NULL,
  `scan_flag` enum('none','unreadable','incomplete','duplicate') NOT NULL DEFAULT 'none',
  `replaced_reason` varchar(255) DEFAULT NULL,
  `ocr_text` mediumtext DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `digital_files`
--

INSERT INTO `digital_files` (`id`, `record_id`, `batch_id`, `version`, `is_current`, `original_name`, `stored_name`, `mime`, `size_bytes`, `page_count`, `sha256`, `scan_flag`, `replaced_reason`, `ocr_text`, `uploaded_by`, `uploaded_at`) VALUES
(1, 1, NULL, 1, 1, 'darlogo.jpg', '2026/10/245e8f01da4010200fc60813ea978030.jpg', 'image/jpeg', 11875, 1, '587c0d128be5199e52f0e0605cec8d4d3e816caa4f871243ac234852e3543323', 'none', NULL, NULL, 1, '2026-10-14 08:43:50'),
(2, 2, NULL, 1, 1, 'darlogo.jpg', '2026/10/77782b781f2ec1a434247e50d63f9b0d.jpg', 'image/jpeg', 11875, 1, '587c0d128be5199e52f0e0605cec8d4d3e816caa4f871243ac234852e3543323', 'duplicate', NULL, NULL, 1, '2026-10-14 09:40:08'),
(3, 3, NULL, 1, 1, 'PDS_ESPINASE.pdf', '2026/10/8d40d16bea379b0a769cd130935ad938.pdf', 'application/pdf', 647352, 5, '5b0caf46061b655262504e1707831f5143302ba33d820c038f34d53a7d312263', 'incomplete', NULL, NULL, 1, '2026-10-15 05:53:42');

-- --------------------------------------------------------

--
-- Table structure for table `disposition_requests`
--

CREATE TABLE `disposition_requests` (
  `id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('transfer_storage','transfer_nap','dispose') NOT NULL,
  `justification` varchar(255) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected','executed') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `divisions`
--

CREATE TABLE `divisions` (
  `id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `divisions`
--

INSERT INTO `divisions` (`id`, `code`, `name`, `is_active`) VALUES
(1, 'RS', 'Records Section', 1),
(2, 'LD', 'Legal Division', 1),
(3, 'FD', 'Finance Division', 1),
(4, 'PD', 'Planning Division', 1),
(5, 'OD', 'Operations Division', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(300) NOT NULL,
  `link` varchar(200) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 2, 'New task: Sorting of Documents (due 2026-10-14)', 'tasks.php', 1, '2026-10-14 04:46:03'),
(2, 2, 'New task: Archiving Documents (due 2026-10-15)', 'tasks.php', 1, '2026-10-14 05:57:31'),
(3, 2, 'New task: document scanning (due 2026-10-14)', 'tasks.php', 1, '2026-10-14 06:21:21'),
(4, 2, 'Folder DAR-FD-2026-0001 was released to you, due 2026-10-21', 'custody.php', 1, '2026-10-14 09:17:54'),
(5, 1, 'DAR-FD-2026-0002 marked damaged', 'record.php?id=2', 1, '2026-10-15 04:55:17'),
(6, 1, 'Scan #2 was flagged duplicate. Please rescan.', 'inventory.php', 1, '2026-10-15 04:55:24'),
(7, 3, 'DAR-LD-2026-0001 is ready for verification', 'verification.php', 1, '2026-10-15 05:54:19'),
(8, 1, 'DAR-LD-2026-0001 failed verification and needs a rescan', 'inventory.php', 1, '2026-10-15 05:55:53');

-- --------------------------------------------------------

--
-- Table structure for table `records`
--

CREATE TABLE `records` (
  `id` int(11) NOT NULL,
  `inventory_no` varchar(40) NOT NULL,
  `qr_token` char(16) NOT NULL,
  `title` varchar(255) NOT NULL,
  `ref_no` varchar(80) DEFAULT NULL,
  `division_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `doc_year` smallint(6) NOT NULL,
  `volume` varchar(30) DEFAULT NULL,
  `cabinet` varchar(30) DEFAULT NULL,
  `shelf` varchar(30) DEFAULT NULL,
  `box` varchar(30) DEFAULT NULL,
  `folder` varchar(30) DEFAULT NULL,
  `custodian_id` int(11) DEFAULT NULL,
  `status` enum('inventoried','scanning','for_verification','digitized','archived') NOT NULL DEFAULT 'inventoried',
  `condition_flag` enum('ok','missing','damaged','unlocated') NOT NULL DEFAULT 'ok',
  `access_level` enum('open','restricted') NOT NULL DEFAULT 'restricted',
  `restriction_basis` varchar(255) DEFAULT NULL,
  `retention_schedule_id` int(11) DEFAULT NULL,
  `retention_start` date DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `archive_location` varchar(150) DEFAULT NULL,
  `preservation_notes` varchar(255) DEFAULT NULL,
  `disposition_status` enum('none','pending','approved','executed') NOT NULL DEFAULT 'none',
  `label_printed_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `storage_type` varchar(20) DEFAULT NULL,
  `storage_category_id` int(11) DEFAULT NULL,
  `storage_no` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `records`
--

INSERT INTO `records` (`id`, `inventory_no`, `qr_token`, `title`, `ref_no`, `division_id`, `section_id`, `category_id`, `doc_year`, `volume`, `cabinet`, `shelf`, `box`, `folder`, `custodian_id`, `status`, `condition_flag`, `access_level`, `restriction_basis`, `retention_schedule_id`, `retention_start`, `archived_at`, `archive_location`, `preservation_notes`, `disposition_status`, `label_printed_at`, `created_by`, `created_at`, `updated_at`, `storage_type`, `storage_category_id`, `storage_no`) VALUES
(1, 'DAR-FD-2026-0001', '220284130859b807', 'Disbursement Voucher, Travel Expenses, Field Monitoring', 'DV-2026-0482', 3, 5, 1, 2026, '1', 'A', '3', '5', '1', 2, 'archived', 'ok', 'open', NULL, 1, '2026-10-14', '2026-10-14 17:13:54', '1', '1', 'none', NULL, 2, '2026-10-14 06:28:15', '2026-10-14 09:13:54', NULL, NULL, NULL),
(2, 'DAR-FD-2026-0002', 'bbd744ab260473e3', 'darlogo', '1', 3, 4, 5, 2026, '1', '1', '1', '1', '1', 2, 'scanning', 'ok', 'restricted', 'Default for category: Case Folder', 5, NULL, NULL, NULL, NULL, 'none', NULL, 1, '2026-10-14 09:40:08', '2026-10-15 05:48:04', NULL, NULL, NULL),
(3, 'DAR-LD-2026-0001', 'dc74d02774cc22fd', 'PDS ESPINASE', '3', 2, 3, 5, 2026, '3', '3', '3', '3', '3', 2, 'scanning', 'ok', 'restricted', 'Default for category: Case Folder', 5, NULL, NULL, NULL, NULL, 'none', NULL, 1, '2026-10-15 05:53:42', '2026-10-15 05:55:53', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `record_history`
--

CREATE TABLE `record_history` (
  `id` bigint(20) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` varchar(40) NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `record_history`
--

INSERT INTO `record_history` (`id`, `record_id`, `action`, `from_status`, `to_status`, `remarks`, `user_id`, `created_at`) VALUES
(1, 1, 'inventoried', NULL, 'inventoried', 'Registered in inventory', 2, '2026-10-14 06:28:15'),
(2, 1, 'status_change', 'inventoried', 'scanning', 'Scan uploaded', 1, '2026-10-14 08:43:50'),
(3, 1, 'scan_uploaded', NULL, NULL, 'v1, 1 page(s)', 1, '2026-10-14 08:43:50'),
(4, 1, 'status_change', 'scanning', 'for_verification', NULL, 1, '2026-10-14 09:04:44'),
(5, 1, 'verified', 'for_verification', 'digitized', 'Good', 3, '2026-10-14 09:10:06'),
(6, 1, 'archived', 'digitized', 'archived', 'Archive location: 1', 3, '2026-10-14 09:13:54'),
(7, 1, 'released', NULL, NULL, 'To Finance Dept, due 2026-10-21. request by client', 1, '2026-10-14 09:17:54'),
(8, 1, 'returned', NULL, NULL, 'Slip RS-2026-0001', 1, '2026-10-14 09:18:14'),
(9, 2, 'inventoried', NULL, 'inventoried', 'Registered in inventory', 1, '2026-10-14 09:40:08'),
(10, 2, 'status_change', 'inventoried', 'scanning', 'Scan uploaded', 1, '2026-10-14 09:40:08'),
(11, 2, 'scan_uploaded', NULL, NULL, 'v1, 1 page(s), flagged duplicate', 1, '2026-10-14 09:40:08'),
(12, 2, 'condition', NULL, NULL, 'Marked ok', 1, '2026-10-15 04:55:03'),
(13, 2, 'condition', NULL, NULL, 'Marked damaged', 1, '2026-10-15 04:55:17'),
(14, 1, 'condition', NULL, NULL, 'Marked ok', 1, '2026-10-15 04:55:34'),
(15, 2, 'condition', NULL, NULL, 'Marked ok', 1, '2026-10-15 05:48:04'),
(16, 3, 'inventoried', NULL, 'inventoried', 'Registered in inventory', 1, '2026-10-15 05:53:42'),
(17, 3, 'status_change', 'inventoried', 'scanning', 'Scan uploaded', 1, '2026-10-15 05:53:42'),
(18, 3, 'scan_uploaded', NULL, NULL, 'v1, 5 page(s)', 1, '2026-10-15 05:53:42'),
(19, 3, 'status_change', 'scanning', 'for_verification', 'It is Good', 1, '2026-10-15 05:54:19'),
(20, 3, 'verification_failed', 'for_verification', 'scanning', NULL, 3, '2026-10-15 05:55:53');

-- --------------------------------------------------------

--
-- Table structure for table `retention_schedules`
--

CREATE TABLE `retention_schedules` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `active_years` int(11) NOT NULL,
  `inactive_years` int(11) NOT NULL,
  `disposition` enum('permanent','dispose','transfer_nap') NOT NULL,
  `authority_ref` varchar(200) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `retention_schedules`
--

INSERT INTO `retention_schedules` (`id`, `category_id`, `class_name`, `active_years`, `inactive_years`, `disposition`, `authority_ref`, `is_active`) VALUES
(1, 1, 'Memoranda (SAMPLE)', 3, 2, 'dispose', 'SAMPLE - replace with approved DAR RDS item', 1),
(2, 2, 'Land Titles (SAMPLE)', 10, 0, 'permanent', 'SAMPLE - replace with approved DAR RDS item', 1),
(3, 3, 'Contracts (SAMPLE)', 5, 5, 'transfer_nap', 'SAMPLE - replace with approved DAR RDS item', 1),
(4, 4, 'Vouchers (SAMPLE)', 3, 7, 'dispose', 'SAMPLE - replace with approved DAR RDS item', 1),
(5, 5, 'Case Folders (SAMPLE)', 5, 5, 'transfer_nap', 'SAMPLE - replace with approved DAR RDS item', 1),
(6, 6, 'Reports (SAMPLE)', 3, 2, 'transfer_nap', 'SAMPLE - replace with approved DAR RDS item', 1);

-- --------------------------------------------------------

--
-- Table structure for table `scan_batches`
--

CREATE TABLE `scan_batches` (
  `id` int(11) NOT NULL,
  `batch_no` varchar(30) NOT NULL,
  `division_id` int(11) DEFAULT NULL,
  `operator_id` int(11) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `scan_batches`
--

INSERT INTO `scan_batches` (`id`, `batch_no`, `division_id`, `operator_id`, `notes`, `created_at`) VALUES
(1, 'SB-2026-0001', 3, 1, NULL, '2026-10-14 09:42:07');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `division_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `division_id`, `name`, `is_active`) VALUES
(1, 1, 'Receiving and Releasing', 1),
(2, 1, 'Archives', 1),
(3, 2, 'Case Management', 1),
(4, 3, 'Accounting', 1),
(5, 3, 'Budget', 1),
(6, 4, 'Policy and Planning', 1),
(7, 5, 'Field Operations', 1);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `skey` varchar(60) NOT NULL,
  `svalue` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`skey`, `svalue`) VALUES
('allowed_ext', 'pdf,png,jpg,jpeg,tif,tiff'),
('backup_frequency', 'daily'),
('loan_days', '7'),
('max_upload_mb', '50'),
('ref_format', 'DAR-{DIV}-{YEAR}-{SEQ}'),
('retention_review_days', '90');

-- --------------------------------------------------------

--
-- Table structure for table `storage_categories`
--

CREATE TABLE `storage_categories` (
  `id` int(11) NOT NULL,
  `storage_type` varchar(20) NOT NULL,
  `name` varchar(80) NOT NULL,
  `code` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `storage_categories`
--

INSERT INTO `storage_categories` (`id`, `storage_type`, `name`, `code`, `is_active`) VALUES
(1, 'document_holder', 'Document Box', 'DB', 1),
(2, 'document_holder', 'Folder', 'FD', 1),
(3, 'document_holder', 'Binder', 'BD', 1),
(4, 'filing_cabinet', 'Leasehold Documentation Folder', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `details` varchar(500) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) NOT NULL,
  `division_id` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('open','done') NOT NULL DEFAULT 'open',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `details`, `record_id`, `assigned_to`, `division_id`, `due_date`, `status`, `created_by`, `created_at`, `completed_at`) VALUES
(1, 'Sorting of Documents', 'Sorting the documents immediately', NULL, 2, 5, '2026-10-14', 'done', 1, '2026-10-14 04:46:03', '2026-10-14 12:46:28'),
(2, 'Archiving Documents', 'Archived Task', NULL, 2, 5, '2026-10-15', 'open', 1, '2026-10-14 05:57:31', NULL),
(3, 'document scanning', 'document scanning', NULL, 2, 5, '2026-10-14', 'open', 1, '2026-10-14 06:21:21', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `surname` varchar(60) DEFAULT NULL,
  `first_name` varchar(60) DEFAULT NULL,
  `middle_initial` char(1) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','records_officer','division_staff','verifier','viewer') NOT NULL DEFAULT 'viewer',
  `division_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 1,
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `surname`, `first_name`, `middle_initial`, `birthdate`, `email`, `password_hash`, `role`, `division_id`, `is_active`, `must_change_password`, `failed_attempts`, `locked_until`, `last_login`, `created_at`) VALUES
(1, 'admin', 'System Administrator', NULL, NULL, NULL, NULL, 'admin@example.gov.ph', '$2y$10$4Zl4r4UyQSWl.KeoEjB1HOyZsr75fny22rJUzJN8H2LsCZoO5JA5O', 'admin', 1, 1, 0, 0, NULL, '2026-10-20 07:21:14', '2026-10-14 03:44:47'),
(2, 'finance', 'Finance Dept', NULL, NULL, NULL, NULL, 'financedept@gmail.com', '$2y$10$WKNdW5Q6Wr/kPjs.wHcJuO1SETi.Bgtq456CBKPASc8ow5vQxVNwu', 'records_officer', 5, 1, 0, 0, NULL, '2026-10-15 14:11:24', '2026-10-14 04:27:02'),
(3, 'verifier', 'Verifier', NULL, NULL, NULL, NULL, 'verifier@gmail.com', '$2y$10$Svmxyb5cT5vQsAyDQYrxhu/fC90wGk0ywwfNekGil7mwSM8s.OCvm', 'verifier', 3, 1, 0, 0, NULL, '2026-10-15 13:50:28', '2026-10-14 09:09:10'),
(4, 'johncarlo23', 'John Carlo V. Espinase', 'Espinase', 'John Carlo', 'V', '1999-08-23', 'punzalanmark8@gmail.com', '$2y$10$QlptUOVEZCUUZAp3qzOGA.AwMigCpcC0lFS375qcz5VQE9.RzHfqy', 'division_staff', 5, 1, 0, 0, NULL, '2026-10-15 15:19:09', '2026-10-15 07:18:44'),
(5, 'jc01', 'JC E. Vargas', 'Vargas', 'JC', 'E', '2026-10-01', 'punzalanmark8@gmail.com', '$2y$10$nhtaYjlFbHFJeykonwH5oeaTCgD78aLganxUW4r68qxzUB1njQo76', 'records_officer', 3, 1, 0, 0, NULL, '2026-10-15 15:21:05', '2026-10-15 07:20:52');

-- --------------------------------------------------------

--
-- Table structure for table `verifications`
--

CREATE TABLE `verifications` (
  `id` int(11) NOT NULL,
  `record_id` int(11) NOT NULL,
  `file_id` bigint(20) NOT NULL,
  `verifier_id` int(11) NOT NULL,
  `physical_pages` int(11) NOT NULL,
  `digital_pages` int(11) NOT NULL,
  `readable_ok` tinyint(1) NOT NULL,
  `orientation_ok` tinyint(1) NOT NULL,
  `result` enum('pass','fail') NOT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `verified_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `verifications`
--

INSERT INTO `verifications` (`id`, `record_id`, `file_id`, `verifier_id`, `physical_pages`, `digital_pages`, `readable_ok`, `orientation_ok`, `result`, `remarks`, `verified_at`) VALUES
(1, 1, 1, 3, 1, 1, 1, 1, 'pass', 'Good', '2026-10-14 09:10:06'),
(2, 3, 3, 3, 4, 5, 0, 0, 'fail', NULL, '2026-10-15 05:55:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `action` (`action`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `class_requests`
--
ALTER TABLE `class_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `record_id` (`record_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `counters`
--
ALTER TABLE `counters`
  ADD PRIMARY KEY (`name`,`yr`);

--
-- Indexes for table `custody`
--
ALTER TABLE `custody`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slip_no` (`slip_no`),
  ADD KEY `status` (`status`),
  ADD KEY `record_id` (`record_id`),
  ADD KEY `borrower_id` (`borrower_id`),
  ADD KEY `released_by` (`released_by`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `digital_files`
--
ALTER TABLE `digital_files`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stored_name` (`stored_name`),
  ADD KEY `record_id` (`record_id`),
  ADD KEY `sha256` (`sha256`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `disposition_requests`
--
ALTER TABLE `disposition_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `record_id` (`record_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `divisions`
--
ALTER TABLE `divisions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`is_read`);

--
-- Indexes for table `records`
--
ALTER TABLE `records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inventory_no` (`inventory_no`),
  ADD UNIQUE KEY `qr_token` (`qr_token`),
  ADD KEY `status` (`status`),
  ADD KEY `division_id` (`division_id`,`doc_year`),
  ADD KEY `access_level` (`access_level`),
  ADD KEY `ref_no` (`ref_no`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `custodian_id` (`custodian_id`),
  ADD KEY `retention_schedule_id` (`retention_schedule_id`);

--
-- Indexes for table `record_history`
--
ALTER TABLE `record_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `record_id` (`record_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `retention_schedules`
--
ALTER TABLE `retention_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `scan_batches`
--
ALTER TABLE `scan_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_no` (`batch_no`),
  ADD KEY `operator_id` (`operator_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `division_id` (`division_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`skey`);

--
-- Indexes for table `storage_categories`
--
ALTER TABLE `storage_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_storage_cat` (`storage_type`,`name`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`,`status`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `record_id` (`record_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `division_id` (`division_id`);

--
-- Indexes for table `verifications`
--
ALTER TABLE `verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `record_id` (`record_id`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `verifier_id` (`verifier_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=169;

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `class_requests`
--
ALTER TABLE `class_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custody`
--
ALTER TABLE `custody`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `digital_files`
--
ALTER TABLE `digital_files`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `disposition_requests`
--
ALTER TABLE `disposition_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `divisions`
--
ALTER TABLE `divisions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `records`
--
ALTER TABLE `records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `record_history`
--
ALTER TABLE `record_history`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `retention_schedules`
--
ALTER TABLE `retention_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `scan_batches`
--
ALTER TABLE `scan_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `storage_categories`
--
ALTER TABLE `storage_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `verifications`
--
ALTER TABLE `verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `class_requests`
--
ALTER TABLE `class_requests`
  ADD CONSTRAINT `class_requests_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `records` (`id`),
  ADD CONSTRAINT `class_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `class_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `custody`
--
ALTER TABLE `custody`
  ADD CONSTRAINT `custody_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `records` (`id`),
  ADD CONSTRAINT `custody_ibfk_2` FOREIGN KEY (`borrower_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `custody_ibfk_3` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `custody_ibfk_4` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `digital_files`
--
ALTER TABLE `digital_files`
  ADD CONSTRAINT `digital_files_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `records` (`id`),
  ADD CONSTRAINT `digital_files_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `scan_batches` (`id`),
  ADD CONSTRAINT `digital_files_ibfk_3` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `disposition_requests`
--
ALTER TABLE `disposition_requests`
  ADD CONSTRAINT `disposition_requests_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `records` (`id`),
  ADD CONSTRAINT `disposition_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `disposition_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `records`
--
ALTER TABLE `records`
  ADD CONSTRAINT `records_ibfk_1` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`),
  ADD CONSTRAINT `records_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  ADD CONSTRAINT `records_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `records_ibfk_4` FOREIGN KEY (`custodian_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `records_ibfk_5` FOREIGN KEY (`retention_schedule_id`) REFERENCES `retention_schedules` (`id`);

--
-- Constraints for table `record_history`
--
ALTER TABLE `record_history`
  ADD CONSTRAINT `record_history_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `records` (`id`),
  ADD CONSTRAINT `record_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `retention_schedules`
--
ALTER TABLE `retention_schedules`
  ADD CONSTRAINT `retention_schedules_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `scan_batches`
--
ALTER TABLE `scan_batches`
  ADD CONSTRAINT `scan_batches_ibfk_1` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`);

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`record_id`) REFERENCES `records` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`);

--
-- Constraints for table `verifications`
--
ALTER TABLE `verifications`
  ADD CONSTRAINT `verifications_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `records` (`id`),
  ADD CONSTRAINT `verifications_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `digital_files` (`id`),
  ADD CONSTRAINT `verifications_ibfk_3` FOREIGN KEY (`verifier_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
