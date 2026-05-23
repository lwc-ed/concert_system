-- 建立資料庫
CREATE DATABASE IF NOT EXISTS concert_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE concert_system;

-- 1. 使用者表
CREATE TABLE User (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'manager') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. 演唱會表
CREATE TABLE Concert (
    concert_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT
) ENGINE=InnoDB;

-- 3. 場次表 (每場演唱會對應多個場次)
CREATE TABLE ShowDate (
    show_id INT AUTO_INCREMENT PRIMARY KEY,
    concert_id INT,
    show_datetime DATETIME NOT NULL,
    status ENUM('available', 'sold_out', 'ended') DEFAULT 'available',
    FOREIGN KEY (concert_id) REFERENCES Concert(concert_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. 座位表 (與場次關聯)
CREATE TABLE Seat (
    seat_id INT AUTO_INCREMENT PRIMARY KEY,
    show_id INT,
    seat_number VARCHAR(20) NOT NULL,
    price INT NOT NULL,
    status ENUM('available', 'reserved', 'sold') DEFAULT 'available',
    FOREIGN KEY (show_id) REFERENCES ShowDate(show_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. 訂單表
CREATE TABLE Orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    show_id INT,
    total_price INT NOT NULL,
    status ENUM('pending_payment', 'paid', 'cancelled') DEFAULT 'pending_payment',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES User(user_id),
    FOREIGN KEY (show_id) REFERENCES ShowDate(show_id)
) ENGINE=InnoDB;