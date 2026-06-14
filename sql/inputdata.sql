USE concert_system;

-- Keep older local databases compatible with the latest payment page.
DELIMITER $$

DROP PROCEDURE IF EXISTS ensure_order_checkout_columns $$

CREATE PROCEDURE ensure_order_checkout_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'Orders'
          AND COLUMN_NAME = 'payment_method'
    ) THEN
        ALTER TABLE Orders ADD COLUMN payment_method VARCHAR(30) NULL AFTER status;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'Orders'
          AND COLUMN_NAME = 'delivery_method'
    ) THEN
        ALTER TABLE Orders ADD COLUMN delivery_method VARCHAR(30) NULL AFTER payment_method;
    END IF;
END $$

CALL ensure_order_checkout_columns() $$
DROP PROCEDURE ensure_order_checkout_columns $$

DELIMITER ;

-- Re-runnable mock data seed.
-- If teammates already imported an older inputdata.sql, run this file again to refresh
-- Concert / ShowDate / Seat data to match the current frontend prototype.
-- Existing Ticket and Orders rows are cleared because they reference old Seat rows.
USE concert_system;

SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM Ticket;
DELETE FROM Orders;
DELETE FROM PromoCode;
DELETE FROM Seat;
DELETE FROM ShowDate;
DELETE FROM Concert;
DELETE FROM Organizer;

TRUNCATE TABLE Ticket;
TRUNCATE TABLE Orders;
TRUNCATE TABLE Seat;
TRUNCATE TABLE ShowDate;
TRUNCATE TABLE Concert;
TRUNCATE TABLE Organizer;
TRUNCATE TABLE User;

SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE Ticket AUTO_INCREMENT = 1;
ALTER TABLE Orders AUTO_INCREMENT = 1;
ALTER TABLE PromoCode AUTO_INCREMENT = 1;
ALTER TABLE Seat AUTO_INCREMENT = 1;
ALTER TABLE ShowDate AUTO_INCREMENT = 1;
ALTER TABLE Concert AUTO_INCREMENT = 1;
ALTER TABLE Organizer AUTO_INCREMENT = 1;

INSERT INTO `User`
(username, real_name, birth_date, phone_num, id_number, email, user_address, password, role)
VALUES
(
    'test',
    '測試帳號',
    '2000-01-01 00:00:00',
    '0912345678',
    'A123456789',
    'test@example.com',
    'Taiwan',
    '12345678',
    'manager'
);

INSERT INTO `User`
(username, real_name, birth_date, phone_num, id_number, email, user_address, password, role)
VALUES
(
    'lwcissohandsome',
    '李瑋晨很帥',
    '2000-01-01 00:00:01',
    '0987-654-321',
    'A130022039',
    'lwcissohandsome@gmail.com',
    'Taiwan',
    '11111111',
    'customer'
);

INSERT INTO PromoCode
    (code_name, discount_amount, usage_limit, starts_at, expires_at, is_active)
VALUES
    ('WELCOME100', 100, 50, '2026-01-01 00:00:00', '2027-12-31 23:59:59', TRUE),
    ('VIP500', 500, 10, '2026-05-01 00:00:00', '2026-12-31 23:59:59', TRUE),
    ('EXPIRED200', 200, 20, '2025-01-01 00:00:00', '2025-12-31 23:59:59', TRUE),
    ('OFFLINE300', 300, NULL, '2026-01-01 00:00:00', '2027-12-31 23:59:59', FALSE);

INSERT INTO Organizer
    (organizer_id, organizer_name, contact_person, contact_email, contact_phone, organizer_address, note)
VALUES
    (1, '幸福娛樂股份有限公司', '李仕祈', 'chris94502@gmail.com', '02-2345-6789', '台北市信義區幸福路 1 號', '負責大型流行音樂與跨界合作活動。'),
    (2, '晴天活動企劃', '陳婉晴', 'sunny@example.com', '02-8765-4321', '台北市松山區南京東路四段 2 號', '粉絲見面會與小型巡迴活動主辦。'),
    (3, 'Final Call Global', 'Alex Chen', 'finalcall@example.com', '+1-212-555-0199', 'New York, NY, United States', '海外場館與國際巡演窗口。');

INSERT INTO Concert
    (concert_id, organizer_id, artist, title, venue, concert_address, image, sale_start, sale_end, description, notice)
VALUES
    (
        1,
        1,
        '崴崴孟孟 x æspa',
        '史詩級跨界合作 <幸福崴孟演唱會 x æspa>',
        '台北大巨蛋',
        '台北市信義區忠孝東路四段515號',
        'assets/images/concert-1.png',
        '2026-05-01 12:00:00',
        '2026-06-27 23:59:00',
        '跨界幸福舞台、沉浸式燈光與完整現場樂隊編制，打造幸福期末演唱會，有參加都會有幸福草莓蛋糕喔！',
        '【購票常見問題 Q&A】\nQ1: 本場次有實施購票限制嗎？\nA1: 本場次每筆訂單/每位會員最多限購 2 張票，超過系統將會阻擋。\nQ2: 本場次是實名制嗎？可以修改填寫的姓名嗎？\nA2: 本場次為「實名制購票」挑戰項目，送出訂單時必須填寫真實姓名與身分證字號，購票成功後「完全無法修改」，請務必確認後再付款。\nQ3: 每位會員限購幾張門票？\nA3: 為維護購票公平性，每位會員在同一個場次中最多只能購買 2 張門票。\nQ4: 聽說現場有送草莓蛋糕是真的嗎？\nA4: 是的！凡購買本場次門票並成功入場者，皆可領取幸福草莓蛋糕一份。'
    ),
    (
        2,
        2,
        '婉晴',
        '婉晴粉絲見面會・全台巡迴中',
        '台北小巨蛋',
        '台北市松山區南京東路四段2號',
        'assets/images/concert-2.png',
        '2026-03-01 10:00:00',
        '2026-03-31 23:59:00',
        '近距離互動、拍照抽選與粉絲限定舞台內容，適合展示不同活動型態的訂票資訊。',
        '【購票常見問題 Q&A】\nQ1: 本場次有實施購票限制嗎？\nA1: 本場次每筆訂單/每位會員最多限購 2 張票，超過系統將會阻擋。\nQ2: 本場次是實名制嗎？可以修改填寫的姓名嗎？\nA2: 本場次為「實名制購票」挑戰項目，送出訂單時必須填寫真實姓名與身分證字號，購票成功後「完全無法修改」，請務必確認後再付款。\nQ3: 活動結束後還可以購票或釋出座位嗎？\nA3: 本活動已於 2026/04 結束，系統已關閉購票與刷卡通道，不開放新訂單。\nQ4: 之前的未付款訂單會怎麼處理？\nA4: 系統管理員已執行排程，逾期未付款之訂單均已被取消並自動釋出座位。'
    ),
    (
        3,
        3,
        '第八組的帥哥們',
        '史上最屌演唱會・Final Call',
        '百老匯',
        'New York, NY, United States',
        'assets/images/concert-3.png',
        '2027-01-10 12:00:00',
        '2027-06-04 23:59:00',
        '壓軸場次以大型舞台機關與海外場館規格呈現，頁面保留票區、剩餘票數與訂票流程入口。',
        '【購票常見問題 Q&A】\nQ1: 本場次有實施購票限制嗎？\nA1: 本場次每筆訂單/每位會員最多限購 2 張票，超過系統將會阻擋。\nQ2: 本場次是實名制嗎？可以修改填寫的姓名嗎？\nA2: 本場次為「實名制購票」挑戰項目，送出訂單時必須填寫真實姓名與身分證字號，購票成功後「完全無法修改」，請務必確認後再付款。\nQ3: 演唱會可以攜帶應援看板嗎？\nA3: 可以，但寬度不得超過隔壁觀眾的臉。若看板上寫「JYP真的很帥」，現場工作人員會對你投以尊敬的眼神，並有機率觸發隱藏的隨機 VIP 升等機制。'
    );

INSERT INTO ShowDate
    (show_id, concert_id, show_datetime, status)
VALUES
    (101, 1, '2026-06-28 19:30:00', 'sold_out'),
    (102, 1, '2026-06-29 19:30:00', 'available'),
    (201, 2, '2026-04-01 20:00:00', 'ended'),
    (202, 2, '2026-04-08 19:30:00', 'ended'),
    (203, 2, '2026-04-15 19:30:00', 'ended'),
    (301, 3, '2027-06-05 19:00:00', 'available'),
    (302, 3, '2027-06-06 18:30:00', 'available');

DELIMITER $$

DROP PROCEDURE IF EXISTS append_mock_seats $$
DROP PROCEDURE IF EXISTS append_mock_seat_unit $$

CREATE PROCEDURE append_mock_seats(
    IN p_show_id INT,
    IN p_zone_name VARCHAR(50),
    IN p_price INT,
    IN p_quantity INT,
    IN p_status VARCHAR(20)
)
BEGIN
    DECLARE seat_index INT DEFAULT 1;
    DECLARE existing_count INT DEFAULT 0;

    SELECT COUNT(*)
    INTO existing_count
    FROM Seat
    WHERE show_id = p_show_id
      AND SUBSTRING_INDEX(seat_number, '_', 1) = p_zone_name;

    WHILE seat_index <= p_quantity DO
        INSERT INTO Seat (show_id, seat_number, price, status)
        VALUES (
            p_show_id,
            CONCAT(p_zone_name, '_', existing_count + seat_index, '號'),
            p_price,
            p_status
        );

        SET seat_index = seat_index + 1;
    END WHILE;
END $$

CREATE PROCEDURE append_mock_seat_unit(
    IN p_show_id INT,
    IN p_seat_number VARCHAR(50),
    IN p_price INT,
    IN p_status VARCHAR(20)
)
BEGIN
    INSERT INTO Seat (show_id, seat_number, price, status)
    VALUES (p_show_id, p_seat_number, p_price, p_status);
END $$

DELIMITER ;

CALL append_mock_seats(101, '幸福搖滾區1', 5800, 6, 'sold');
CALL append_mock_seats(101, '幸福搖滾區2', 5800, 6, 'sold');
CALL append_mock_seats(101, '幸福崴區1', 4200, 20, 'sold');
CALL append_mock_seats(101, '幸福崴區2', 4200, 20, 'sold');
CALL append_mock_seats(101, '幸福崴區3', 4200, 20, 'sold');
CALL append_mock_seats(101, '幸福崴區4', 4200, 20, 'sold');
CALL append_mock_seats(101, '幸福孟區1', 2800, 30, 'sold');
CALL append_mock_seats(101, '幸福孟區2', 2800, 30, 'sold');
CALL append_mock_seats(101, '幸福孟區3', 2800, 30, 'sold');
CALL append_mock_seats(101, '幸福孟區4', 2800, 30, 'sold');
CALL append_mock_seats(101, '幸福崴孟區1', 1800, 40, 'sold');
CALL append_mock_seats(101, '幸福崴孟區2', 1800, 40, 'sold');
CALL append_mock_seats(101, '幸福崴孟區3', 1800, 40, 'sold');
CALL append_mock_seats(101, '幸福崴孟區4', 1800, 40, 'sold');
CALL append_mock_seats(102, '幸福搖滾區1', 5800, 6, 'sold');
CALL append_mock_seats(102, '幸福搖滾區2', 5800, 6, 'sold');
CALL append_mock_seats(102, '幸福崴區1', 4200, 20, 'sold');
CALL append_mock_seats(102, '幸福崴區2', 4200, 20, 'sold');
CALL append_mock_seats(102, '幸福崴區3', 4200, 20, 'sold');
CALL append_mock_seats(102, '幸福崴區4', 4200, 20, 'sold');
CALL append_mock_seats(102, '幸福孟區1', 2800, 30, 'available');
CALL append_mock_seats(102, '幸福孟區2', 2800, 30, 'available');
CALL append_mock_seats(102, '幸福孟區3', 2800, 30, 'available');
CALL append_mock_seats(102, '幸福孟區4', 2800, 30, 'available');
CALL append_mock_seats(102, '幸福崴孟區1', 1800, 40, 'available');
CALL append_mock_seats(102, '幸福崴孟區2', 1800, 40, 'available');
CALL append_mock_seats(102, '幸福崴孟區3', 1800, 40, 'available');
CALL append_mock_seats(102, '幸福崴孟區4', 1800, 40, 'available');

CALL append_mock_seats(201, '特典區1', 500, 15, 'sold');
CALL append_mock_seats(201, '特典區2', 500, 15, 'sold');
CALL append_mock_seats(201, '一般區1', 300, 12, 'sold');
CALL append_mock_seats(201, '一般區2', 300, 12, 'sold');
CALL append_mock_seats(201, '一般區3', 300, 12, 'sold');
CALL append_mock_seats(201, '一般區4', 300, 12, 'sold');
CALL append_mock_seats(201, '一般區5', 300, 12, 'sold');
CALL append_mock_seats(201, '一般區6', 300, 12, 'sold');
CALL append_mock_seats(201, '一般區7', 300, 12, 'sold');
CALL append_mock_seats(201, '一般區8', 300, 12, 'sold');
CALL append_mock_seats(201, '一般區9', 300, 12, 'sold');
CALL append_mock_seats(201, '一般區10', 300, 12, 'sold');
CALL append_mock_seats(201, '學生區1', 1, 9, 'sold');
CALL append_mock_seats(201, '學生區2', 1, 9, 'sold');
CALL append_mock_seats(201, '學生區3', 1, 8, 'sold');
CALL append_mock_seats(201, '學生區4', 1, 8, 'sold');
CALL append_mock_seats(201, '學生區5', 1, 8, 'sold');
CALL append_mock_seats(201, '學生區6', 1, 8, 'sold');
CALL append_mock_seats(202, '特典區1', 500, 15, 'sold');
CALL append_mock_seats(202, '特典區2', 500, 15, 'sold');
CALL append_mock_seats(202, '一般區1', 300, 12, 'sold');
CALL append_mock_seats(202, '一般區2', 300, 12, 'sold');
CALL append_mock_seats(202, '一般區3', 300, 12, 'sold');
CALL append_mock_seats(202, '一般區4', 300, 12, 'sold');
CALL append_mock_seats(202, '一般區5', 300, 12, 'sold');
CALL append_mock_seats(202, '一般區6', 300, 12, 'sold');
CALL append_mock_seats(202, '一般區7', 300, 12, 'sold');
CALL append_mock_seats(202, '一般區8', 300, 12, 'sold');
CALL append_mock_seats(202, '一般區9', 300, 12, 'sold');
CALL append_mock_seats(202, '一般區10', 300, 12, 'sold');
CALL append_mock_seats(202, '學生區1', 1, 9, 'sold');
CALL append_mock_seats(202, '學生區2', 1, 9, 'sold');
CALL append_mock_seats(202, '學生區3', 1, 8, 'sold');
CALL append_mock_seats(202, '學生區4', 1, 8, 'sold');
CALL append_mock_seats(202, '學生區5', 1, 8, 'sold');
CALL append_mock_seats(202, '學生區6', 1, 8, 'sold');
CALL append_mock_seats(203, '特典區1', 500, 15, 'sold');
CALL append_mock_seats(203, '特典區2', 500, 15, 'sold');
CALL append_mock_seats(203, '一般區1', 300, 12, 'sold');
CALL append_mock_seats(203, '一般區2', 300, 12, 'sold');
CALL append_mock_seats(203, '一般區3', 300, 12, 'sold');
CALL append_mock_seats(203, '一般區4', 300, 12, 'sold');
CALL append_mock_seats(203, '一般區5', 300, 12, 'sold');
CALL append_mock_seats(203, '一般區6', 300, 12, 'sold');
CALL append_mock_seats(203, '一般區7', 300, 12, 'sold');
CALL append_mock_seats(203, '一般區8', 300, 12, 'sold');
CALL append_mock_seats(203, '一般區9', 300, 12, 'sold');
CALL append_mock_seats(203, '一般區10', 300, 12, 'sold');
CALL append_mock_seats(203, '學生區1', 1, 9, 'sold');
CALL append_mock_seats(203, '學生區2', 1, 9, 'sold');
CALL append_mock_seats(203, '學生區3', 1, 8, 'sold');
CALL append_mock_seats(203, '學生區4', 1, 8, 'sold');
CALL append_mock_seats(203, '學生區5', 1, 8, 'sold');
CALL append_mock_seats(203, '學生區6', 1, 8, 'sold');

CALL append_mock_seat_unit(301, '至尊包廂1', 100000, 'available');
CALL append_mock_seat_unit(301, '至尊包廂2', 100000, 'available');
CALL append_mock_seats(301, '搖滾站區1', 36000, 12, 'available');
CALL append_mock_seats(301, '搖滾站區1', 36000, 2, 'reserved');
CALL append_mock_seats(301, '搖滾站區2', 36000, 12, 'available');
CALL append_mock_seats(301, '搖滾站區2', 36000, 2, 'reserved');
CALL append_mock_seats(301, '搖滾站區3', 36000, 12, 'available');
CALL append_mock_seats(301, '搖滾站區3', 36000, 2, 'sold');
CALL append_mock_seats(301, '一樓座席1', 18000, 56, 'available');
CALL append_mock_seats(301, '一樓座席1', 18000, 4, 'sold');
CALL append_mock_seats(301, '一樓座席2', 18000, 56, 'available');
CALL append_mock_seats(301, '一樓座席2', 18000, 4, 'sold');
CALL append_mock_seats(301, '二樓座席1', 9000, 122, 'available');
CALL append_mock_seats(301, '二樓座席1', 9000, 8, 'reserved');
CALL append_mock_seats(301, '二樓座席2', 9000, 123, 'available');
CALL append_mock_seats(301, '二樓座席2', 9000, 7, 'reserved');
CALL append_mock_seat_unit(302, '至尊包廂1', 100000, 'available');
CALL append_mock_seat_unit(302, '至尊包廂2', 100000, 'reserved');
CALL append_mock_seats(302, '搖滾站區1', 36000, 10, 'available');
CALL append_mock_seats(302, '搖滾站區1', 36000, 2, 'sold');
CALL append_mock_seats(302, '搖滾站區2', 36000, 10, 'available');
CALL append_mock_seats(302, '搖滾站區2', 36000, 2, 'sold');
CALL append_mock_seats(302, '搖滾站區3', 36000, 10, 'available');
CALL append_mock_seats(302, '搖滾站區3', 36000, 1, 'sold');
CALL append_mock_seats(302, '一樓座席1', 18000, 50, 'available');
CALL append_mock_seats(302, '一樓座席1', 18000, 5, 'reserved');
CALL append_mock_seats(302, '一樓座席2', 18000, 50, 'available');
CALL append_mock_seats(302, '一樓座席2', 18000, 5, 'reserved');
CALL append_mock_seats(302, '二樓座席1', 9000, 110, 'available');
CALL append_mock_seats(302, '二樓座席1', 9000, 10, 'sold');
CALL append_mock_seats(302, '二樓座席2', 9000, 110, 'available');
CALL append_mock_seats(302, '二樓座席2', 9000, 9, 'sold');

-- Mock order data for manager Order Management / Order Detail / Sales Report.
-- These rows depend on the users, promo codes, shows and seats created above.
INSERT INTO Orders
    (order_id, user_id, show_id, promo_id, total_price, status, payment_method, delivery_method, created_at)
VALUES
    (1, 2, 102, 1, 8500, 'paid', 'credit_card', 'ibon', DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (2, 2, 301, NULL, 72000, 'pending_payment', NULL, NULL, DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
    (3, 2, 302, 2, 71500, 'pending_payment', NULL, NULL, DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
    (4, 2, 301, NULL, 100000, 'cancelled', NULL, NULL, DATE_SUB(NOW(), INTERVAL 3 DAY));

INSERT INTO Ticket
    (order_id, seat_id, real_name, id_number)
VALUES
    (
        1,
        (SELECT seat_id FROM Seat WHERE show_id = 102 AND seat_number = '幸福搖滾區1_1號' LIMIT 1),
        '李瑋晨',
        'A130022039'
    ),
    (
        1,
        (SELECT seat_id FROM Seat WHERE show_id = 102 AND seat_number = '幸福孟區1_1號' LIMIT 1),
        '王小明',
        'B123456789'
    ),
    (
        2,
        (SELECT seat_id FROM Seat WHERE show_id = 301 AND seat_number = '搖滾站區1_13號' LIMIT 1),
        '李瑋晨',
        'A130022039'
    ),
    (
        2,
        (SELECT seat_id FROM Seat WHERE show_id = 301 AND seat_number = '搖滾站區1_14號' LIMIT 1),
        '陳小華',
        'C223456789'
    ),
    (
        3,
        (SELECT seat_id FROM Seat WHERE show_id = 302 AND seat_number = '搖滾站區1_11號' LIMIT 1),
        '李瑋晨',
        'A130022039'
    ),
    (
        3,
        (SELECT seat_id FROM Seat WHERE show_id = 302 AND seat_number = '搖滾站區1_12號' LIMIT 1),
        '林小美',
        'D323456789'
    ),
    (
        4,
        (SELECT seat_id FROM Seat WHERE show_id = 301 AND seat_number = '至尊包廂1' LIMIT 1),
        '取消測試',
        'E423456789'
    );

UPDATE Seat
SET status = 'sold'
WHERE seat_id IN (
    SELECT seat_id FROM Ticket WHERE order_id = 1
);

UPDATE Seat
SET status = 'reserved'
WHERE seat_id IN (
    SELECT seat_id FROM Ticket WHERE order_id IN (2, 3)
);

UPDATE Seat
SET status = 'available'
WHERE seat_id IN (
    SELECT seat_id FROM Ticket WHERE order_id = 4
);

DROP PROCEDURE IF EXISTS append_mock_seats;
DROP PROCEDURE IF EXISTS append_mock_seat_unit;
