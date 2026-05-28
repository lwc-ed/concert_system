USE concert_system;

-- Re-runnable mock data seed.
-- If teammates already imported an older inputdata.sql, run this file again to refresh
-- Concert / ShowDate / Seat data to match the current frontend prototype.
-- Existing Ticket and Orders rows are cleared because they reference old Seat rows.
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM Ticket;
DELETE FROM Orders;
DELETE FROM Seat;
DELETE FROM ShowDate;
DELETE FROM Concert;
SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE Ticket AUTO_INCREMENT = 1;
ALTER TABLE Orders AUTO_INCREMENT = 1;
ALTER TABLE Seat AUTO_INCREMENT = 1;
ALTER TABLE ShowDate AUTO_INCREMENT = 1;
ALTER TABLE Concert AUTO_INCREMENT = 1;

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
    'customer'
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

INSERT INTO Concert
    (concert_id, artist, title, venue, concert_address, image, sale_start, sale_end, description, notice)
VALUES
    (
        1,
        '史詩級跨界合作 <幸福崴孟演唱會 x æspa>',
        '2026 Taipei Arena Tour',
        '台北大巨蛋',
        '台北市信義區忠孝東路四段515號',
        'assets/images/concert-1.png',
        '2026-05-01 12:00:00',
        '2026-06-27 23:59:00',
        '跨界幸福舞台、沉浸式燈光與完整現場樂隊編制，打造幸福期末演唱會，有參加都會有幸福草莓蛋糕喔！',
        '本場次目前部分場次已售完，想要和幸福崴孟一起幸福æspa要盡快唷！'
    ),
    (
        2,
        '婉晴粉絲見面會',
        '全台巡迴中',
        '台北小巨蛋',
        '台北市松山區南京東路四段2號',
        'assets/images/concert-2.png',
        '2026-03-01 10:00:00',
        '2026-03-31 23:59:00',
        '近距離互動、拍照抽選與粉絲限定舞台內容，適合展示不同活動型態的訂票資訊。',
        '活動已結束，訂票按鈕會自動呈現不可購買狀態。'
    ),
    (
        3,
        '史上最屌演唱會',
        'Final Call',
        '百老匯',
        'New York, NY, United States',
        'assets/images/concert-3.png',
        '2027-01-10 12:00:00',
        '2027-06-04 23:59:00',
        '壓軸場次以大型舞台機關與海外場館規格呈現，頁面保留票區、剩餘票數與訂票流程入口。',
        '每位會員每筆訂單最多可購買 2 張票，實際付款與座位鎖定可於後續串接訂單資料表。'
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

DROP PROCEDURE IF EXISTS append_mock_seats;
DROP PROCEDURE IF EXISTS append_mock_seat_unit;
