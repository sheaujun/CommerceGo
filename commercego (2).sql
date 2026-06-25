-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 01, 2026 at 05:04 PM
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
-- Database: `commercego`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `join_date` date NOT NULL DEFAULT curdate(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `user_id`, `customer_code`, `name`, `email`, `phone`, `address`, `join_date`, `status`, `created_at`, `updated_at`) VALUES
(6, 5, 'CUST005', 'Noona Tan', 'noona@gmail.com', '0186483442', '', '2026-04-19', 'active', '2026-04-19 14:23:47', '2026-04-21 17:14:07'),
(8, 7, 'CUST007', 'cus2 tan', 'customer2@gmail.com', '0289746372', NULL, '2026-04-22', 'active', '2026-04-22 04:52:27', '2026-04-22 04:52:27'),
(9, NULL, 'POSWALKIN', 'POS Walk-in Customer', 'pos.walkin@essen.local', '', 'In-store POS transaction', '2026-05-15', 'active', '2026-05-14 16:20:38', '2026-05-14 16:20:38');

-- --------------------------------------------------------

--
-- Table structure for table `customer_orders`
--

CREATE TABLE `customer_orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `order_code` varchar(20) NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `cashier_user_id` int(10) UNSIGNED DEFAULT NULL,
  `order_date` date NOT NULL DEFAULT curdate(),
  `transaction_datetime` datetime DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `items` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  `paymentMethod` varchar(80) DEFAULT 'Unknown',
  `amount_paid` decimal(12,2) DEFAULT NULL,
  `change_amount` decimal(12,2) DEFAULT NULL,
  `order_source` varchar(30) NOT NULL DEFAULT 'Online',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_orders`
--

INSERT INTO `customer_orders` (`order_id`, `order_code`, `customer_id`, `cashier_user_id`, `order_date`, `transaction_datetime`, `total`, `items`, `status`, `paymentMethod`, `amount_paid`, `change_amount`, `order_source`, `created_at`, `updated_at`) VALUES
(11, 'ORD866E5FE222', 6, NULL, '2026-04-22', NULL, 159.90, 3, 'Pending', 'Stripe - CARD', NULL, NULL, 'Online', '2026-04-21 17:07:25', '2026-04-21 17:07:25'),
(12, 'ORD31DD0D0349', 6, NULL, '2026-04-22', NULL, 4.99, 1, 'Processing', 'Stripe - CARD', NULL, NULL, 'Online', '2026-04-21 17:09:35', '2026-04-22 04:21:31'),
(13, 'ORDFFE9E491CC', 6, NULL, '2026-04-22', NULL, 6.00, 1, 'Processing', 'Stripe - CARD', NULL, NULL, 'Online', '2026-04-22 04:37:10', '2026-04-22 06:26:45'),
(14, 'ORD4B0A98DEC3', 6, NULL, '2026-04-22', NULL, 23.00, 1, 'Pending', 'Stripe - CARD', NULL, NULL, 'Online', '2026-04-22 06:29:46', '2026-04-22 06:29:46'),
(15, 'POS26051418203863', 9, 4, '2026-05-15', '2026-05-14 18:20:38', 43.00, 2, 'Delivered', 'POS Cash', 50.00, 7.00, 'POS', '2026-05-14 16:20:38', '2026-05-14 16:20:38'),
(16, 'POS26051418210231', 9, 4, '2026-05-15', '2026-05-14 18:21:02', 15.80, 2, 'Delivered', 'POS Card', 15.80, 0.00, 'POS', '2026-05-14 16:21:02', '2026-05-14 16:21:02'),
(17, 'ORD953B91E506', 8, NULL, '2026-05-28', NULL, 18.50, 1, 'Pending', 'Stripe - CARD', NULL, NULL, 'Online', '2026-05-28 12:25:11', '2026-05-28 12:25:11');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('Pending','Paid','Processing','Shipped','Delivered','Cancelled') DEFAULT 'Pending',
  `stripe_session_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_name`, `quantity`, `unit_price`, `created_at`) VALUES
(19, 11, 'Blood Pressure Monitor', 1, 89.90, '2026-04-21 17:07:25'),
(20, 11, 'Product 3', 1, 50.00, '2026-04-21 17:07:25'),
(21, 11, 'Product 2', 1, 20.00, '2026-04-21 17:07:25'),
(22, 12, 'Bandage Pack', 1, 4.99, '2026-04-21 17:09:35'),
(23, 13, 'Hand Sanitizer 500ml', 1, 6.00, '2026-04-22 04:37:10'),
(24, 14, 'HS', 1, 23.00, '2026-04-22 06:29:46'),
(25, 15, 'Omega-3 Fish Oil', 1, 24.00, '2026-05-14 16:20:38'),
(26, 15, 'Multivitamin Complex', 1, 19.00, '2026-05-14 16:20:38'),
(27, 16, 'Hansaplast Elastic Plaster 20s', 2, 7.90, '2026-05-14 16:21:02'),
(28, 17, 'Betadine Antiseptic Solution 120ml', 1, 18.50, '2026-05-28 12:25:11');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(150) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `used`) VALUES
(1, 'tansheaujun1308@gmail.com', 'eb1414ec7d670c945da74706c1b60af0c954a6fb7b7072f9c0ccecbc0a62ac62', '2026-03-05 19:00:36', 0),
(2, 'tansheaujun1308@gmail.com', '6804873797074c85f6054a6656a0884f7d2dd18dd4cac7b440f8cfd0e596cf1a', '2026-04-12 18:04:46', 0),
(3, 'tansheaujun1308@gmail.com', '5d2b910ab9582d552dc51c4e8249697fd637db533a1c1256b9d05cf4cdc29f03', '2026-04-12 18:04:48', 0);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `productID` int(10) UNSIGNED NOT NULL,
  `productName` varchar(255) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `productDescription` text DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stockQuantity` int(11) NOT NULL DEFAULT 0,
  `physicalStock` int(11) DEFAULT NULL,
  `onlineStock` int(11) DEFAULT NULL,
  `productType` enum('Physical','Online','Both') NOT NULL DEFAULT 'Both',
  `complianceStatus` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `imagePath` varchar(255) DEFAULT NULL,
  `expiryDate` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`productID`, `productName`, `barcode`, `productDescription`, `category`, `price`, `stockQuantity`, `physicalStock`, `onlineStock`, `productType`, `complianceStatus`, `status`, `imagePath`, `expiryDate`, `created_at`) VALUES
(1, 'Product1', '9551000000017', 'This is product 1', 'Medication', 10.00, 103, 3, 100, 'Both', 'Approved', 'Inactive', '', '2026-03-10', '2026-03-05 17:33:16'),
(2, 'Product 2', '9551000000024', 'This is product 2', 'Equipment', 20.00, 7, 2, 5, 'Both', 'Approved', 'Inactive', 'uploads/products/product_1776091514_bb2728584e.jpg', '2026-04-30', '2026-04-13 14:45:14'),
(3, 'Paracetamol 500mg', '9551000000031', 'Pain relief tablets for aches and fever', 'Medication', 5.00, 140, 20, 120, '', 'Approved', 'Active', 'uploads/products/product_1776827301_88c30fa3cf.jpg', '2026-12-31', '2026-03-05 09:33:16'),
(4, 'Vitamin C 1000mg', '9551000000048', 'Immune support supplement', 'Supplements', 15.00, 170, 90, 80, '', 'Approved', 'Active', 'uploads/products/product_1776827352_ef52c48502.jpg', '2026-12-31', '2026-03-05 09:33:16'),
(5, 'Omega-3 Fish Oil', '9551000000055', 'Heart and brain support capsules', 'Supplements', 24.00, 149, 149, 149, '', 'Approved', 'Active', 'uploads/products/product_1776827250_8ab1a40274.jpg', '2026-12-31', '2026-03-05 09:33:16'),
(6, 'Multivitamin Complex', '9551000000062', 'Daily essential vitamins', 'Supplements', 19.00, 81, 81, 81, '', 'Approved', 'Active', 'uploads/products/product_1776827219_e87d31753d.png', '2026-12-31', '2026-03-05 09:33:16'),
(8, 'Ibuprofen 400mg', '9551000000086', 'Anti-inflammatory pain relief tablets', 'Medication', 8.00, 115, 20, 95, '', 'Approved', 'Active', 'uploads/products/product_1776827183_5aef82124b.png', '2026-12-31', '2026-03-05 09:33:16'),
(9, 'Hand Sanitizer 500ml', '9551000000093', 'Alcohol-based hand sanitizer', 'Personal Care', 6.00, 99, 20, 79, '', 'Approved', 'Active', 'uploads/products/product_1776827130_5c9c01d457.jpg', '2027-03-01', '2026-03-05 09:33:16'),
(11, 'Probiotic Capsules', '9551000000116', 'Digestive health capsules', 'Supplements', 29.00, 150, 100, 50, '', 'Approved', 'Active', 'uploads/products/product_1776827333_989015b5c3.jpg', '2026-12-31', '2026-03-05 09:33:16'),
(12, 'Zinc Tablets 50mg', '9551000000123', 'Mineral supplement for immunity', 'Supplements', 11.00, 180, 90, 90, '', 'Approved', 'Active', 'uploads/products/product_1776827369_71095150a3.jpg', '2026-12-31', '2026-03-05 09:33:16'),
(13, 'Product 3', '9551000000130', 'This is Product 3', 'Personal Care', 50.00, 5, 1, 4, '', 'Approved', 'Inactive', 'uploads/products/product_1776273489_18c3abc621.png', '2026-05-10', '2026-04-15 17:21:28'),
(14, 'HS', '9551000000147', 'ss', 'Personal Care', 23.00, 2, 0, 2, 'Both', 'Approved', 'Inactive', 'uploads/products/product_1776830953_07986785a3.jpg', '2026-04-22', '2026-04-22 06:25:33'),
(85, 'Panadol Actifast 500mg 20s', '9556006000011', 'Fast-acting paracetamol tablets for fever and mild pain relief.', 'Pain Relief', 12.90, 120, 0, 120, 'Both', 'Approved', 'Active', 'uploads/products/panadol-actifast.jpg', '2027-08-31', '2026-05-14 15:06:15'),
(86, 'Hurixs Fever Patch Kids 6s', '9556006000028', 'Cooling gel patches suitable for children during fever care.', 'Child Care', 9.50, 80, 0, 80, 'Physical', 'Approved', 'Active', 'uploads/products/hurixs-fever-patch.jpg', '2027-11-15', '2026-05-14 15:06:15'),
(87, 'Blackmores Bio C 1000 60s', '9556006000035', 'Vitamin C supplement with rose hips for daily immune support.', 'Vitamins', 48.00, 45, 0, 45, 'Both', 'Approved', 'Active', 'uploads/products/blackmores-bio-c.jpg', '2028-02-28', '2026-05-14 15:06:15'),
(88, 'Hansaplast Elastic Plaster 20s', '9556006000042', 'Flexible adhesive plasters for minor cuts and scrapes.', 'First Aid', 7.90, 148, 148, 148, 'Both', 'Approved', 'Active', 'uploads/products/hansaplast-elastic.jpg', '2028-01-10', '2026-05-14 15:06:15'),
(89, 'Betadine Antiseptic Solution 120ml', '9556006000059', 'Antiseptic solution for wound cleansing and skin disinfection.', 'First Aid', 18.50, 69, 69, 69, 'Physical', 'Approved', 'Active', 'uploads/products/betadine-solution.jpg', '2027-06-30', '2026-05-14 15:06:15'),
(90, 'Cetaphil Gentle Skin Cleanser 125ml', '9556006000066', 'Soap-free gentle cleanser for sensitive and dry skin.', 'Skin Care', 29.90, 55, 0, 55, 'Both', 'Approved', 'Active', 'uploads/products/cetaphil-cleanser.jpg', '2028-04-20', '2026-05-14 15:06:15'),
(91, 'KoolFever Adult Cooling Gel 4s', '9556006000073', 'Cooling gel sheets for temporary relief during fever.', 'Health Care', 8.80, 90, 0, 90, 'Online', 'Approved', 'Active', 'uploads/products/koolfever-adult.jpg', '2027-10-05', '2026-05-14 15:06:15'),
(92, 'Difflam Forte Throat Spray 15ml', '9556006000080', 'Throat spray for sore throat relief.', 'Cough & Cold', 32.50, 38, 0, 38, 'Both', 'Pending', 'Active', 'uploads/products/difflam-forte.jpg', '2027-12-01', '2026-05-14 15:06:15'),
(93, 'Optrex Eye Wash 110ml', '9556006000097', 'Sterile eye wash for tired or irritated eyes.', 'Eye Care', 21.90, 34, 0, 34, 'Physical', 'Approved', 'Active', 'uploads/products/optrex-eye-wash.jpg', '2027-09-12', '2026-05-14 15:06:15'),
(94, 'Demo Discontinued Multivitamin 30s', '9556006000103', 'Inactive demo product for showing disabled catalogue items.', 'Vitamins', 19.90, 0, 0, 0, 'Online', 'Rejected', 'Inactive', 'uploads/products/demo-discontinued.jpg', '2026-12-31', '2026-05-14 15:06:15');

-- --------------------------------------------------------

--
-- Table structure for table `product_submissions`
--

CREATE TABLE `product_submissions` (
  `submissionID` int(10) UNSIGNED NOT NULL,
  `userID` int(10) UNSIGNED NOT NULL,
  `productName` varchar(255) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `productDescription` text DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stockQuantity` int(11) NOT NULL DEFAULT 0,
  `imagePath` varchar(255) DEFAULT NULL,
  `expiryDate` date DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `rejectionReason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_submissions`
--

INSERT INTO `product_submissions` (`submissionID`, `userID`, `productName`, `barcode`, `productDescription`, `category`, `price`, `stockQuantity`, `imagePath`, `expiryDate`, `status`, `rejectionReason`, `created_at`, `updated_at`) VALUES
(1, 4, 'Product Image URL', '9552000000014', 'Product Image URL', '0', 90.00, 100, 'uploads/products/product_1776271660_fd82fc54e0.png', '2026-04-30', 'Rejected', 'Category now shown', '2026-04-15 16:47:40', '2026-04-15 17:17:08'),
(2, 4, 'Product 3', '9552000000021', 'This is Product 3', '0', 50.00, 5, 'uploads/products/product_1776273489_18c3abc621.png', '2026-05-10', 'Approved', NULL, '2026-04-15 17:18:09', '2026-04-15 17:21:28'),
(3, 4, 'Product 3', '9552000000038', 'This is Product 3', '0', 50.00, 5, 'uploads/products/product_1776273489_45ad7cba0a.png', '2026-05-10', 'Rejected', 'Incomplete', '2026-04-15 17:18:09', '2026-04-22 04:16:23'),
(4, 4, 'Product 4', '9552000000045', 'This is Product 4', 'Supplements', 20.00, 10, 'uploads/products/product_1776273738_2c1ffc4d9a.jpg', '2026-04-28', 'Pending', NULL, '2026-04-15 17:22:18', '2026-04-15 17:22:18'),
(5, 4, 'HS', '9552000000052', 'ss', 'Personal Care', 23.00, 3, 'uploads/products/product_1776830953_07986785a3.jpg', '2026-04-22', 'Approved', NULL, '2026-04-22 04:09:13', '2026-04-22 06:25:33'),
(6, 4, 'HS', '9552000000069', 'ss', 'Personal Care', 23.00, 3, 'uploads/products/product_1776830990_883e922cac.jpg', '2026-04-22', 'Rejected', 'Imcomplete details', '2026-04-22 04:09:50', '2026-04-22 04:15:40'),
(7, 4, 'Testing', '9552000000076', 'Test', 'Personal Care', 2.00, 2, 'uploads/products/product_1776832315_1b054f7a5e.png', '2026-04-21', 'Rejected', 'Incomplete details', '2026-04-22 04:31:55', '2026-04-22 06:25:31');

-- --------------------------------------------------------

--
-- Table structure for table `staff_details`
--

CREATE TABLE `staff_details` (
  `staffID` int(10) UNSIGNED NOT NULL,
  `userID` int(10) UNSIGNED NOT NULL,
  `position` varchar(100) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `join_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_details`
--

INSERT INTO `staff_details` (`staffID`, `userID`, `position`, `status`, `join_date`) VALUES
(3, 4, 'Pharmacist', 'Active', '2026-04-10');

-- --------------------------------------------------------

--
-- Table structure for table `support_chat`
--

CREATE TABLE `support_chat` (
  `chat_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `sender` enum('admin','customer') NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_chat`
--

INSERT INTO `support_chat` (`chat_id`, `customer_id`, `sender`, `message`, `created_at`, `is_read`) VALUES
(8, 6, 'customer', 'Hi,can i visit your store?', '2026-04-19 14:23:59', 1),
(9, 6, 'admin', 'Sure', '2026-04-22 03:19:11', 1),
(11, 8, 'customer', 'Hi', '2026-05-14 02:41:42', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userID` int(10) UNSIGNED NOT NULL,
  `userName` varchar(50) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','customer') NOT NULL DEFAULT 'customer',
  `phoneNo` varchar(30) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `address` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userID`, `userName`, `firstName`, `lastName`, `email`, `password`, `role`, `phoneNo`, `created_at`, `address`, `department`, `avatar`, `updated_at`) VALUES
(1, 'admin1', 'sheaujun', '', 'tansheaujun1308@gmail.com', '$2a$12$T789zSQQX81e6WKjlQdiAuVZTCe.eFk0mfmafZmrYSN4UlM5LLOJO', 'admin', '011-27334338', '2026-03-31 14:21:16', '1542', 'Admin', 'uploads/avatars/1_1776090779_534c2a775a.jpg', '2026-04-22 03:19:21'),
(4, 'jun02', 'Chong', 'WK', 'jun02@graduate.utm.my', '$2y$10$vggWJxMHCRnVw5Enw25DjOW/D7YuDGplubhHpcuEAaTup.W3tCVoq', 'staff', '01125886489', '2026-04-14 10:53:34', '88772', 'Management', '../admin/uploads/avatars/4_1776503012_4085effc19.png', '2026-04-18 09:03:53'),
(5, 'noona', 'Noona', 'Tan', 'noona@gmail.com', '$2y$10$HvDsaeJhEqckiNrlq1uEj.n3Ld7952FFKKYqXjUvsTHZaPPvSdmAi', 'customer', '0186483442', '2026-04-19 20:54:38', '', NULL, 'uploads/avatars/5_1776791647_75d1d68afc.png', '2026-04-21 17:14:07'),
(7, 'customer2', 'cus2', 'tan', 'customer2@gmail.com', '$2y$10$euTdq3n8GFKWg.UWbMu9kOolgKC6Lr6KTvaUDxloYSWQIiz7FN12a', 'customer', '0289746372', '2026-04-22 12:52:27', NULL, NULL, NULL, '2026-04-22 04:52:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `fk_cart_user` (`user_id`),
  ADD KEY `fk_cart_product` (`product_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_customers_name` (`name`),
  ADD KEY `idx_customers_email` (`email`),
  ADD KEY `idx_customers_status` (`status`),
  ADD KEY `fk_customers_user` (`user_id`);

--
-- Indexes for table `customer_orders`
--
ALTER TABLE `customer_orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `order_code` (`order_code`),
  ADD KEY `idx_orders_customer` (`customer_id`),
  ADD KEY `idx_orders_date` (`order_date`),
  ADD KEY `idx_orders_status` (`status`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `fk_order_items_order` (`order_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `token` (`token`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`productID`),
  ADD KEY `idx_products_barcode` (`barcode`);

--
-- Indexes for table `product_submissions`
--
ALTER TABLE `product_submissions`
  ADD PRIMARY KEY (`submissionID`),
  ADD KEY `fk_submission_user` (`userID`),
  ADD KEY `idx_product_submissions_barcode` (`barcode`);

--
-- Indexes for table `staff_details`
--
ALTER TABLE `staff_details`
  ADD PRIMARY KEY (`staffID`),
  ADD KEY `fk_staff_user` (`userID`);

--
-- Indexes for table `support_chat`
--
ALTER TABLE `support_chat`
  ADD PRIMARY KEY (`chat_id`),
  ADD KEY `idx_support_customer` (`customer_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userID`),
  ADD UNIQUE KEY `userName` (`userName`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `customer_orders`
--
ALTER TABLE `customer_orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `productID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `product_submissions`
--
ALTER TABLE `product_submissions`
  MODIFY `submissionID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `staff_details`
--
ALTER TABLE `staff_details`
  MODIFY `staffID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `support_chat`
--
ALTER TABLE `support_chat`
  MODIFY `chat_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`productID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`userID`) ON DELETE SET NULL;

--
-- Constraints for table `customer_orders`
--
ALTER TABLE `customer_orders`
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_submissions`
--
ALTER TABLE `product_submissions`
  ADD CONSTRAINT `fk_submission_user` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `staff_details`
--
ALTER TABLE `staff_details`
  ADD CONSTRAINT `fk_staff_user` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `support_chat`
--
ALTER TABLE `support_chat`
  ADD CONSTRAINT `fk_support_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
