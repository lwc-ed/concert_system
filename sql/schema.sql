-- 0. 環境重置 (開發階段適用)
SET FOREIGN_KEY_CHECKS = 0; -- 暫時關閉外鍵檢查

DROP TABLE IF EXISTS Ticket;
DROP TABLE IF EXISTS Orders;
DROP TABLE IF EXISTS Seat;
DROP TABLE IF EXISTS ShowDate;
DROP TABLE IF EXISTS Concert;
DROP TABLE IF EXISTS Organizer;
DROP TABLE IF EXISTS PromoCode;
DROP TABLE IF EXISTS User;

SET FOREIGN_KEY_CHECKS = 1;
-- 0. 建立資料庫
CREATE DATABASE IF NOT EXISTS concert_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE concert_system;

-- 1. 使用者表 (會員與管理員)
CREATE TABLE User (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    real_name VARCHAR(50) NOT NULL,
    birth_date DATETIME NOT NULL,
    phone_num VARCHAR(20) NOT NULL,
    id_number VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL,
    user_address VARCHAR(255) NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'manager') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. 促銷碼表 (管理員可 CRUD)
CREATE TABLE PromoCode (
    promo_id INT AUTO_INCREMENT PRIMARY KEY,
    code_name VARCHAR(50) NOT NULL UNIQUE, -- 例如: 'NCT2026'
    discount_amount INT NOT NULL,          -- 折扣金額，例如: 200
    usage_limit INT NULL,                  -- 最多可使用次數，NULL 代表不限次數
    starts_at DATETIME NULL,               -- 開始可使用時間
    expires_at DATETIME NULL,              -- 到期時間
    is_active BOOLEAN DEFAULT TRUE,        -- 是否啟用
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. 主辦單位表
CREATE TABLE Organizer (
    organizer_id INT AUTO_INCREMENT PRIMARY KEY,
    organizer_name VARCHAR(100) NOT NULL UNIQUE,
    contact_person VARCHAR(100) NULL,
    contact_email VARCHAR(100) NULL,
    contact_phone VARCHAR(30) NULL,
    organizer_address VARCHAR(255) NULL,
    note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. 演唱會表
CREATE TABLE Concert (
    concert_id INT AUTO_INCREMENT PRIMARY KEY,
    organizer_id INT NULL,                          -- 外鍵，連結 Organizer
    artist VARCHAR(100) NOT NULL,                    -- 藝人/團體名稱
    title VARCHAR(255) NOT NULL,                     -- 演唱會完整標題
    venue VARCHAR(100) NOT NULL,                     -- 場地名稱 (如：高雄巨蛋)
    concert_address VARCHAR(255) NOT NULL,                   -- 場地地址
    image VARCHAR(255),                              -- 演唱會海報圖片路徑或 URL
    sale_start DATETIME NOT NULL,                    -- 啟售開始時間
    sale_end DATETIME NOT NULL,                      -- 售票截止時間
    description TEXT,                                -- 活動簡介
    notice TEXT,                                     -- 購票及入場注意事項
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES Organizer(organizer_id) ON DELETE SET NULL
);

-- 5 . 演唱會場次時間表
CREATE TABLE ShowDate (
    show_id INT AUTO_INCREMENT PRIMARY KEY,
    concert_id INT NOT NULL,                         -- 外鍵，連結 Concert
    show_datetime DATETIME NOT NULL,                 -- 演出日期與時間 (如：2026-07-18 17:00:00)
    status VARCHAR(50) DEFAULT 'available',          -- 狀態：available / ended / sold_out
    FOREIGN KEY (concert_id) REFERENCES Concert(concert_id) ON DELETE CASCADE
);

-- 6. 座位資料表
CREATE TABLE Seat (
    seat_id INT AUTO_INCREMENT PRIMARY KEY,
    show_id INT NOT NULL,                            -- 外鍵，連結 ShowDate
    seat_number VARCHAR(50) NOT NULL,                -- 座位號碼 (如：209區-15號)
    price DECIMAL(10, 2) NOT NULL,                   -- 票價 (使用 DECIMAL 儲存金錢較精確)
    status VARCHAR(50) DEFAULT 'available',          -- 座位狀態：available / reserved / sold
    FOREIGN KEY (show_id) REFERENCES ShowDate(show_id) ON DELETE CASCADE
);
-- 7. 訂單總表
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

-- 8. 門票明細表 (實現實名制與一筆訂單多個座位的核心)
CREATE TABLE Ticket (
    ticket_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    seat_id INT UNIQUE,                    -- 加上 UNIQUE 限制，全系統這張椅子的票只能被建立一次 (防超賣)
    real_name VARCHAR(100) NOT NULL,       -- 【加分功能：實名制姓名】
    id_number VARCHAR(50) NOT NULL,        -- 【加分功能：身分證字號/護照號】
    FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (seat_id) REFERENCES Seat(seat_id)
) ENGINE=InnoDB;
