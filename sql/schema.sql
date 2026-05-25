-- 建立資料庫
CREATE DATABASE IF NOT EXISTS concert_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE concert_system;

-- 1. 使用者表 (會員與管理員)
CREATE TABLE User (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'manager') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. 促銷碼表 (管理員可 CRUD)
CREATE TABLE PromoCode (
    promo_id INT AUTO_INCREMENT PRIMARY KEY,
    code_name VARCHAR(50) NOT NULL UNIQUE, -- 例如: 'NCT2026'
    discount_amount INT NOT NULL,          -- 折扣金額，例如: 200
    is_active BOOLEAN DEFAULT TRUE         -- 是否啟用
) ENGINE=InnoDB;

-- 3. 演唱會表
CREATE TABLE Concert (
    concert_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT
) ENGINE=InnoDB;

-- 4. 場次表 (一場演唱會有多個日期場次)
CREATE TABLE ShowDate (
    show_id INT AUTO_INCREMENT PRIMARY KEY,
    concert_id INT,
    show_datetime DATETIME NOT NULL,
    status ENUM('available', 'sold_out', 'ended') DEFAULT 'available',
    FOREIGN KEY (concert_id) REFERENCES Concert(concert_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. 座位主表 
CREATE TABLE Seat (
    seat_id INT AUTO_INCREMENT PRIMARY KEY,
    show_id INT,
    seat_number VARCHAR(20) NOT NULL,
    price INT NOT NULL,
    status ENUM('available', 'reserved', 'sold') DEFAULT 'available',
    FOREIGN KEY (show_id) REFERENCES ShowDate(show_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. 訂單總表
CREATE TABLE Orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    show_id INT,
    promo_id INT NULL,                     -- 記錄這筆訂單用了哪組促銷碼 (可為空)
    total_price INT NOT NULL,              -- 扣除折扣後的最終總金額
    status ENUM('pending_payment', 'paid', 'cancelled') DEFAULT 'pending_payment',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES User(user_id),
    FOREIGN KEY (show_id) REFERENCES ShowDate(show_id),
    FOREIGN KEY (promo_id) REFERENCES PromoCode(promo_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 7. 門票明細表 (實現實名制與一筆訂單多個座位的核心)
CREATE TABLE Ticket (
    ticket_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    seat_id INT UNIQUE,                    -- 加上 UNIQUE 限制，全系統這張椅子的票只能被建立一次 (防超賣)
    real_name VARCHAR(100) NOT NULL,       -- 【加分功能：實名制姓名】
    id_number VARCHAR(50) NOT NULL,        -- 【加分功能：身分證字號/護照號】
    FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (seat_id) REFERENCES Seat(seat_id)
) ENGINE=InnoDB;