-- phpMyAdmin SQL Dump
-- version 4.9.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Dec 31, 2020 at 06:15 AM
-- Server version: 10.4.10-MariaDB
-- PHP Version: 7.3.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sfa_saas`
--

-- --------------------------------------------------------

--
-- Table structure for table `setting_menus`
--

DROP TABLE IF EXISTS `setting_menus`;
CREATE TABLE IF NOT EXISTS `setting_menus` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `software_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `setting_menus_software_id_foreign` (`software_id`)
) ENGINE=InnoDB AUTO_INCREMENT=163 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `setting_menus`
--

INSERT INTO `setting_menus` (`id`, `software_id`, `name`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 3, 'Organization', 1, '2020-10-20 00:16:24', '2020-10-20 00:16:24', NULL),
(2, 3, 'Users & Roles', 1, '2020-10-20 00:16:24', '2020-10-20 00:16:24', NULL),
(3, 3, 'Preferences', 1, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL),
(4, 3, 'Taxes', 1, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL),
(5, 3, 'BANK', 1, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL),
(6, 3, 'Warehouse', 1, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL),
(7, 3, 'Country', 1, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL),
(8, 3, 'Region', 1, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL),
(9, 3, 'Branch/Depot', 1, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL),
(10, 3, 'Van Master', 1, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL),
(11, 3, 'Route', 1, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL),
(12, 3, 'Customer Category', 0, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL),
(13, 3, 'Credit Limits', 1, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL),
(14, 3, 'Outlet Product Code', 1, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL),
(15, 3, 'Item Group', 1, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL),
(16, 3, 'UOM', 1, '2020-10-20 00:16:25', '2020-10-20 00:16:25', NULL);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
