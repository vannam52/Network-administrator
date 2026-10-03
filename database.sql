-- ========================================================
-- ĐỀ TÀI 10 - QUẢN TRỊ MẠNG: CƠ SỞ DỮ LIỆU TẬP TRUNG (VM 4)
-- IP MÁY CHỦ DATABASE: 100.72.145.103 (Mạng Tailscale) / 192.168.10.40 (Mạng LAN)
-- Phụ trách: Thành viên 5 - Thành Phát (Database, DevOps & Cloud)
-- ========================================================

-- 1. Tạo Database
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

-- 4. Chèn dữ liệu mẫu cho sản phẩm
INSERT INTO `products` (`id`, `code`, `name`, `brand`, `category`, `price`, `cost`, `old_price`, `ram`, `rom`, `chip`, `screen`, `camera`, `battery`, `stock`, `rating`, `is_new`, `imported`, `sold`) VALUES
(1, 'SP001', 'iPhone 16 Pro Max 256GB', 'Apple', 'Điện thoại', 34990000, 30200000, 36990000, '8 GB', '256 GB', 'Apple A18 Pro', '6.9\" OLED 120Hz', '48MP + 48MP + 12MP', '4685 mAh', 24, 4.9, 1, 60, 36),
(2, 'SP002', 'Samsung Galaxy S25 Ultra 512GB', 'Samsung', 'Điện thoại', 33490000, 28900000, 35990000, '12 GB', '512 GB', 'Snapdragon 8 Elite', '6.9\" Dynamic AMOLED 2X', '200MP + 50MP + 10MP + 50MP', '5000 mAh', 8, 4.8, 1, 40, 32),
(3, 'SP003', 'iPhone 15 128GB', 'Apple', 'Điện thoại', 18990000, 16400000, 21990000, '6 GB', '128 GB', 'Apple A16 Bionic', '6.1\" OLED 60Hz', '48MP + 12MP', '3349 mAh', 41, 4.7, 0, 80, 39),
(4, 'SP008', 'iPad Pro M4 11 inch Wi-Fi', 'Apple', 'Máy tính bảng', 28990000, 25200000, NULL, '8 GB', '256 GB', 'Apple M4', '11\" Tandem OLED', '12MP', '31.29 Wh', 14, 4.9, 0, 20, 6),
(5, 'SP009', 'AirPods Pro 2 USB-C', 'Apple', 'Tai nghe', 5990000, 4700000, 6790000, '-', '-', 'Apple H2', '-', '-', '6 giờ', 62, 4.8, 0, 100, 38),
(6, 'SP012', 'Sạc nhanh MagSafe 25W', 'Apple', 'Phụ kiện', 1090000, 720000, NULL, '-', '-', '-', '-', '-', '-', 120, 4.4, 0, 200, 80);

-- 5. Cấp quyền truy cập từ xa cho mạng Tailscale (100.%) và mạng LAN
CREATE USER IF NOT EXISTS 'shop_user'@'%' IDENTIFIED BY 'Shop@123456';
GRANT ALL PRIVILEGES ON `shop_db`.* TO 'shop_user'@'%';
FLUSH PRIVILEGES;
