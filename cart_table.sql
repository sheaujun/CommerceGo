-- Create cart table (drop if exists first)
DROP TABLE IF EXISTS `cart`;

CREATE TABLE `cart` (
  `cart_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for table `cart`
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `fk_cart_user` (`user_id`),
  ADD KEY `fk_cart_product` (`product_id`);

-- AUTO_INCREMENT for table `cart`
ALTER TABLE `cart`
  MODIFY `cart_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

-- Constraints for table `cart`
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`productID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`userID`) ON DELETE CASCADE;