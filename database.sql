CREATE DATABASE IF NOT EXISTS `shop_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `shop_db`;

-- 2. Bảng sản phẩm (products)
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `brand` VARCHAR(100) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `price` DECIMAL(15,2) NOT NULL,
  `cost` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `old_price` DECIMAL(15,2) DEFAULT NULL,
  `ram` VARCHAR(50) DEFAULT '-',
  `rom` VARCHAR(50) DEFAULT '-',
  `chip` VARCHAR(100) DEFAULT '-',
  `screen` VARCHAR(100) DEFAULT '-',
  `camera` VARCHAR(100) DEFAULT '-',
  `battery` VARCHAR(100) DEFAULT '-',
  `stock` INT NOT NULL DEFAULT 10,
  `rating` DECIMAL(2,1) DEFAULT 5.0,
  `is_new` TINYINT(1) DEFAULT 1,
  `img` VARCHAR(500) DEFAULT NULL,
  `image_url` VARCHAR(500) DEFAULT NULL,
  `imported` INT NOT NULL DEFAULT 0,
  `sold` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Bảng đơn hàng (orders)
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` VARCHAR(30) PRIMARY KEY,
  `customer` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `order_date` VARCHAR(30) NOT NULL,
  `total` DECIMAL(15,2) NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'Mới đặt',
  `pay` VARCHAR(50) NOT NULL DEFAULT 'Tiền mặt',
  `processed_by` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Bảng người dùng / khách hàng (users)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` VARCHAR(30) PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) DEFAULT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'customer',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `role`) VALUES
('KH001', 'Trần Văn Nam', 'vannam@gmail.com', '0987654321', 'customer');

-- 4. Chèn dữ liệu mẫu cho sản phẩm iPhone (Phone Store)
INSERT INTO `products` (`id`, `code`, `name`, `brand`, `category`, `price`, `cost`, `old_price`, `ram`, `rom`, `chip`, `screen`, `camera`, `battery`, `stock`, `rating`, `is_new`, `img`, `image_url`, `imported`, `sold`) VALUES
(1, 'SP001', 'iPhone 17 Pro Max 256GB', 'Apple', 'iPhone 17 Series', 37990000, 32290000, 41790000, '12 GB', '256 GB', 'Apple A19 Pro', '6.9\" OLED 120Hz', '48MP Triple Zoom', '4800 mAh', 15, 5.0, 1, 'img/iphone17.webp', 'img/iphone17.webp', 50, 0),
(2, 'SP002', 'iPhone 16 Pro Max 256GB', 'Apple', 'iPhone 16 Series', 34990000, 30200000, 36990000, '8 GB', '256 GB', 'Apple A18 Pro', '6.9\" OLED 120Hz', '48MP + 48MP + 12MP', '4685 mAh', 24, 4.9, 1, 'img/iphone-16-pro-max.png', 'img/iphone-16-pro-max.png', 60, 36),
(3, 'SP003', 'iPhone 16 Pro 128GB', 'Apple', 'iPhone 16 Series', 28990000, 24890000, 31990000, '8 GB', '128 GB', 'Apple A18 Pro', '6.3\" OLED 120Hz', '48MP + 48MP + 12MP', '3582 mAh', 20, 4.9, 1, 'img/iphone-16-pro-max.png', 'img/iphone-16-pro-max.png', 50, 15),
(4, 'SP004', 'iPhone 16 128GB', 'Apple', 'iPhone 16 Series', 22990000, 19500000, 24990000, '8 GB', '128 GB', 'Apple A18', '6.1\" OLED 60Hz', '48MP + 12MP', '3561 mAh', 30, 4.8, 1, 'img/iphone-16-den-128.webp', 'img/iphone-16-den-128.webp', 80, 25),
(5, 'SP005', 'iPhone 15 Pro Max 256GB', 'Apple', 'iPhone 15 Series', 29990000, 25800000, 34990000, '8 GB', '256 GB', 'Apple A17 Pro', '6.7\" OLED 120Hz', '48MP + 12MP + 12MP', '4422 mAh', 18, 4.8, 0, 'img/iphone15-pro-max-titan-xanh.webp', 'img/iphone15-pro-max-titan-xanh.webp', 70, 45),
(6, 'SP006', 'iPhone 15 128GB', 'Apple', 'iPhone 15 Series', 18990000, 16400000, 21990000, '6 GB', '128 GB', 'Apple A16 Bionic', '6.1\" OLED 60Hz', '48MP + 12MP', '3349 mAh', 41, 4.7, 0, 'img/iphone15-hong.webp', 'img/iphone15-hong.webp', 80, 39),
(7, 'SP007', 'iPhone 14 Pro Max 128GB', 'Apple', 'iPhone 14 Series', 24990000, 21500000, 27990000, '6 GB', '128 GB', 'Apple A16 Bionic', '6.7\" OLED 120Hz', '48MP + 12MP + 12MP', '4323 mAh', 12, 4.7, 0, 'img/iphone-14-pro-max.png', 'img/iphone-14-pro-max.png', 50, 38),
(8, 'SP008', 'iPhone 14 128GB', 'Apple', 'iPhone 14 Series', 16990000, 14500000, 19990000, '6 GB', '128 GB', 'Apple A15 Bionic', '6.1\" OLED 60Hz', '12MP + 12MP', '3279 mAh', 25, 4.6, 0, 'img/iphone14.webp', 'img/iphone14.webp', 60, 35),
(9, 'SP009', 'iPhone 13 128GB', 'Apple', 'iPhone 11 - 13', 13990000, 11800000, 16990000, '4 GB', '128 GB', 'Apple A15 Bionic', '6.1\" OLED 60Hz', '12MP + 12MP', '3227 mAh', 35, 4.6, 0, 'img/iphone13.webp', 'img/iphone13.webp', 100, 65),
(10, 'SP010', 'iPhone 11 64GB', 'Apple', 'iPhone 11 - 13', 8990000, 7500000, 10990000, '4 GB', '64 GB', 'Apple A13 Bionic', '6.1\" Liquid Retina', '12MP + 12MP', '3110 mAh', 50, 4.5, 0, 'img/iphone-11.webp', 'img/iphone-11.webp', 120, 70);

-- 5. Cấp quyền truy cập từ xa cho mạng Tailscale (100.%) và mạng LAN
CREATE USER IF NOT EXISTS 'shop_user'@'%' IDENTIFIED BY 'Shop@123456';
GRANT ALL PRIVILEGES ON `shop_db`.* TO 'shop_user'@'%';
FLUSH PRIVILEGES;
