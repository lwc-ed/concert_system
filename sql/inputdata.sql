INSERT INTO Concert
    (concert_id, artist, title, venue, address, image, sale_start, sale_end, description, notice)
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
        'Final Call ',
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

CREATE PROCEDURE append_mock_seats(
    IN p_show_id INT,
    IN p_zone_name VARCHAR(20),
    IN p_price INT,
    IN p_quantity INT,
    IN p_status VARCHAR(20)
)
BEGIN
    DECLARE seat_index INT DEFAULT 1;

    WHILE seat_index <= p_quantity DO
        INSERT INTO Seat (show_id, seat_number, price, status)
        VALUES (
            p_show_id,
            CONCAT(p_zone_name, '_', seat_index, '號'),
            p_price,
            p_status
        );

        SET seat_index = seat_index + 1;
    END WHILE;
END $$

DELIMITER ;

CALL append_mock_seats(101, '幸福搖滾區', 5800, 12, 'sold');
CALL append_mock_seats(101, '幸福崴區', 4200, 80, 'sold');
CALL append_mock_seats(101, '幸福孟區', 2800, 120, 'sold');
CALL append_mock_seats(101, '幸福崴孟區', 1800, 160, 'sold');
CALL append_mock_seats(102, '幸福搖滾區', 5800, 12, 'sold');
CALL append_mock_seats(102, '幸福崴區', 4200, 80, 'sold');
CALL append_mock_seats(102, '幸福孟區', 2800, 120, 'available');
CALL append_mock_seats(102, '幸福崴孟區', 1800, 160, 'available');

CALL append_mock_seats(201, '特典區', 500, 30, 'sold');
CALL append_mock_seats(201, '一般區', 300, 120, 'sold');
CALL append_mock_seats(201, '學生區', 1, 50, 'sold');
CALL append_mock_seats(202, '特典區', 500, 30, 'sold');
CALL append_mock_seats(202, '一般區', 300, 120, 'sold');
CALL append_mock_seats(202, '學生區', 1, 50, 'sold');
CALL append_mock_seats(203, '特典區', 500, 30, 'sold');
CALL append_mock_seats(203, '一般區', 300, 120, 'sold');
CALL append_mock_seats(203, '學生區', 1, 50, 'sold');

CALL append_mock_seats(301, '至尊包廂', 100000, 8, 'available');
CALL append_mock_seats(301, '搖滾站區', 36000, 42, 'available');
CALL append_mock_seats(301, '一樓座席', 18000, 120, 'available');
CALL append_mock_seats(301, '二樓座席', 9000, 260, 'available');
CALL append_mock_seats(302, '至尊包廂', 100000, 6, 'available');
CALL append_mock_seats(302, '搖滾站區', 36000, 35, 'available');
CALL append_mock_seats(302, '一樓座席', 18000, 110, 'available');
CALL append_mock_seats(302, '二樓座席', 9000, 239, 'available');

DROP PROCEDURE IF EXISTS append_mock_seats;
