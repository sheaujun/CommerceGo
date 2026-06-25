-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 13, 2026 at 03:21 PM
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
(1, NULL, 'CUST001', 'Ahmad Razak', 'ahmad.razak@email.com', '+60 12-345 6789', '123 Jalan Ampang, 50450 Kuala Lumpur', '2024-01-15', 'active', '2026-03-22 16:01:44', '2026-03-22 16:01:44'),
(2, NULL, 'CUST002', 'Siti Nurhaliza', 'siti.n@email.com', '+60 13-456 7890', '45 Jalan Bukit Bintang, 55100 Kuala Lumpur', '2024-02-20', 'active', '2026-03-22 16:01:44', '2026-03-22 16:01:44'),
(3, NULL, 'CUST003', 'Raj Kumar', 'raj.kumar@email.com', '+60 14-567 8901', '78 Jalan Petaling, 50000 Kuala Lumpur', '2023-11-10', 'active', '2026-03-22 16:01:44', '2026-03-22 16:01:44'),
(4, NULL, 'CUST004', 'Lee Wei Ming', 'weiming.lee@email.com', '+60 15-678 9012', '92 Jalan Imbi, 55100 Kuala Lumpur', '2024-03-05', 'inactive', '2026-03-22 16:01:44', '2026-03-22 16:01:44'),
(5, NULL, 'CUST005', 'Fatimah Abdullah', 'fatimah.a@email.com', '+60 16-789 0123', '156 Jalan Tun Razak, 50400 Kuala Lumpur', '2023-08-22', 'active', '2026-03-22 16:01:44', '2026-03-22 16:01:44');

-- --------------------------------------------------------

--
-- Table structure for table `customer_orders`
--

CREATE TABLE `customer_orders` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `order_code` varchar(20) NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `order_date` date NOT NULL DEFAULT curdate(),
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `items` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('Pending','Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  `paymentMethod` varchar(80) DEFAULT 'Unknown',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_orders`
--

INSERT INTO `customer_orders` (`order_id`, `order_code`, `customer_id`, `order_date`, `total`, `items`, `status`, `paymentMethod`, `created_at`, `updated_at`) VALUES
(1, 'ORD001', 1, '2024-01-20', 89.90, 3, 'Delivered', 'Credit Card', '2026-03-22 16:01:44', '2026-03-22 16:01:44'),
(2, 'ORD002', 1, '2024-01-18', 156.00, 5, 'Delivered', 'FPX - Maybank', '2026-03-22 16:01:44', '2026-03-22 16:01:44'),
(3, 'ORD003', 1, '2024-01-10', 45.50, 2, 'Delivered', 'Touch n Go eWallet', '2026-03-22 16:01:44', '2026-03-22 16:01:44'),
(4, 'ORD004', 2, '2024-01-22', 120.00, 4, 'Processing', 'Cash on Delivery', '2026-03-22 16:01:44', '2026-03-22 16:01:44'),
(5, 'ORD005', 2, '2024-01-15', 78.30, 2, 'Delivered', 'Credit Card', '2026-03-22 16:01:44', '2026-03-22 16:01:44'),
(6, 'ORD006', 3, '2024-01-21', 200.00, 6, 'Shipped', 'Credit Card', '2026-03-22 16:01:44', '2026-03-22 16:01:44'),
(7, 'ORD007', 3, '2024-01-12', 95.00, 3, 'Delivered', 'Touch n Go eWallet', '2026-03-22 16:01:44', '2026-03-22 16:01:44'),
(8, 'ORD008', 4, '2024-01-05', 65.80, 2, 'Delivered', 'Cash on Delivery', '2026-03-22 16:01:44', '2026-03-22 16:01:44'),
(9, 'ORD009', 5, '2024-01-23', 145.90, 4, 'Shipped', 'Credit Card', '2026-03-22 16:01:44', '2026-03-30 14:38:08'),
(10, 'ORD010', 5, '2024-01-19', 88.00, 3, 'Delivered', 'FPX - Maybank', '2026-03-22 16:01:44', '2026-03-22 16:01:44');

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
(1, 1, 'Paracetamol 500mg', 2, 5.99, '2026-03-22 15:57:41'),
(2, 1, 'Vitamin C 1000mg', 1, 15.99, '2026-03-22 15:57:41'),
(3, 2, 'Omega-3 Fish Oil', 2, 24.99, '2026-03-22 15:57:41'),
(4, 2, 'Multivitamin Complex', 1, 19.99, '2026-03-22 15:57:41'),
(5, 3, 'Ibuprofen 400mg', 3, 8.49, '2026-03-22 15:57:41'),
(6, 4, 'Hand Sanitizer 500ml', 5, 6.99, '2026-03-22 15:57:41'),
(7, 4, 'Bandage Pack', 2, 4.99, '2026-03-22 15:57:41'),
(8, 5, 'Probiotic Capsules', 1, 29.99, '2026-03-22 15:57:41'),
(9, 5, 'Zinc Tablets 50mg', 2, 11.99, '2026-03-22 15:57:41'),
(10, 1, 'Paracetamol 500mg', 2, 5.99, '2026-03-22 16:01:44'),
(11, 1, 'Vitamin C 1000mg', 1, 15.99, '2026-03-22 16:01:44'),
(12, 2, 'Omega-3 Fish Oil', 2, 24.99, '2026-03-22 16:01:44'),
(13, 2, 'Multivitamin Complex', 1, 19.99, '2026-03-22 16:01:44'),
(14, 3, 'Ibuprofen 400mg', 3, 8.49, '2026-03-22 16:01:44'),
(15, 4, 'Hand Sanitizer 500ml', 5, 6.99, '2026-03-22 16:01:44'),
(16, 4, 'Bandage Pack', 2, 4.99, '2026-03-22 16:01:44'),
(17, 5, 'Probiotic Capsules', 1, 29.99, '2026-03-22 16:01:44'),
(18, 5, 'Zinc Tablets 50mg', 2, 11.99, '2026-03-22 16:01:44');

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
(1, 'Product1', '9550000000011', 'This is product 1', 'Medication', 10.00, 100, 0, 100, 'Both', 'Approved', 'Active', '', '2026-03-10', '2026-03-05 17:33:16'),
(2, 'Paracetamol 500mg', '9550000000028', 'Pain relief tablets for aches and fever', 'Medication', 5.99, 120, 0, 120, 'Both', 'Approved', 'Active', '', '2026-12-31', '2026-03-05 17:33:16'),
(3, 'Vitamin C 1000mg', '9550000000035', 'Immune support supplement', 'Supplements', 15.99, 80, 0, 80, 'Both', 'Approved', 'Active', '', '2026-12-31', '2026-03-05 17:33:16'),
(4, 'Omega-3 Fish Oil', '9550000000042', 'Heart and brain support capsules', 'Supplements', 24.99, 60, 0, 60, 'Both', 'Approved', 'Active', '', '2026-12-31', '2026-03-05 17:33:16'),
(5, 'Multivitamin Complex', '9550000000059', 'Daily essential vitamins', 'Supplements', 19.99, 70, 0, 70, 'Both', 'Approved', 'Active', '', '2026-12-31', '2026-03-05 17:33:16'),
(6, 'Blood Pressure Monitor', '9550000000066', 'Digital wrist blood pressure monitor', 'Equipment', 89.90, 30, 0, 30, 'Both', 'Approved', 'Active', '', '2027-03-01', '2026-03-05 17:33:16'),
(7, 'Ibuprofen 400mg', '9550000000073', 'Anti-inflammatory pain relief tablets', 'Medication', 8.49, 95, 0, 95, 'Both', 'Approved', 'Active', '', '2026-12-31', '2026-03-05 17:33:16'),
(8, 'Hand Sanitizer 500ml', '9550000000080', 'Alcohol-based hand sanitizer', 'Personal Care', 6.99, 200, 0, 200, 'Both', 'Approved', 'Active', '', '2027-03-01', '2026-03-05 17:33:16'),
(9, 'Bandage Pack', '9550000000097', 'Pack of sterile bandages', 'Equipment', 4.99, 120, 0, 120, 'Both', 'Approved', 'Active', '', '2027-03-01', '2026-03-05 17:33:16'),
(10, 'Probiotic Capsules', '9550000000103', 'Digestive health capsules', 'Supplements', 29.99, 50, 0, 50, 'Both', 'Approved', 'Active', '', '2026-12-31', '2026-03-05 17:33:16'),
(11, 'Zinc Tablets 50mg', '9550000000110', 'Mineral supplement for immunity', 'Supplements', 11.99, 90, 0, 90, 'Both', 'Approved', 'Active', '', '2026-12-31', '2026-03-05 17:33:16');

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
(2, 3, 'Pharmacist', 'Active', '2026-03-06');

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
(1, 1, 'customer', 'Hi, I have a question about my prescription.', '2024-01-23 02:30:00', 1),
(2, 1, 'admin', 'Of course! I am happy to help. What would you like to know?', '2024-01-23 02:32:00', 1),
(3, 1, 'customer', 'I was wondering if I can take Ibuprofen with my current medication?', '2024-01-23 02:33:00', 1),
(4, 1, 'admin', 'Please allow me a moment to check the records.', '2024-01-23 02:34:00', 1),
(5, 2, 'customer', 'When will my order arrive?', '2024-01-25 01:15:00', 1),
(6, 3, 'customer', 'Can I get a refund?', '2024-01-24 04:04:00', 1),
(7, 2, 'admin', 'Hi', '2026-03-22 17:09:18', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `userName` varchar(100) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','customer') NOT NULL DEFAULT 'customer',
  `phoneNo` varchar(40) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`userID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userID`, `userName`, `firstName`, `lastName`, `email`, `password`, `role`, `phoneNo`, `address`, `department`, `avatar`, `created_at`, `updated_at`) VALUES
(1, 'admin1', 'sheaujun', 'tan', 'tansheaujun1308@gmail.com', '$2a$12$T789zSQQX81e6WKjlQdiAuVZTCe.eFk0mfmafZmrYSN4UlM5LLOJO', 'admin', '011-27334338', NULL, NULL, NULL, '2026-03-31 14:21:16', '2026-03-31 14:21:16'),
(3, 'jun02', 'Chong', 'WK', 'jun02@graduate.utm.my', '$2y$10$KCOtHQ5/pdPl/rladCky0ehqZchnnf26hljV/nu6kURcLYozuVN1.', 'staff', '01127334323', NULL, NULL, NULL, '2026-03-31 14:21:16', '2026-03-31 14:21:16');

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
  ADD KEY `idx_product_submissions_barcode` (`barcode`),
  ADD KEY `fk_submission_user` (`userID`);

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
  MODIFY `cart_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customer_orders`
--
ALTER TABLE `customer_orders`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `productID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_submissions`
--
ALTER TABLE `product_submissions`
  MODIFY `submissionID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_details`
--
ALTER TABLE `staff_details`
  MODIFY `staffID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `support_chat`
--
ALTER TABLE `support_chat`
  MODIFY `chat_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
