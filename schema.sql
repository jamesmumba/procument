-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 14, 2025 at 12:40 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `procurement_platform`
--

-- --------------------------------------------------------

--
-- Table structure for table `approvals`
--

CREATE TABLE `approvals` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `approver_id` int(11) NOT NULL,
  `approval_level` int(11) DEFAULT 1,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `comments` text DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `approvals`
--

INSERT INTO `approvals` (`id`, `requisition_id`, `approver_id`, `approval_level`, `status`, `comments`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'approved', 'Approved for new employee onboarding', '2025-10-09 10:41:26', '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(2, 2, 3, 1, 'pending', NULL, NULL, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(3, 5, 5, 1, 'approved', '', '2025-10-14 10:36:23', '2025-10-14 10:35:56', '2025-10-14 10:36:23');

-- --------------------------------------------------------

--
-- Table structure for table `approval_rules`
--

CREATE TABLE `approval_rules` (
  `id` int(11) NOT NULL,
  `rule_name` varchar(100) NOT NULL,
  `min_amount` decimal(12,2) NOT NULL,
  `max_amount` decimal(12,2) DEFAULT NULL,
  `role_to_notify` int(11) NOT NULL,
  `conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`conditions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `approval_rules`
--




-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'create_user', 'users', 1, NULL, '{\"username\": \"admin\", \"email\": \"admin@company.com\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-10-09 10:41:28'),
(2, 2, 'create_vendor', 'vendors', 1, NULL, '{\"name\": \"TechSupply Inc.\", \"contact_person\": \"Mike Johnson\"}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-10-09 10:41:28'),
(3, 2, 'create_requisition', 'purchase_requisitions', 1, NULL, '{\"requisition_number\": \"REQ2025001\", \"total_amount\": 120000.00}', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', '2025-10-09 10:41:28'),
(5, NULL, 'register', 'users', 6, NULL, '{\"username\":\"Nelson\",\"email\":\"mwalehnelson@gmail.com\",\"password\":\"111111\",\"first_name\":\"Nelson\",\"last_name\":\"Mwale\",\"role_id\":4}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 10:42:36'),
(6, 5, 'login', 'users', 5, NULL, '{\"username\":\"kat\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 10:44:44'),
(7, 5, 'logout', 'users', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 10:45:14'),
(8, 3, 'login', 'users', 3, NULL, '{\"username\":\"james\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 10:48:18'),
(9, 3, 'logout', 'users', 3, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 10:52:06'),
(10, 6, 'login', 'users', 6, NULL, '{\"username\":\"nelson\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 10:52:17'),
(11, 6, 'adjust_stock', 'inventory_items', 5, '{\"old_stock\":\"50\"}', '{\"new_stock\":100,\"adjustment_type\":\"add\",\"quantity\":50,\"reason\":\"ghjk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 10:52:55'),
(12, 6, 'adjust_stock', 'inventory_items', 5, '{\"old_stock\":\"100\"}', '{\"new_stock\":150,\"adjustment_type\":\"add\",\"quantity\":50,\"reason\":\"ghjk\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 10:53:07'),
(13, 6, 'adjust_stock', 'inventory_items', 6, '{\"old_stock\":\"30\"}', '{\"new_stock\":0,\"adjustment_type\":\"subtract\",\"quantity\":30,\"reason\":\"tyujkl\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 10:53:22'),
(14, 6, 'create_notification', 'notifications', 1, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 10:53:22'),
(15, 6, 'create_notification', 'notifications', 2, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 10:53:22'),
(16, 6, 'create_notification', 'notifications', 3, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 10:53:22'),
(17, 6, 'read_all_notifications', 'notifications', NULL, NULL, '{\"user_id\":\"6\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:12:18'),
(18, 6, 'create_notification', 'notifications', 7, NULL, '{\"user_id\":\"6\",\"title\":\"Debug Test Notification - 13:16:09\",\"type\":\"info\",\"category\":\"system\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:09'),
(19, 6, 'create_notification', 'notifications', 8, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:09'),
(20, 6, 'create_notification', 'notifications', 9, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:09'),
(21, 6, 'create_notification', 'notifications', 10, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:09'),
(22, 6, 'create_notification', 'notifications', 11, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:09'),
(23, 6, 'create_notification', 'notifications', 12, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:09'),
(24, 6, 'create_notification', 'notifications', 13, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:09'),
(25, 6, 'create_notification', 'notifications', 14, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:09'),
(26, 6, 'create_notification', 'notifications', 15, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:09'),
(27, 6, 'create_notification', 'notifications', 16, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:09'),
(28, 6, 'create_notification', 'notifications', 17, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:09'),
(29, 6, 'create_notification', 'notifications', 18, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:09'),
(30, 6, 'create_notification', 'notifications', 19, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:09'),
(31, 6, 'create_notification', 'notifications', 20, NULL, '{\"user_id\":\"6\",\"title\":\"Debug Test Notification - 13:16:22\",\"type\":\"info\",\"category\":\"system\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(32, 6, 'create_notification', 'notifications', 21, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(33, 6, 'create_notification', 'notifications', 22, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(34, 6, 'create_notification', 'notifications', 23, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(35, 6, 'create_notification', 'notifications', 24, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(36, 6, 'create_notification', 'notifications', 25, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(37, 6, 'create_notification', 'notifications', 26, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(38, 6, 'create_notification', 'notifications', 27, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(39, 6, 'create_notification', 'notifications', 28, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(40, 6, 'create_notification', 'notifications', 29, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(41, 6, 'create_notification', 'notifications', 30, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(42, 6, 'create_notification', 'notifications', 31, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(43, 6, 'create_notification', 'notifications', 32, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(44, 6, 'create_notification', 'notifications', 33, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(45, 6, 'create_notification', 'notifications', 34, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(46, 6, 'create_notification', 'notifications', 35, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(47, 6, 'create_notification', 'notifications', 36, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(48, 6, 'create_notification', 'notifications', 37, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(49, 6, 'create_notification', 'notifications', 38, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(50, 6, 'create_notification', 'notifications', 39, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(51, 6, 'create_notification', 'notifications', 40, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(52, 6, 'create_notification', 'notifications', 41, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(53, 6, 'create_notification', 'notifications', 42, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(54, 6, 'create_notification', 'notifications', 43, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(55, 6, 'create_notification', 'notifications', 44, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:16:22'),
(56, 6, 'create_notification', 'notifications', 45, NULL, '{\"user_id\":\"6\",\"title\":\"Debug Test Notification - 13:18:59\",\"type\":\"info\",\"category\":\"system\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:18:59'),
(57, 6, 'create_notification', 'notifications', 46, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:18:59'),
(58, 6, 'create_notification', 'notifications', 47, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:18:59'),
(59, 6, 'create_notification', 'notifications', 48, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:18:59'),
(60, 6, 'create_notification', 'notifications', 49, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:18:59'),
(61, 6, 'create_notification', 'notifications', 50, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:18:59'),
(62, 6, 'create_notification', 'notifications', 51, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(63, 6, 'create_notification', 'notifications', 52, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(64, 6, 'create_notification', 'notifications', 53, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(65, 6, 'create_notification', 'notifications', 54, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(66, 6, 'create_notification', 'notifications', 55, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(67, 6, 'create_notification', 'notifications', 56, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(68, 6, 'create_notification', 'notifications', 57, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(69, 6, 'create_notification', 'notifications', 58, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(70, 6, 'create_notification', 'notifications', 59, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(71, 6, 'create_notification', 'notifications', 60, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(72, 6, 'create_notification', 'notifications', 61, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(73, 6, 'create_notification', 'notifications', 62, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(74, 6, 'create_notification', 'notifications', 63, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(75, 6, 'create_notification', 'notifications', 64, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(76, 6, 'create_notification', 'notifications', 65, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(77, 6, 'create_notification', 'notifications', 66, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(78, 6, 'create_notification', 'notifications', 67, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(79, 6, 'create_notification', 'notifications', 68, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(80, 6, 'create_notification', 'notifications', 69, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:00'),
(81, 6, 'create_notification', 'notifications', 70, NULL, '{\"user_id\":\"6\",\"title\":\"Debug Test Notification - 13:19:06\",\"type\":\"info\",\"category\":\"system\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(82, 6, 'create_notification', 'notifications', 71, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(83, 6, 'create_notification', 'notifications', 72, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(84, 6, 'create_notification', 'notifications', 73, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(85, 6, 'create_notification', 'notifications', 74, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(86, 6, 'create_notification', 'notifications', 75, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(87, 6, 'create_notification', 'notifications', 76, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(88, 6, 'create_notification', 'notifications', 77, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(89, 6, 'create_notification', 'notifications', 78, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(90, 6, 'create_notification', 'notifications', 79, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(91, 6, 'create_notification', 'notifications', 80, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(92, 6, 'create_notification', 'notifications', 81, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(93, 6, 'create_notification', 'notifications', 82, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(94, 6, 'create_notification', 'notifications', 83, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(95, 6, 'create_notification', 'notifications', 84, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(96, 6, 'create_notification', 'notifications', 85, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(97, 6, 'create_notification', 'notifications', 86, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(98, 6, 'create_notification', 'notifications', 87, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(99, 6, 'create_notification', 'notifications', 88, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(100, 6, 'create_notification', 'notifications', 89, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(101, 6, 'create_notification', 'notifications', 90, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(102, 6, 'create_notification', 'notifications', 91, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(103, 6, 'create_notification', 'notifications', 92, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(104, 6, 'create_notification', 'notifications', 93, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(105, 6, 'create_notification', 'notifications', 94, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:06'),
(106, 6, 'create_notification', 'notifications', 95, NULL, '{\"user_id\":\"6\",\"title\":\"Debug Test Notification - 13:19:22\",\"type\":\"info\",\"category\":\"system\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:22'),
(107, 6, 'create_notification', 'notifications', 96, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:22'),
(108, 6, 'create_notification', 'notifications', 97, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:22'),
(109, 6, 'create_notification', 'notifications', 98, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:22'),
(110, 6, 'create_notification', 'notifications', 99, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:22'),
(111, 6, 'create_notification', 'notifications', 100, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:23'),
(112, 6, 'create_notification', 'notifications', 101, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:23'),
(113, 6, 'create_notification', 'notifications', 102, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:23'),
(114, 6, 'create_notification', 'notifications', 103, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:23'),
(115, 6, 'create_notification', 'notifications', 104, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:23'),
(116, 6, 'create_notification', 'notifications', 105, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:23'),
(117, 6, 'create_notification', 'notifications', 106, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:23'),
(118, 6, 'create_notification', 'notifications', 107, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:19:23'),
(119, 6, 'create_notification', 'notifications', 108, NULL, '{\"user_id\":\"6\",\"title\":\"Test Info Notification\",\"type\":\"info\",\"category\":\"system\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(120, 6, 'create_notification', 'notifications', 109, NULL, '{\"user_id\":\"6\",\"title\":\"Test Warning Notification\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(121, 6, 'create_notification', 'notifications', 110, NULL, '{\"user_id\":\"6\",\"title\":\"Test Success Notification\",\"type\":\"success\",\"category\":\"requisition\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(122, 6, 'create_notification', 'notifications', 111, NULL, '{\"user_id\":\"6\",\"title\":\"Test Error Notification\",\"type\":\"error\",\"category\":\"system\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(123, 6, 'create_notification', 'notifications', 112, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(124, 6, 'create_notification', 'notifications', 113, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(125, 6, 'create_notification', 'notifications', 114, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Ballpoint Pen Set\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(126, 6, 'create_notification', 'notifications', 115, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(127, 6, 'create_notification', 'notifications', 116, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(128, 6, 'create_notification', 'notifications', 117, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Laptop\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(129, 6, 'create_notification', 'notifications', 118, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(130, 6, 'create_notification', 'notifications', 119, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(131, 6, 'create_notification', 'notifications', 120, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Paper\",\"type\":\"error\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(132, 6, 'create_notification', 'notifications', 121, NULL, '{\"user_id\":\"3\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(133, 6, 'create_notification', 'notifications', 122, NULL, '{\"user_id\":\"5\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(134, 6, 'create_notification', 'notifications', 123, NULL, '{\"user_id\":\"6\",\"title\":\"Low Stock Alert: Test Chair\",\"type\":\"warning\",\"category\":\"inventory\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-09 11:26:38'),
(135, 6, 'login', 'users', 6, NULL, '{\"username\":\"nelson\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 10:32:21'),
(136, 6, 'create_requisition', 'purchase_requisitions', 4, NULL, '{\"department\":\"zxvsdvb\",\"cost_center\":\"345678\",\"priority\":\"urgent\",\"justification\":\"xdgdfgr\",\"requested_by\":\"6\",\"requisition_number\":\"REQ20250492\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 10:35:01'),
(137, 6, 'submit_requisition', 'purchase_requisitions', 4, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 10:35:11'),
(138, 6, 'create_requisition', 'purchase_requisitions', 5, NULL, '{\"department\":\"bcxbxcbxf\",\"cost_center\":\"xfgfd\",\"priority\":\"medium\",\"justification\":\"xcfdxcb\",\"requested_by\":\"6\",\"requisition_number\":\"REQ20258407\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 10:35:49'),
(139, 6, 'create_notification', 'notifications', 124, NULL, '{\"user_id\":\"5\",\"title\":\"New Requisition for Approval: REQ20258407\",\"type\":\"info\",\"category\":\"approval\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 10:35:56'),
(140, 6, 'submit_requisition', 'purchase_requisitions', 5, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 10:35:56'),
(141, 6, 'logout', 'users', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 10:36:07'),
(142, 5, 'login', 'users', 5, NULL, '{\"username\":\"kat\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 10:36:14'),
(143, 5, 'approve_approval', 'approvals', 3, NULL, '{\"status\":\"approved\",\"comments\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 10:36:23'),
(144, 5, 'create_notification', 'notifications', 125, NULL, '{\"user_id\":\"6\",\"title\":\"Requisition Status Update: REQ20258407\",\"type\":\"success\",\"category\":\"requisition\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 10:36:23'),
(145, 5, 'create_po', 'purchase_orders', 2, NULL, '{\"requisition_id\":\"5\",\"vendor_id\":\"3\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 10:37:11'),
(146, 5, 'send_po', 'purchase_orders', 2, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-14 10:37:21');

-- --------------------------------------------------------

--
-- Stand-in structure for view `inventory_alerts`
-- (See below for the actual view)
--
CREATE TABLE `inventory_alerts` (
`id` int(11)
,`item_code` varchar(50)
,`name` varchar(100)
,`current_stock` int(11)
,`reorder_point` int(11)
,`reorder_quantity` int(11)
,`unit_cost` decimal(10,2)
,`supplier_name` varchar(100)
,`alert_status` varchar(12)
);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_consumption`
--

CREATE TABLE `inventory_consumption` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `location_id` int(11) DEFAULT NULL,
  `consumption_type` enum('daily','project','maintenance','operational','other') DEFAULT 'operational',
  `quantity_consumed` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(15,2) NOT NULL,
  `consumption_date` date NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `project_code` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_consumption`
--

INSERT INTO `inventory_consumption` (`id`, `item_id`, `location_id`, `consumption_type`, `quantity_consumed`, `unit_cost`, `total_cost`, `consumption_date`, `department`, `project_code`, `notes`, `recorded_by`, `created_at`) VALUES
(1, 5, 2, 'daily', 2, 170.00, 340.00, '2025-01-15', 'HR Department', 'DAILY', 'Daily office operations', 2, '2025-10-09 10:41:27'),
(2, 6, 2, 'operational', 1, 240.00, 240.00, '2025-01-15', 'IT Department', 'PROJ001', 'Project documentation', 2, '2025-10-09 10:41:27');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_issues`
--

CREATE TABLE `inventory_issues` (
  `id` int(11) NOT NULL,
  `issue_number` varchar(50) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `cost_center` varchar(50) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `justification` text DEFAULT NULL,
  `total_value` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','submitted','approved','rejected','issued','cancelled') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_issues`
--

INSERT INTO `inventory_issues` (`id`, `issue_number`, `requested_by`, `department`, `cost_center`, `location_id`, `priority`, `justification`, `total_value`, `status`, `approved_by`, `approved_at`, `issued_by`, `issued_at`, `created_at`, `updated_at`) VALUES
(1, 'ISS2025001', 2, 'IT Department', 'IT-001', 3, 'high', 'New employee equipment allocation', 24000.00, 'approved', NULL, NULL, NULL, NULL, '2025-10-09 10:41:27', '2025-10-09 10:41:27'),
(2, 'ISS2025002', 2, 'HR Department', 'HR-001', 4, 'medium', 'Office supplies for new hires', 500.00, 'submitted', NULL, NULL, NULL, NULL, '2025-10-09 10:41:27', '2025-10-09 10:41:27');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_issue_items`
--

CREATE TABLE `inventory_issue_items` (
  `id` int(11) NOT NULL,
  `issue_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity_requested` int(11) NOT NULL,
  `quantity_approved` int(11) DEFAULT 0,
  `quantity_issued` int(11) DEFAULT 0,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(15,2) NOT NULL,
  `specifications` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_issue_items`
--

INSERT INTO `inventory_issue_items` (`id`, `issue_id`, `item_id`, `quantity_requested`, `quantity_approved`, `quantity_issued`, `unit_cost`, `total_cost`, `specifications`, `created_at`) VALUES
(1, 1, 1, 1, 1, 1, 24000.00, 24000.00, 'Dell Latitude 5520 for new employee', '2025-10-09 10:41:27'),
(2, 2, 5, 2, 2, 2, 170.00, 340.00, 'Copy paper for new employee onboarding', '2025-10-09 10:41:27'),
(3, 2, 6, 1, 1, 1, 240.00, 240.00, 'Pen set for new employee', '2025-10-09 10:41:27');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `unit_of_measure` varchar(20) DEFAULT NULL,
  `current_stock` int(11) DEFAULT 0,
  `reorder_point` int(11) DEFAULT 0,
  `reorder_quantity` int(11) DEFAULT 0,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `supplier_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_code`, `name`, `description`, `category`, `unit_of_measure`, `current_stock`, `reorder_point`, `reorder_quantity`, `unit_cost`, `supplier_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'LAPTOP001', 'Dell Latitude 5520', 'Business laptop with Intel i7, 16GB RAM, 512GB SSD', 'Electronics', 'units', 25, 10, 20, 24000.00, 1, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(2, 'MONITOR001', 'Dell UltraSharp 24\" Monitor', '24-inch LED monitor, 1920x1080 resolution', 'Electronics', 'units', 15, 5, 10, 5000.00, 1, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(3, 'CHAIR001', 'Ergonomic Office Chair', 'Adjustable height office chair with lumbar support', 'Furniture', 'units', 8, 5, 15, 3600.00, 2, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(4, 'DESK001', 'Standing Desk', 'Electric height-adjustable standing desk', 'Furniture', 'units', 12, 3, 8, 9000.00, 2, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(5, 'PAPER001', 'Copy Paper A4', 'White copy paper, 500 sheets per ream', 'Office Supplies', 'reams', 150, 20, 50, 170.00, 4, 1, '2025-10-09 10:41:26', '2025-10-09 10:53:07'),
(6, 'PEN001', 'Ballpoint Pen Set', 'Set of 12 blue ballpoint pens', 'Office Supplies', 'sets', 0, 10, 25, 240.00, 4, 1, '2025-10-09 10:41:26', '2025-10-09 10:53:22'),
(7, 'SERVER001', 'Dell PowerEdge Server', 'Rack server with dual Xeon processors', 'IT Equipment', 'units', 3, 1, 2, 70000.00, 1, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(8, 'CABLE001', 'Ethernet Cable Cat6', '25ft Cat6 Ethernet cable', 'IT Equipment', 'units', 100, 25, 50, 300.00, 1, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(9, 'LOW001', 'Test Laptop', 'Low stock test laptop', 'Electronics', 'pcs', 2, 10, 20, 25000.00, 1, 1, '2025-10-09 11:06:32', '2025-10-09 11:06:32'),
(10, 'OUT001', 'Test Paper', 'Out of stock test paper', 'Office Supplies', 'reams', 0, 5, 15, 150.00, 1, 1, '2025-10-09 11:06:32', '2025-10-09 11:06:32'),
(11, 'LOW002', 'Test Chair', 'Low stock test chair', 'Furniture', 'pcs', 3, 5, 10, 3500.00, 1, 1, '2025-10-09 11:06:32', '2025-10-09 11:06:32');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_locations`
--

CREATE TABLE `inventory_locations` (
  `id` int(11) NOT NULL,
  `location_code` varchar(20) NOT NULL,
  `location_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_locations`
--

INSERT INTO `inventory_locations` (`id`, `location_code`, `location_name`, `description`, `address`, `is_active`, `created_at`) VALUES
(1, 'MAIN', 'Main Warehouse', 'Primary storage facility', '123 Industrial Area, Lusaka', 1, '2025-10-09 10:41:26'),
(2, 'OFFICE', 'Office Storage', 'Office supplies storage', '456 Business District, Lusaka', 1, '2025-10-09 10:41:26'),
(3, 'IT', 'IT Department', 'IT equipment storage', '789 Tech Hub, Lusaka', 1, '2025-10-09 10:41:26'),
(4, 'HR', 'HR Department', 'HR supplies storage', '321 Admin Building, Lusaka', 1, '2025-10-09 10:41:26');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_returns`
--

CREATE TABLE `inventory_returns` (
  `id` int(11) NOT NULL,
  `return_number` varchar(50) NOT NULL,
  `return_type` enum('damaged','defective','excess','supplier_return','customer_return') NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity_returned` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_value` decimal(15,2) NOT NULL,
  `return_reason` text DEFAULT NULL,
  `condition_description` text DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `status` enum('pending','approved','rejected','processed','disposed') DEFAULT 'pending',
  `returned_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_returns`
--

INSERT INTO `inventory_returns` (`id`, `return_number`, `return_type`, `item_id`, `quantity_returned`, `unit_cost`, `total_value`, `return_reason`, `condition_description`, `location_id`, `department`, `status`, `returned_by`, `approved_by`, `approved_at`, `processed_by`, `processed_at`, `created_at`, `updated_at`) VALUES
(1, 'RET2025001', 'damaged', 1, 1, 24000.00, 24000.00, 'Screen damage during transport', 'Screen cracked, needs repair', 3, 'IT Department', 'pending', 2, NULL, NULL, NULL, NULL, '2025-10-09 10:41:27', '2025-10-09 10:41:27');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `location_id` int(11) DEFAULT NULL,
  `transaction_type` enum('purchase','issue','transfer_in','transfer_out','adjustment','consumption','return','disposal') NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_value` decimal(15,2) NOT NULL,
  `reference_type` enum('purchase_order','inventory_issue','stock_transfer','adjustment','consumption','return') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_transactions`
--

INSERT INTO `inventory_transactions` (`id`, `item_id`, `location_id`, `transaction_type`, `quantity_change`, `unit_cost`, `total_value`, `reference_type`, `reference_id`, `transaction_date`, `notes`, `created_by`) VALUES
(1, 1, 3, 'issue', -1, 24000.00, 24000.00, 'inventory_issue', 1, '2025-10-09 10:41:27', 'Equipment issued to new employee', 2),
(2, 5, 2, 'consumption', -2, 170.00, 340.00, 'consumption', 1, '2025-10-09 10:41:27', 'Daily office operations', 2),
(3, 6, 2, 'consumption', -1, 240.00, 240.00, 'consumption', 2, '2025-10-09 10:41:27', 'Project documentation', 2);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','error','success') DEFAULT 'info',
  `category` enum('inventory','requisition','approval','purchase_order','system') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `action_url` varchar(500) DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `category`, `is_read`, `action_url`, `metadata`, `created_at`, `read_at`) VALUES
(1, 6, 'Welcome to Notifications!', 'This is your first notification. The system is working correctly.', 'info', 'system', 1, 'dashboard.php', '{\"test\":true}', '2025-10-09 11:06:32', '2025-10-09 11:12:18'),
(2, 6, 'System Ready', 'Your procurement system notification feature is now fully operational.', 'success', 'system', 1, 'notification_center.php', '{\"system\":\"ready\"}', '2025-10-09 11:06:32', '2025-10-09 11:12:18'),
(3, 6, 'Out of Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is out of stock. Current stock: 0, Reorder point: 10', 'error', 'inventory', 1, 'inventory.php', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:06:32', '2025-10-09 11:12:18'),
(4, 6, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 1, 'inventory.php', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:06:32', '2025-10-09 11:12:18'),
(5, 6, 'Out of Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is out of stock. Current stock: 0, Reorder point: 5', 'error', 'inventory', 1, 'inventory.php', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:06:32', '2025-10-09 11:12:18'),
(6, 6, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 1, 'inventory.php', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:06:32', '2025-10-09 11:12:18'),
(8, 3, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:16:09', NULL),
(9, 5, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:16:09', NULL),
(10, 6, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 1, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:16:09', '2025-10-09 11:24:32'),
(11, 3, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:09', NULL),
(12, 5, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:09', NULL),
(13, 6, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 1, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:09', '2025-10-09 11:24:32'),
(14, 3, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:09', NULL),
(15, 5, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:09', NULL),
(16, 6, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 1, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:09', '2025-10-09 11:24:32'),
(17, 3, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:09', NULL),
(18, 5, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:09', NULL),
(19, 6, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 1, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:09', '2025-10-09 11:24:32'),
(21, 3, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:16:22', NULL),
(22, 5, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:16:22', NULL),
(23, 6, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 1, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:16:22', '2025-10-09 11:24:32'),
(24, 3, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', NULL),
(25, 5, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', NULL),
(26, 6, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 1, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', '2025-10-09 11:24:32'),
(27, 3, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', NULL),
(28, 5, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', NULL),
(29, 6, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 1, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', '2025-10-09 11:24:32'),
(30, 3, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', NULL),
(31, 5, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', NULL),
(32, 6, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 1, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', '2025-10-09 11:24:32'),
(33, 3, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:16:22', NULL),
(34, 5, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:16:22', NULL),
(35, 6, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 1, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:16:22', '2025-10-09 11:24:32'),
(36, 3, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', NULL),
(37, 5, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', NULL),
(38, 6, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 1, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', '2025-10-09 11:24:32'),
(39, 3, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', NULL),
(40, 5, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', NULL),
(41, 6, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 1, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', '2025-10-09 11:24:32'),
(42, 3, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', NULL),
(43, 5, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', NULL),
(44, 6, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 1, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:16:22', '2025-10-09 11:24:32'),
(46, 3, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:18:59', NULL),
(47, 5, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:18:59', NULL),
(48, 6, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 1, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:18:59', '2025-10-09 11:24:32'),
(49, 3, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:18:59', NULL),
(50, 5, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:18:59', NULL),
(51, 6, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 1, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:18:59', '2025-10-09 11:24:32'),
(52, 3, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', NULL),
(53, 5, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', NULL),
(54, 6, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 1, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', '2025-10-09 11:24:32'),
(55, 3, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', NULL),
(56, 5, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', NULL),
(57, 6, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 1, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', '2025-10-09 11:24:32'),
(58, 3, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:19:00', NULL),
(59, 5, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:19:00', NULL),
(60, 6, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 1, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:19:00', '2025-10-09 11:24:32'),
(61, 3, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', NULL),
(62, 5, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', NULL),
(63, 6, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 1, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', '2025-10-09 11:24:32'),
(64, 3, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', NULL),
(65, 5, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', NULL),
(66, 6, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 1, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', '2025-10-09 11:24:32'),
(67, 3, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', NULL),
(68, 5, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', NULL),
(69, 6, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 1, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:00', '2025-10-09 11:24:32'),
(71, 3, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:19:06', NULL),
(72, 5, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:19:06', NULL),
(73, 6, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 1, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:19:06', '2025-10-09 11:24:32'),
(74, 3, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', NULL),
(75, 5, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', NULL),
(76, 6, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 1, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', '2025-10-09 11:24:32'),
(77, 3, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', NULL),
(78, 5, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', NULL),
(79, 6, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 1, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', '2025-10-09 11:24:32'),
(80, 3, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', NULL),
(81, 5, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', NULL),
(82, 6, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 1, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', '2025-10-09 11:24:32'),
(83, 3, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:19:06', NULL),
(84, 5, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:19:06', NULL),
(85, 6, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 1, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:19:06', '2025-10-09 11:24:32'),
(86, 3, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', NULL),
(87, 5, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', NULL),
(88, 6, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 1, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', '2025-10-09 11:24:32'),
(89, 3, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', NULL),
(90, 5, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', NULL),
(91, 6, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 1, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', '2025-10-09 11:24:32'),
(92, 3, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', NULL),
(93, 5, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', NULL),
(94, 6, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 1, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:06', '2025-10-09 11:24:32'),
(96, 3, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:19:22', NULL),
(97, 5, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:19:22', NULL),
(98, 6, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 1, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:19:22', '2025-10-09 11:24:32'),
(99, 3, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:22', NULL),
(100, 5, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:23', NULL),
(101, 6, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 1, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:23', '2025-10-09 11:24:32'),
(102, 3, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:23', NULL),
(103, 5, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:23', NULL),
(104, 6, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 1, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:23', '2025-10-09 11:24:32'),
(105, 3, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:23', NULL),
(106, 5, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:23', NULL),
(107, 6, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 1, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:19:23', '2025-10-09 11:24:32'),
(108, 6, 'Test Info Notification', 'This is a test info notification to verify the system is working.', 'info', 'system', 0, NULL, NULL, '2025-10-09 11:26:38', NULL),
(109, 6, 'Test Warning Notification', 'This is a test warning notification for low stock items.', 'warning', 'inventory', 0, NULL, NULL, '2025-10-09 11:26:38', NULL),
(110, 6, 'Test Success Notification', 'This is a test success notification for completed actions.', 'success', 'requisition', 0, NULL, NULL, '2025-10-09 11:26:38', NULL),
(111, 6, 'Test Error Notification', 'This is a test error notification for system issues.', 'error', 'system', 0, NULL, NULL, '2025-10-09 11:26:38', NULL),
(112, 3, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:26:38', NULL),
(113, 5, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:26:38', NULL),
(114, 6, 'Low Stock Alert: Ballpoint Pen Set', 'Item \'Ballpoint Pen Set\' (Code: PEN001) is running low. Current stock: 0, Reorder point: 10', 'error', 'inventory', 0, 'inventory.php?action=view&id=6', '{\"item_id\":\"6\",\"item_code\":\"PEN001\",\"current_stock\":\"0\",\"reorder_point\":\"10\",\"supplier_name\":\"Office Depot Zambia\"}', '2025-10-09 11:26:38', NULL),
(115, 3, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:26:38', NULL),
(116, 5, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:26:38', NULL),
(117, 6, 'Low Stock Alert: Test Laptop', 'Item \'Test Laptop\' (Code: LOW001) is running low. Current stock: 2, Reorder point: 10', 'warning', 'inventory', 0, 'inventory.php?action=view&id=9', '{\"item_id\":\"9\",\"item_code\":\"LOW001\",\"current_stock\":\"2\",\"reorder_point\":\"10\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:26:38', NULL),
(118, 3, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:26:38', NULL),
(119, 5, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:26:38', NULL),
(120, 6, 'Low Stock Alert: Test Paper', 'Item \'Test Paper\' (Code: OUT001) is running low. Current stock: 0, Reorder point: 5', 'error', 'inventory', 0, 'inventory.php?action=view&id=10', '{\"item_id\":\"10\",\"item_code\":\"OUT001\",\"current_stock\":\"0\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:26:38', NULL),
(121, 3, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:26:38', NULL),
(122, 5, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:26:38', NULL),
(123, 6, 'Low Stock Alert: Test Chair', 'Item \'Test Chair\' (Code: LOW002) is running low. Current stock: 3, Reorder point: 5', 'warning', 'inventory', 0, 'inventory.php?action=view&id=11', '{\"item_id\":\"11\",\"item_code\":\"LOW002\",\"current_stock\":\"3\",\"reorder_point\":\"5\",\"supplier_name\":\"TechSupply Zambia Ltd.\"}', '2025-10-09 11:26:38', NULL),
(124, 5, 'New Requisition for Approval: REQ20258407', 'Requisition REQ20258407 from bcxbxcbxf requires your approval. Amount: K 17,000,170.00', 'info', 'approval', 0, 'approval.php', '{\"requisition_number\":\"REQ20258407\",\"amount\":\"17000170.00\",\"requester_name\":\"bcxbxcbxf\"}', '2025-10-14 10:35:56', NULL),
(125, 6, 'Requisition Status Update: REQ20258407', 'Your requisition REQ20258407 has been approved.', 'success', 'requisition', 0, 'requisition.php?action=view&id=REQ20258407', '{\"requisition_number\":\"REQ20258407\",\"status\":\"approved\",\"comments\":\"\"}', '2025-10-14 10:36:23', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `po_items`
--

CREATE TABLE `po_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(12,2) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_items`
--

INSERT INTO `po_items` (`id`, `po_id`, `item_id`, `quantity`, `unit_cost`, `total_cost`, `received_quantity`, `created_at`) VALUES
(1, 1, 1, 5, 24000.00, 120000.00, 0, '2025-10-09 10:41:26'),
(2, 2, 5, 100001, 170.00, 17000170.00, 0, '2025-10-14 10:37:11');

--
-- Triggers `po_items`
--
DELIMITER $$
CREATE TRIGGER `update_po_total` AFTER INSERT ON `po_items` FOR EACH ROW BEGIN
    UPDATE purchase_orders 
    SET total_amount = (
        SELECT SUM(total_cost) 
        FROM po_items 
        WHERE po_id = NEW.po_id
    )
    WHERE id = NEW.po_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_po_total_delete` AFTER DELETE ON `po_items` FOR EACH ROW BEGIN
    UPDATE purchase_orders 
    SET total_amount = (
        SELECT COALESCE(SUM(total_cost), 0) 
        FROM po_items 
        WHERE po_id = OLD.po_id
    )
    WHERE id = OLD.po_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_po_total_update` AFTER UPDATE ON `po_items` FOR EACH ROW BEGIN
    UPDATE purchase_orders 
    SET total_amount = (
        SELECT SUM(total_cost) 
        FROM po_items 
        WHERE po_id = NEW.po_id
    )
    WHERE id = NEW.po_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `requisition_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `delivery_date` date DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('draft','sent','acknowledged','partially_received','fully_received','closed') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_number`, `requisition_id`, `vendor_id`, `created_by`, `total_amount`, `delivery_date`, `payment_terms`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PO2025001', 1, 1, 2, 120000.00, '2025-02-15', 'Bank Transfer', 'Urgent delivery required for new employee start date', 'sent', '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(2, 'PO20259729', 5, 3, 5, 17000170.00, '2025-10-16', 'Net 60', '', 'sent', '2025-10-14 10:37:11', '2025-10-14 10:37:21');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requisitions`
--

CREATE TABLE `purchase_requisitions` (
  `id` int(11) NOT NULL,
  `requisition_number` varchar(50) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `cost_center` varchar(50) DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `justification` text DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('draft','submitted','approved','rejected','cancelled','converted_to_po') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_requisitions`
--

INSERT INTO `purchase_requisitions` (`id`, `requisition_number`, `requested_by`, `department`, `cost_center`, `priority`, `justification`, `total_amount`, `status`, `created_at`, `updated_at`) VALUES
(1, 'REQ2025001', 2, 'IT Department', 'IT-001', 'high', 'New employee onboarding - need laptops for 5 new hires', 120000.00, 'approved', '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(2, 'REQ2025002', 2, 'HR Department', 'HR-001', 'medium', 'Office furniture for new conference room', 36000.00, 'submitted', '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(3, 'REQ2025003', 2, 'Operations', 'OPS-001', 'low', 'Regular office supplies restock', 4360.00, 'draft', '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(4, 'REQ20250492', 6, 'zxvsdvb', '345678', '', 'xdgdfgr', 2400.00, 'approved', '2025-10-14 10:35:00', '2025-10-14 10:35:11'),
(5, 'REQ20258407', 6, 'bcxbxcbxf', 'xfgfd', 'medium', 'xcfdxcb', 17000170.00, 'converted_to_po', '2025-10-14 10:35:49', '2025-10-14 10:37:11');

-- --------------------------------------------------------

--
-- Table structure for table `requisition_items`
--

CREATE TABLE `requisition_items` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(12,2) NOT NULL,
  `specifications` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requisition_items`
--

INSERT INTO `requisition_items` (`id`, `requisition_id`, `item_id`, `quantity`, `unit_cost`, `total_cost`, `specifications`, `created_at`) VALUES
(1, 1, 1, 5, 24000.00, 120000.00, 'Dell Latitude 5520 with Windows 11 Pro', '2025-10-09 10:41:26'),
(2, 2, 3, 10, 3600.00, 36000.00, 'Ergonomic chairs for conference room', '2025-10-09 10:41:26'),
(3, 3, 5, 20, 170.00, 3400.00, 'White copy paper', '2025-10-09 10:41:26'),
(4, 3, 6, 5, 12.00, 60.00, 'Blue ballpoint pen sets', '2025-10-09 10:41:26'),
(5, 3, 4, 2, 450.00, 900.00, 'Standing desks for new office space', '2025-10-09 10:41:26'),
(6, 4, 6, 10, 240.00, 2400.00, 'dfgdf', '2025-10-14 10:35:00'),
(7, 5, 5, 100001, 170.00, 17000170.00, '', '2025-10-14 10:35:49');

--
-- Triggers `requisition_items`
--
DELIMITER $$
CREATE TRIGGER `update_requisition_total` AFTER INSERT ON `requisition_items` FOR EACH ROW BEGIN
    UPDATE purchase_requisitions 
    SET total_amount = (
        SELECT SUM(total_cost) 
        FROM requisition_items 
        WHERE requisition_id = NEW.requisition_id
    )
    WHERE id = NEW.requisition_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_requisition_total_delete` AFTER DELETE ON `requisition_items` FOR EACH ROW BEGIN
    UPDATE purchase_requisitions 
    SET total_amount = (
        SELECT COALESCE(SUM(total_cost), 0) 
        FROM requisition_items 
        WHERE requisition_id = OLD.requisition_id
    )
    WHERE id = OLD.requisition_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_requisition_total_update` AFTER UPDATE ON `requisition_items` FOR EACH ROW BEGIN
    UPDATE purchase_requisitions 
    SET total_amount = (
        SELECT SUM(total_cost) 
        FROM requisition_items 
        WHERE requisition_id = NEW.requisition_id
    )
    WHERE id = NEW.requisition_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `requisition_summary`
-- (See below for the actual view)
--
CREATE TABLE `requisition_summary` (
`id` int(11)
,`requisition_number` varchar(50)
,`department` varchar(100)
,`priority` enum('low','medium','high')
,`total_amount` decimal(12,2)
,`status` enum('draft','submitted','approved','rejected','cancelled','converted_to_po')
,`created_at` timestamp
,`requested_by_name` varchar(101)
,`item_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `permissions`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'System Administrator', '{\"all\": true, \"manage_users\": true, \"manage_roles\": true, \"system_settings\": true, \"backup_restore\": true, \"audit_logs\": true, \"database_maintenance\": true, \"system_monitoring\": true, \"approval_rules\": true, \"create_requisition\": true, \"create_po\": true, \"manage_vendors\": true, \"view_inventory\": true, \"view_analytics\": true, \"issue_inventory\": true, \"transfer_stock\": true, \"manage_consumption\": true, \"process_returns\": true, \"approve_requisition\": true, \"view_requisitions\": true, \"approve_inventory_issues\": true, \"approve_stock_transfers\": true, \"manage_notifications\": true}', '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(2, 'chief_procurement_officer', 'Chief Procurement Officer (CPO)', '{\"approve_requisition\": true, \"view_requisitions\": true, \"view_analytics\": true, \"approve_inventory_issues\": true, \"approve_stock_transfers\": true, \"create_po\": true, \"manage_vendors\": true, \"view_inventory\": true, \"manage_notifications\": true, \"issue_inventory\": true, \"transfer_stock\": true, \"manage_consumption\": true, \"process_returns\": true}', '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(4, 'inventory_manager', 'Inventory Manager', '{\"view_inventory\": true, \"issue_inventory\": true, \"transfer_stock\": true, \"manage_consumption\": true, \"process_returns\": true, \"view_analytics\": true}', '2025-10-09 10:41:26', '2025-10-09 10:41:26');

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfers`
--

CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL,
  `transfer_number` varchar(50) NOT NULL,
  `from_location_id` int(11) NOT NULL,
  `to_location_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `reason` text DEFAULT NULL,
  `total_value` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','submitted','approved','rejected','transferred','cancelled') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `transferred_by` int(11) DEFAULT NULL,
  `transferred_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_transfers`
--

INSERT INTO `stock_transfers` (`id`, `transfer_number`, `from_location_id`, `to_location_id`, `requested_by`, `department`, `priority`, `reason`, `total_value`, `status`, `approved_by`, `approved_at`, `transferred_by`, `transferred_at`, `created_at`, `updated_at`) VALUES
(1, 'TRF2025001', 1, 2, 2, 'Operations', 'medium', 'Office supplies restock', 500.00, 'approved', NULL, NULL, NULL, NULL, '2025-10-09 10:41:27', '2025-10-09 10:41:27');

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfer_items`
--

CREATE TABLE `stock_transfer_items` (
  `id` int(11) NOT NULL,
  `transfer_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity_transferred` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_transfer_items`
--

INSERT INTO `stock_transfer_items` (`id`, `transfer_id`, `item_id`, `quantity_transferred`, `unit_cost`, `total_cost`, `created_at`) VALUES
(1, 1, 5, 5, 170.00, 850.00, '2025-10-09 10:41:27');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `role_id`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'James', 'procurement@company.com', '$2y$10$Au8mdrOYMKq.97k6aZrSp.uTCJ8uk7Nwe..X5lb/iajx7.qC0c2U.', 'John', 'Smith', 4, 1, '2025-10-09 10:41:26', '2025-10-09 10:48:06'),
(4, 'jake', 'procurement1@company.com', '$2y$10$Au8mdrOYMKq.97k6aZrSp.uTCJ8uk7Nwe..X5lb/iajx7.qC0c2U.', 'Bob', 'Johnson', 2, 1, '2025-10-09 10:41:26', '2025-10-09 10:47:01'),
(5, 'Kat', 'cpo1@company.com', '$2y$10$Au8mdrOYMKq.97k6aZrSp.uTCJ8uk7Nwe..X5lb/iajx7.qC0c2U.', 'Alice', 'Brown', 2, 1, '2025-10-09 10:41:26', '2025-10-09 10:46:12'),
(6, 'Nelson', 'mwalehnelson@gmail.com', '$2y$10$Au8mdrOYMKq.97k6aZrSp.uTCJ8uk7Nwe..X5lb/iajx7.qC0c2U.', 'Nelson', 'Mwale', 1, 1, '2025-10-09 10:42:36', '2025-10-09 10:43:11');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `vendor_score` decimal(3,1) DEFAULT 0.0,
  `delivery_time_avg` int(11) DEFAULT 0,
  `defect_rate` decimal(5,2) DEFAULT 0.00,
  `on_time_percentage` decimal(5,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `name`, `contact_person`, `email`, `phone`, `address`, `tax_id`, `payment_terms`, `vendor_score`, `delivery_time_avg`, `defect_rate`, `on_time_percentage`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'TechSupply Zambia Ltd.', 'Mike Johnson', 'mike@techsupply.co.zm', '+260-97-123-4567', '123 Cairo Road, Lusaka, Zambia', 'TAX123456789', 'Bank Transfer', 8.5, 5, 2.10, 95.50, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(2, 'Office Solutions Zambia', 'Sarah Wilson', 'sarah@officesolutions.co.zm', '+260-97-234-5678', '456 Independence Avenue, Lusaka, Zambia', 'TAX987654321', 'Cheque', 7.8, 7, 3.20, 88.30, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(3, 'Global Electronics Zambia', 'David Chen', 'david@globalelectronics.co.zm', '+260-97-345-6789', '789 Great East Road, Lusaka, Zambia', 'TAX456789123', 'Mobile Money', 9.2, 3, 1.50, 97.80, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(4, 'Office Depot Zambia', 'Lisa Rodriguez', 'lisa@officedepot.co.zm', '+260-97-456-7890', '321 Lumumba Road, Lusaka, Zambia', 'TAX789123456', 'Net 30', 8.1, 6, 2.80, 92.10, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(5, 'Industrial Supplies Zambia', 'Robert Brown', 'robert@industrialsupplies.co.zm', '+260-97-567-8901', '654 Makeni Road, Lusaka, Zambia', 'TAX321654987', 'COD', 7.5, 10, 4.10, 85.70, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_contracts`
--

CREATE TABLE `vendor_contracts` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `contract_number` varchar(50) NOT NULL,
  `contract_name` varchar(255) NOT NULL,
  `contract_type` enum('framework','blanket','standing','service','goods') DEFAULT 'goods',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_value` decimal(15,2) DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'ZMW',
  `status` enum('draft','active','expiring','expired','terminated') DEFAULT 'draft',
  `terms_and_conditions` text DEFAULT NULL,
  `renewal_notification_days` int(11) DEFAULT 60,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_contracts`
--

INSERT INTO `vendor_contracts` (`id`, `vendor_id`, `contract_number`, `contract_name`, `contract_type`, `start_date`, `end_date`, `total_value`, `currency`, `status`, `terms_and_conditions`, `renewal_notification_days`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'CON2025001', 'IT Equipment Supply Agreement', 'blanket', '2025-01-01', '2025-12-31', 1000000.00, 'ZMW', 'active', 'Annual blanket agreement for IT equipment with preferred pricing', 90, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(2, 2, 'CON2025002', 'Office Furniture Supply', 'goods', '2025-01-15', '2025-12-31', 500000.00, 'ZMW', 'active', 'One-year supply contract for office furniture', 60, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(3, 3, 'CON2025003', 'Electronics Purchase Agreement', 'framework', '2025-03-01', '2026-02-28', 750000.00, 'ZMW', 'active', 'Framework agreement for electronic items', 90, 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_certifications`
--

CREATE TABLE `vendor_certifications` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `certification_type` enum('iso9001','iso14001','ohsas18001','iso27001','iso45001','other') NOT NULL,
  `certification_name` varchar(255) NOT NULL,
  `certification_number` varchar(100) DEFAULT NULL,
  `issuing_organization` varchar(255) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `certificate_file` varchar(500) DEFAULT NULL,
  `status` enum('valid','expiring','expired','revoked') DEFAULT 'valid',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_certifications`
--

INSERT INTO `vendor_certifications` (`id`, `vendor_id`, `certification_type`, `certification_name`, `certification_number`, `issuing_organization`, `issue_date`, `expiry_date`, `certificate_file`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'iso9001', 'ISO 9001:2015 Quality Management', 'ISO-ZMB-2024-001', 'ISO Certification Zambia', '2024-01-15', '2027-01-14', NULL, 'valid', 'Quality management system certification', 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(2, 3, 'iso27001', 'ISO 27001 Information Security', 'ISO-27001-2024-003', 'Information Security Institute', '2024-03-01', '2027-02-28', NULL, 'valid', 'Information security management certification', 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26'),
(3, 2, 'ohsas18001', 'Occupational Health & Safety', 'OHSAS-ZMB-2023-002', 'Safety Standards Board', '2023-06-01', '2026-05-31', NULL, 'valid', 'Workplace health and safety certification', 1, '2025-10-09 10:41:26', '2025-10-09 10:41:26');

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `id` int(11) NOT NULL,
  `budget_code` varchar(50) NOT NULL,
  `budget_name` varchar(255) NOT NULL,
  `fiscal_year` varchar(20) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `cost_center` varchar(50) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'ZMW',
  `status` enum('draft','approved','active','closed','cancelled') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budgets`
--

INSERT INTO `budgets` (`id`, `budget_code`, `budget_name`, `fiscal_year`, `department`, `cost_center`, `category`, `allocated_amount`, `currency`, `status`, `approved_by`, `approved_at`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'BUD2025-001', 'IT Department Budget', '2025', 'IT Department', 'IT-001', 'Technology', 500000.00, 'ZMW', 'approved', 1, '2025-01-01 00:00:00', 'Annual IT procurement budget', 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00'),
(2, 'BUD2025-002', 'Office Supplies Budget', '2025', 'HR Department', 'HR-001', 'Office Supplies', 250000.00, 'ZMW', 'approved', 1, '2025-01-01 00:00:00', 'Annual office supplies budget', 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00'),
(3, 'BUD2025-003', 'Furniture & Equipment', '2025', 'Operations', 'OPS-001', 'Furniture', 300000.00, 'ZMW', 'approved', 1, '2025-01-01 00:00:00', 'Furniture and equipment procurement budget', 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00'),
(4, 'BUD2025-004', 'General Procurement', '2025', NULL, NULL, NULL, 1000000.00, 'ZMW', 'approved', 1, '2025-01-01 00:00:00', 'General procurement budget', 1, '2025-01-01 00:00:00', '2025-01-01 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `budget_allocations`
--

CREATE TABLE `budget_allocations` (
  `id` int(11) NOT NULL,
  `budget_id` int(11) NOT NULL,
  `allocation_type` enum('initial','adjustment','transfer') DEFAULT 'initial',
  `amount` decimal(15,2) NOT NULL,
  `allocation_date` date NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budget_allocations`
--

INSERT INTO `budget_allocations` (`id`, `budget_id`, `allocation_type`, `amount`, `allocation_date`, `reference_number`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 'initial', 500000.00, '2025-01-01', 'BUD2025-001', 'Initial allocation for IT Department', 1, '2025-01-01 00:00:00'),
(2, 2, 'initial', 250000.00, '2025-01-01', 'BUD2025-002', 'Initial allocation for Office Supplies', 1, '2025-01-01 00:00:00'),
(3, 3, 'initial', 300000.00, '2025-01-01', 'BUD2025-003', 'Initial allocation for Furniture', 1, '2025-01-01 00:00:00'),
(4, 4, 'initial', 1000000.00, '2025-01-01', 'BUD2025-004', 'Initial allocation for General Procurement', 1, '2025-01-01 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `budget_spend`
--

CREATE TABLE `budget_spend` (
  `id` int(11) NOT NULL,
  `budget_id` int(11) NOT NULL,
  `reference_type` enum('purchase_requisition','purchase_order','expense') NOT NULL,
  `reference_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `spend_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `cost_center` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budget_spend`
--

INSERT INTO `budget_spend` (`id`, `budget_id`, `reference_type`, `reference_id`, `amount`, `spend_date`, `description`, `department`, `cost_center`, `created_at`) VALUES
(1, 1, 'purchase_order', 1, 120000.00, '2025-01-15', 'Dell Latitude 5520 laptops for new hires', 'IT Department', 'IT-001', '2025-10-09 10:41:26'),
(2, 3, 'purchase_requisition', 2, 36000.00, '2025-01-15', 'Office furniture for new conference room', 'HR Department', 'HR-001', '2025-10-09 10:41:26');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vendor_performance`
-- (See below for the actual view)
--
CREATE TABLE `vendor_performance` (
`id` int(11)
,`name` varchar(100)
,`vendor_score` decimal(3,1)
,`delivery_time_avg` int(11)
,`defect_rate` decimal(5,2)
,`on_time_percentage` decimal(5,2)
,`total_orders` bigint(21)
,`total_spend` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Structure for view `inventory_alerts`
--
DROP TABLE IF EXISTS `inventory_alerts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `inventory_alerts`  AS SELECT `ii`.`id` AS `id`, `ii`.`item_code` AS `item_code`, `ii`.`name` AS `name`, `ii`.`current_stock` AS `current_stock`, `ii`.`reorder_point` AS `reorder_point`, `ii`.`reorder_quantity` AS `reorder_quantity`, `ii`.`unit_cost` AS `unit_cost`, `v`.`name` AS `supplier_name`, CASE WHEN `ii`.`current_stock` <= `ii`.`reorder_point` THEN 'Low Stock' WHEN `ii`.`current_stock` = 0 THEN 'Out of Stock' ELSE 'OK' END AS `alert_status` FROM (`inventory_items` `ii` left join `vendors` `v` on(`ii`.`supplier_id` = `v`.`id`)) WHERE `ii`.`is_active` = 1 AND `ii`.`current_stock` <= `ii`.`reorder_point` ;

-- --------------------------------------------------------

--
-- Structure for view `requisition_summary`
--
DROP TABLE IF EXISTS `requisition_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `requisition_summary`  AS SELECT `pr`.`id` AS `id`, `pr`.`requisition_number` AS `requisition_number`, `pr`.`department` AS `department`, `pr`.`priority` AS `priority`, `pr`.`total_amount` AS `total_amount`, `pr`.`status` AS `status`, `pr`.`created_at` AS `created_at`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `requested_by_name`, count(`ri`.`id`) AS `item_count` FROM ((`purchase_requisitions` `pr` join `users` `u` on(`pr`.`requested_by` = `u`.`id`)) left join `requisition_items` `ri` on(`pr`.`id` = `ri`.`requisition_id`)) GROUP BY `pr`.`id`, `pr`.`requisition_number`, `pr`.`department`, `pr`.`priority`, `pr`.`total_amount`, `pr`.`status`, `pr`.`created_at`, `u`.`first_name`, `u`.`last_name` ;

-- --------------------------------------------------------

--
-- Structure for view `vendor_performance`
--
DROP TABLE IF EXISTS `vendor_performance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vendor_performance`  AS SELECT `v`.`id` AS `id`, `v`.`name` AS `name`, `v`.`vendor_score` AS `vendor_score`, `v`.`delivery_time_avg` AS `delivery_time_avg`, `v`.`defect_rate` AS `defect_rate`, `v`.`on_time_percentage` AS `on_time_percentage`, count(`po`.`id`) AS `total_orders`, coalesce(sum(`po`.`total_amount`),0) AS `total_spend` FROM (`vendors` `v` left join `purchase_orders` `po` on(`v`.`id` = `po`.`vendor_id`)) WHERE `v`.`is_active` = 1 GROUP BY `v`.`id`, `v`.`name`, `v`.`vendor_score`, `v`.`delivery_time_avg`, `v`.`defect_rate`, `v`.`on_time_percentage` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `approvals`
--
ALTER TABLE `approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_approvals_requisition` (`requisition_id`),
  ADD KEY `idx_approvals_approver` (`approver_id`),
  ADD KEY `idx_approvals_status` (`status`);

--
-- Indexes for table `approval_rules`
--
ALTER TABLE `approval_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_to_notify` (`role_to_notify`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_table` (`table_name`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `inventory_consumption`
--
ALTER TABLE `inventory_consumption`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `inventory_issues`
--
ALTER TABLE `inventory_issues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `issue_number` (`issue_number`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `issued_by` (`issued_by`);

--
-- Indexes for table `inventory_issue_items`
--
ALTER TABLE `inventory_issue_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `issue_id` (`issue_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `idx_inventory_code` (`item_code`),
  ADD KEY `idx_inventory_category` (`category`),
  ADD KEY `idx_inventory_supplier` (`supplier_id`);

--
-- Indexes for table `inventory_locations`
--
ALTER TABLE `inventory_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `location_code` (`location_code`);

--
-- Indexes for table `inventory_returns`
--
ALTER TABLE `inventory_returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_number` (`return_number`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `returned_by` (`returned_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `location_id` (`location_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `po_items`
--
ALTER TABLE `po_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `requisition_id` (`requisition_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_pos_number` (`po_number`),
  ADD KEY `idx_pos_vendor` (`vendor_id`),
  ADD KEY `idx_pos_status` (`status`);

--
-- Indexes for table `purchase_requisitions`
--
ALTER TABLE `purchase_requisitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `requisition_number` (`requisition_number`),
  ADD KEY `idx_requisitions_number` (`requisition_number`),
  ADD KEY `idx_requisitions_status` (`status`),
  ADD KEY `idx_requisitions_requested_by` (`requested_by`);

--
-- Indexes for table `requisition_items`
--
ALTER TABLE `requisition_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requisition_id` (`requisition_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transfer_number` (`transfer_number`),
  ADD KEY `from_location_id` (`from_location_id`),
  ADD KEY `to_location_id` (`to_location_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `transferred_by` (`transferred_by`);

--
-- Indexes for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transfer_id` (`transfer_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_username` (`username`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_role` (`role_id`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendors_name` (`name`),
  ADD KEY `idx_vendors_active` (`is_active`);

--
-- Indexes for table `vendor_contracts`
--
ALTER TABLE `vendor_contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contract_number` (`contract_number`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `idx_contracts_status` (`status`),
  ADD KEY `idx_contracts_dates` (`start_date`, `end_date`);

--
-- Indexes for table `vendor_certifications`
--
ALTER TABLE `vendor_certifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `idx_cert_status` (`status`),
  ADD KEY `idx_cert_dates` (`expiry_date`);

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `budget_code` (`budget_code`),
  ADD KEY `idx_budgets_status` (`status`),
  ADD KEY `idx_budgets_dept` (`department`),
  ADD KEY `idx_budgets_cc` (`cost_center`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `budget_id` (`budget_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `budget_spend`
--
ALTER TABLE `budget_spend`
  ADD PRIMARY KEY (`id`),
  ADD KEY `budget_id` (`budget_id`),
  ADD KEY `idx_spend_reference` (`reference_type`, `reference_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approvals`
--
ALTER TABLE `approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `approval_rules`
--
ALTER TABLE `approval_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `inventory_consumption`
--
ALTER TABLE `inventory_consumption`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inventory_issues`
--
ALTER TABLE `inventory_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inventory_issue_items`
--
ALTER TABLE `inventory_issue_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `inventory_locations`
--
ALTER TABLE `inventory_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory_returns`
--
ALTER TABLE `inventory_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `po_items`
--
ALTER TABLE `po_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_requisitions`
--
ALTER TABLE `purchase_requisitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `requisition_items`
--
ALTER TABLE `requisition_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vendor_contracts`
--
ALTER TABLE `vendor_contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `vendor_certifications`
--
ALTER TABLE `vendor_certifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `budget_spend`
--
ALTER TABLE `budget_spend`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approvals`
--
ALTER TABLE `approvals`
  ADD CONSTRAINT `approvals_ibfk_1` FOREIGN KEY (`requisition_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approvals_ibfk_2` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `approval_rules`
--
ALTER TABLE `approval_rules`
  ADD CONSTRAINT `approval_rules_ibfk_1` FOREIGN KEY (`role_to_notify`) REFERENCES `roles` (`id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_consumption`
--
ALTER TABLE `inventory_consumption`
  ADD CONSTRAINT `inventory_consumption_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `inventory_consumption_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_consumption_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory_issues`
--
ALTER TABLE `inventory_issues`
  ADD CONSTRAINT `inventory_issues_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `inventory_issues_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_issues_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_issues_ibfk_4` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_issue_items`
--
ALTER TABLE `inventory_issue_items`
  ADD CONSTRAINT `inventory_issue_items_ibfk_1` FOREIGN KEY (`issue_id`) REFERENCES `inventory_issues` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_issue_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_returns`
--
ALTER TABLE `inventory_returns`
  ADD CONSTRAINT `inventory_returns_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `inventory_returns_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_returns_ibfk_3` FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `inventory_returns_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_returns_ibfk_5` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_transactions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `po_items`
--
ALTER TABLE `po_items`
  ADD CONSTRAINT `po_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `po_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`requisition_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_requisitions`
--
ALTER TABLE `purchase_requisitions`
  ADD CONSTRAINT `purchase_requisitions_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `requisition_items`
--
ALTER TABLE `requisition_items`
  ADD CONSTRAINT `requisition_items_ibfk_1` FOREIGN KEY (`requisition_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `requisition_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD CONSTRAINT `stock_transfers_ibfk_1` FOREIGN KEY (`from_location_id`) REFERENCES `inventory_locations` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_2` FOREIGN KEY (`to_location_id`) REFERENCES `inventory_locations` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_3` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_transfers_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_transfers_ibfk_5` FOREIGN KEY (`transferred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  ADD CONSTRAINT `stock_transfer_items_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_transfer_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `vendor_contracts`
--
ALTER TABLE `vendor_contracts`
  ADD CONSTRAINT `vendor_contracts_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  ADD CONSTRAINT `vendor_contracts_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `vendor_certifications`
--
ALTER TABLE `vendor_certifications`
  ADD CONSTRAINT `vendor_certifications_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`),
  ADD CONSTRAINT `vendor_certifications_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `budgets`
--
ALTER TABLE `budgets`
  ADD CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `budgets_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  ADD CONSTRAINT `budget_allocations_ibfk_1` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`),
  ADD CONSTRAINT `budget_allocations_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `budget_spend`
--
ALTER TABLE `budget_spend`
  ADD CONSTRAINT `budget_spend_ibfk_1` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
