<?php

require_once __DIR__ . '/db_config.php';

/**
 * Normalized data source.
 *
 * The public helper functions below are the page-facing data API. They first try
 * to read from MySQL tables and fall back to mock arrays when local DB settings
 * are not ready yet.
 *
 * The data mirrors these database tables:
 * - Organizer(organizer_id, organizer_name, contact_person, contact_email, contact_phone, organizer_address, note)
 * - Concert(concert_id, organizer_id, artist, title, venue, concert_address, image, sale_start, sale_end, description, notice)
 * - ShowDate(show_id, concert_id, show_datetime, status)
 * - Seat(seat_id, show_id, seat_number, price, status)
 */
function getDatabaseConnection() {
    global $pdo;

    return $pdo instanceof PDO ? $pdo : null;
}

function fetchDatabaseRows($sql, $params = []) {
    $connection = getDatabaseConnection();

    if ($connection === null) {
        return null;
    }

    try {
        $statement = $connection->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    } catch (PDOException $exception) {
        return null;
    }
}

function getConcertTable() {
    $rows = fetchDatabaseRows(
        'SELECT
            c.concert_id,
            c.organizer_id,
            c.artist,
            c.title,
            c.venue,
            c.concert_address AS address,
            c.image,
            c.sale_start,
            c.sale_end,
            c.description,
            c.notice,
            o.organizer_name,
            o.contact_person AS organizer_contact_person,
            o.contact_email AS organizer_contact_email,
            o.contact_phone AS organizer_contact_phone,
            o.organizer_address,
            o.note AS organizer_note
         FROM Concert c
         LEFT JOIN Organizer o ON o.organizer_id = c.organizer_id
         ORDER BY c.concert_id'
    );

    if ($rows !== null) {
        return array_map('normalizeConcertRow', $rows);
    }

    return [
        [
            'concert_id' => 1,
            'organizer_id' => 1,
            'organizer_name' => '幸福娛樂股份有限公司',
            'organizer_contact_person' => '王幸福',
            'organizer_contact_email' => 'happy@example.com',
            'organizer_contact_phone' => '02-2345-6789',
            'organizer_address' => '台北市信義區幸福路 1 號',
            'artist' => '史詩級跨界合作 <幸福崴孟演唱會 x æspa>',
            'title' => '2026 Taipei Arena Tour',
            'venue' => '台北大巨蛋',
            'address' => '台北市信義區忠孝東路四段515號',
            'image' => 'assets/images/concert-1.png',
            'sale_start' => '2026.05.01 12:00',
            'sale_end' => '2026.06.27 23:59',
            'description' => '跨界幸福舞台、沉浸式燈光與完整現場樂隊編制，打造幸福期末演唱會，有參加都會有幸福草莓蛋糕喔！',
            'notice' => '本場次目前部分場次已售完，想要和幸福崴孟一起幸福æspa要盡快唷！',
        ],
        [
            'concert_id' => 2,
            'organizer_id' => 2,
            'organizer_name' => '晴天活動企劃',
            'organizer_contact_person' => '陳婉晴',
            'organizer_contact_email' => 'sunny@example.com',
            'organizer_contact_phone' => '02-8765-4321',
            'organizer_address' => '台北市松山區南京東路四段 2 號',
            'artist' => '婉晴粉絲見面會',
            'title' => '全台巡迴中',
            'venue' => '台北小巨蛋',
            'address' => '台北市松山區南京東路四段2號',
            'image' => 'assets/images/concert-2.png',
            'sale_start' => '2026.03.01 10:00',
            'sale_end' => '2026.03.31 23:59',
            'description' => '近距離互動、拍照抽選與粉絲限定舞台內容，適合展示不同活動型態的訂票資訊。',
            'notice' => '活動已結束，訂票按鈕會自動呈現不可購買狀態。',
        ],
        [
            'concert_id' => 3,
            'organizer_id' => 3,
            'organizer_name' => 'Final Call Global',
            'organizer_contact_person' => 'Alex Chen',
            'organizer_contact_email' => 'finalcall@example.com',
            'organizer_contact_phone' => '+1-212-555-0199',
            'organizer_address' => 'New York, NY, United States',
            'artist' => '史上最屌演唱會',
            'title' => 'Final Call',
            'venue' => '百老匯',
            'address' => 'New York, NY, United States',
            'image' => 'assets/images/concert-3.png',
            'sale_start' => '2027.01.10 12:00',
            'sale_end' => '2027.06.04 23:59',
            'description' => '壓軸場次以大型舞台機關與海外場館規格呈現，頁面保留票區、剩餘票數與訂票流程入口。',
            'notice' => '每位會員每筆訂單最多可購買 2 張票，實際付款與座位鎖定可於後續串接訂單資料表。',
        ],
    ];
}

function getShowDateTable() {
    $rows = fetchDatabaseRows(
        'SELECT show_id, concert_id, show_datetime, status
         FROM ShowDate
         ORDER BY show_datetime, show_id'
    );

    if ($rows !== null) {
        return array_map('normalizeShowDateRow', $rows);
    }

    return [
        ['show_id' => 101, 'concert_id' => 1, 'show_datetime' => '2026-06-28 19:30:00', 'status' => 'sold_out'],
        ['show_id' => 102, 'concert_id' => 1, 'show_datetime' => '2026-06-29 19:30:00', 'status' => 'available'],
        ['show_id' => 201, 'concert_id' => 2, 'show_datetime' => '2026-04-01 20:00:00', 'status' => 'ended'],
        ['show_id' => 202, 'concert_id' => 2, 'show_datetime' => '2026-04-08 19:30:00', 'status' => 'ended'],
        ['show_id' => 203, 'concert_id' => 2, 'show_datetime' => '2026-04-15 19:30:00', 'status' => 'ended'],
        ['show_id' => 301, 'concert_id' => 3, 'show_datetime' => '2027-06-05 19:00:00', 'status' => 'available'],
        ['show_id' => 302, 'concert_id' => 3, 'show_datetime' => '2027-06-06 18:30:00', 'status' => 'available'],
    ];
}

function getSeatTable() {
    $rows = fetchDatabaseRows(
        'SELECT seat_id, show_id, seat_number, price, status
         FROM Seat
         ORDER BY show_id, seat_id'
    );

    if ($rows !== null) {
        return array_map('normalizeSeatRow', $rows);
    }

    $seats = [];
    $nextSeatId = 1;

    foreach ([101 => 'sold', 102 => 'sold'] as $showId => $status) {
        appendSeats($seats, $nextSeatId, $showId, '幸福搖滾區1', 5800, 6, $status);
        appendSeats($seats, $nextSeatId, $showId, '幸福搖滾區2', 5800, 6, $status);
        appendSeats($seats, $nextSeatId, $showId, '幸福崴區1', 4200, 20, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '幸福崴區2', 4200, 20, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '幸福崴區3', 4200, 20, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '幸福崴區4', 4200, 20, 'sold');
    }

    appendSeats($seats, $nextSeatId, 101, '幸福孟區1', 2800, 30, 'sold');
    appendSeats($seats, $nextSeatId, 101, '幸福孟區2', 2800, 30, 'sold');
    appendSeats($seats, $nextSeatId, 101, '幸福孟區3', 2800, 30, 'sold');
    appendSeats($seats, $nextSeatId, 101, '幸福孟區4', 2800, 30, 'sold');
    appendSeats($seats, $nextSeatId, 101, '幸福崴孟區1', 1800, 40, 'sold');
    appendSeats($seats, $nextSeatId, 101, '幸福崴孟區2', 1800, 40, 'sold');
    appendSeats($seats, $nextSeatId, 101, '幸福崴孟區3', 1800, 40, 'sold');
    appendSeats($seats, $nextSeatId, 101, '幸福崴孟區4', 1800, 40, 'sold');
    appendSeats($seats, $nextSeatId, 102, '幸福孟區1', 2800, 30, 'available');
    appendSeats($seats, $nextSeatId, 102, '幸福孟區2', 2800, 30, 'available');
    appendSeats($seats, $nextSeatId, 102, '幸福孟區3', 2800, 30, 'available');
    appendSeats($seats, $nextSeatId, 102, '幸福孟區4', 2800, 30, 'available');
    appendSeats($seats, $nextSeatId, 102, '幸福崴孟區1', 1800, 40, 'available');
    appendSeats($seats, $nextSeatId, 102, '幸福崴孟區2', 1800, 40, 'available');
    appendSeats($seats, $nextSeatId, 102, '幸福崴孟區3', 1800, 40, 'available');
    appendSeats($seats, $nextSeatId, 102, '幸福崴孟區4', 1800, 40, 'available');

    foreach ([201, 202, 203] as $showId) {
        appendSeats($seats, $nextSeatId, $showId, '特典區1', 500, 15, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '特典區2', 500, 15, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '一般區1', 300, 12, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '一般區2', 300, 12, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '一般區3', 300, 12, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '一般區4', 300, 12, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '一般區5', 300, 12, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '一般區6', 300, 12, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '一般區7', 300, 12, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '一般區8', 300, 12, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '一般區9', 300, 12, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '一般區10', 300, 12, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '學生區1', 1, 9, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '學生區2', 1, 9, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '學生區3', 1, 8, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '學生區4', 1, 8, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '學生區5', 1, 8, 'sold');
        appendSeats($seats, $nextSeatId, $showId, '學生區6', 1, 8, 'sold');
    }

    appendSeatUnit($seats, $nextSeatId, 301, '至尊包廂1', 100000, 'available');
    appendSeatUnit($seats, $nextSeatId, 301, '至尊包廂2', 100000, 'available');
    appendSeats($seats, $nextSeatId, 301, '搖滾站區1', 36000, 12, 'available');
    appendSeats($seats, $nextSeatId, 301, '搖滾站區1', 36000, 2, 'reserved');
    appendSeats($seats, $nextSeatId, 301, '搖滾站區2', 36000, 12, 'available');
    appendSeats($seats, $nextSeatId, 301, '搖滾站區2', 36000, 2, 'reserved');
    appendSeats($seats, $nextSeatId, 301, '搖滾站區3', 36000, 12, 'available');
    appendSeats($seats, $nextSeatId, 301, '搖滾站區3', 36000, 2, 'sold');
    appendSeats($seats, $nextSeatId, 301, '一樓座席1', 18000, 56, 'available');
    appendSeats($seats, $nextSeatId, 301, '一樓座席1', 18000, 4, 'sold');
    appendSeats($seats, $nextSeatId, 301, '一樓座席2', 18000, 56, 'available');
    appendSeats($seats, $nextSeatId, 301, '一樓座席2', 18000, 4, 'sold');
    appendSeats($seats, $nextSeatId, 301, '二樓座席1', 9000, 122, 'available');
    appendSeats($seats, $nextSeatId, 301, '二樓座席1', 9000, 8, 'reserved');
    appendSeats($seats, $nextSeatId, 301, '二樓座席2', 9000, 123, 'available');
    appendSeats($seats, $nextSeatId, 301, '二樓座席2', 9000, 7, 'reserved');
    appendSeatUnit($seats, $nextSeatId, 302, '至尊包廂1', 100000, 'available');
    appendSeatUnit($seats, $nextSeatId, 302, '至尊包廂2', 100000, 'reserved');
    appendSeats($seats, $nextSeatId, 302, '搖滾站區1', 36000, 10, 'available');
    appendSeats($seats, $nextSeatId, 302, '搖滾站區1', 36000, 2, 'sold');
    appendSeats($seats, $nextSeatId, 302, '搖滾站區2', 36000, 10, 'available');
    appendSeats($seats, $nextSeatId, 302, '搖滾站區2', 36000, 2, 'sold');
    appendSeats($seats, $nextSeatId, 302, '搖滾站區3', 36000, 10, 'available');
    appendSeats($seats, $nextSeatId, 302, '搖滾站區3', 36000, 1, 'sold');
    appendSeats($seats, $nextSeatId, 302, '一樓座席1', 18000, 50, 'available');
    appendSeats($seats, $nextSeatId, 302, '一樓座席1', 18000, 5, 'reserved');
    appendSeats($seats, $nextSeatId, 302, '一樓座席2', 18000, 50, 'available');
    appendSeats($seats, $nextSeatId, 302, '一樓座席2', 18000, 5, 'reserved');
    appendSeats($seats, $nextSeatId, 302, '二樓座席1', 9000, 110, 'available');
    appendSeats($seats, $nextSeatId, 302, '二樓座席1', 9000, 10, 'sold');
    appendSeats($seats, $nextSeatId, 302, '二樓座席2', 9000, 110, 'available');
    appendSeats($seats, $nextSeatId, 302, '二樓座席2', 9000, 9, 'sold');

    return $seats;
}

function normalizeConcertRow($row) {
    $row['concert_id'] = (int) $row['concert_id'];
    $row['organizer_id'] = $row['organizer_id'] !== null ? (int) $row['organizer_id'] : null;
    $row['sale_start'] = formatDatabaseDateTimeForDisplay($row['sale_start']);
    $row['sale_end'] = formatDatabaseDateTimeForDisplay($row['sale_end']);

    return $row;
}

function normalizeShowDateRow($row) {
    $row['show_id'] = (int) $row['show_id'];
    $row['concert_id'] = (int) $row['concert_id'];

    return $row;
}

function normalizeSeatRow($row) {
    $row['seat_id'] = (int) $row['seat_id'];
    $row['show_id'] = (int) $row['show_id'];
    $row['price'] = (int) $row['price'];

    return $row;
}

function formatDatabaseDateTimeForDisplay($value) {
    if ($value === null || $value === '') {
        return '';
    }

    try {
        $dateTime = new DateTime($value);
        return $dateTime->format('Y.m.d H:i');
    } catch (Exception $exception) {
        return $value;
    }
}

function appendSeats(&$seats, &$nextSeatId, $showId, $zone, $price, $quantity, $status) {
    $existingZoneSeatCount = 0;

    foreach ($seats as $seat) {
        if ((int) $seat['show_id'] === (int) $showId && seatZoneFromSeatNumber($seat['seat_number']) === $zone) {
            $existingZoneSeatCount++;
        }
    }

    for ($seatIndex = 1; $seatIndex <= $quantity; $seatIndex++) {
        $seats[] = [
            'seat_id' => $nextSeatId,
            'show_id' => $showId,
            'seat_number' => $zone . '_' . ($existingZoneSeatCount + $seatIndex) . '號',
            'price' => $price,
            'status' => $status,
        ];
        $nextSeatId++;
    }
}

function appendSeatUnit(&$seats, &$nextSeatId, $showId, $seatNumber, $price, $status) {
    $seats[] = [
        'seat_id' => $nextSeatId,
        'show_id' => $showId,
        'seat_number' => $seatNumber,
        'price' => $price,
        'status' => $status,
    ];
    $nextSeatId++;
}

function getConcerts() {
    $concerts = [];

    foreach (getConcertTable() as $concert) {
        $concert['show_dates'] = getShowDatesByConcertId($concert['concert_id']);
        $concert['status'] = concertStatusText($concert);
        $concert['price'] = concertPriceRangeText($concert['concert_id']);
        $concerts[] = $concert;
    }

    usort($concerts, function ($firstConcert, $secondConcert) {
        return concertFirstShowTimestamp($firstConcert) <=> concertFirstShowTimestamp($secondConcert);
    });

    return $concerts;
}

function findConcertById($concertId) {
    foreach (getConcerts() as $concert) {
        if ((int) $concert['concert_id'] === (int) $concertId) {
            return $concert;
        }
    }

    return null;
}

function getShowDatesByConcertId($concertId) {
    $showDates = [];

    foreach (getShowDateTable() as $showDate) {
        if ((int) $showDate['concert_id'] === (int) $concertId) {
            $showDates[] = $showDate;
        }
    }

    usort($showDates, function ($firstShow, $secondShow) {
        return strtotime($firstShow['show_datetime']) <=> strtotime($secondShow['show_datetime']);
    });

    return $showDates;
}

function getSeatsByShowId($showId) {
    $seats = [];

    foreach (getSeatTable() as $seat) {
        if ((int) $seat['show_id'] === (int) $showId) {
            $seats[] = $seat;
        }
    }

    return $seats;
}

function getSeatMapLayout($concertId) {
    $layouts = [
        1 => [
            ['label' => '幸福崴區1', 'zone' => '幸福崴區1', 'row' => 1, 'col' => 2, 'colspan' => 1],
            ['label' => '幸福搖滾區1', 'zone' => '幸福搖滾區1', 'row' => 1, 'col' => 3, 'colspan' => 2],
            ['label' => '幸福孟區1', 'zone' => '幸福孟區1', 'row' => 1, 'col' => 5, 'colspan' => 1],
            ['label' => '幸福崴區2', 'zone' => '幸福崴區2', 'row' => 2, 'col' => 2, 'colspan' => 1],
            ['label' => '幸福搖滾區2', 'zone' => '幸福搖滾區2', 'row' => 2, 'col' => 3, 'colspan' => 2],
            ['label' => '幸福孟區2', 'zone' => '幸福孟區2', 'row' => 2, 'col' => 5, 'colspan' => 1],
            ['label' => '幸福崴區3', 'zone' => '幸福崴區3', 'row' => 3, 'col' => 2, 'colspan' => 1],
            ['label' => '幸福孟區3', 'zone' => '幸福孟區3', 'row' => 3, 'col' => 5, 'colspan' => 1],
            ['label' => '幸福崴區4', 'zone' => '幸福崴區4', 'row' => 4, 'col' => 2, 'colspan' => 1],
            ['label' => '幸福孟區4', 'zone' => '幸福孟區4', 'row' => 4, 'col' => 5, 'colspan' => 1],
            ['label' => '幸福崴孟區1', 'zone' => '幸福崴孟區1', 'row' => 5, 'col' => 1, 'colspan' => 2],
            ['label' => '幸福崴孟區2', 'zone' => '幸福崴孟區2', 'row' => 5, 'col' => 3, 'colspan' => 1],
            ['label' => '幸福崴孟區3', 'zone' => '幸福崴孟區3', 'row' => 5, 'col' => 4, 'colspan' => 1],
            ['label' => '幸福崴孟區4', 'zone' => '幸福崴孟區4', 'row' => 5, 'col' => 5, 'colspan' => 2],
        ],
        2 => [
            ['label' => '一般區1', 'zone' => '一般區1', 'row' => 1, 'col' => 2, 'colspan' => 1],
            ['label' => '特典區1', 'zone' => '特典區1', 'row' => 1, 'col' => 3, 'colspan' => 2],
            ['label' => '一般區2', 'zone' => '一般區2', 'row' => 1, 'col' => 5, 'colspan' => 1],
            ['label' => '一般區3', 'zone' => '一般區3', 'row' => 2, 'col' => 2, 'colspan' => 1],
            ['label' => '特典區2', 'zone' => '特典區2', 'row' => 2, 'col' => 3, 'colspan' => 2],
            ['label' => '一般區4', 'zone' => '一般區4', 'row' => 2, 'col' => 5, 'colspan' => 1],
            ['label' => '一般區5', 'zone' => '一般區5', 'row' => 3, 'col' => 2, 'colspan' => 1],
            ['label' => '一般區6', 'zone' => '一般區6', 'row' => 3, 'col' => 3, 'colspan' => 2],
            ['label' => '一般區7', 'zone' => '一般區7', 'row' => 3, 'col' => 5, 'colspan' => 1],
            ['label' => '一般區8', 'zone' => '一般區8', 'row' => 4, 'col' => 2, 'colspan' => 1],
            ['label' => '一般區9', 'zone' => '一般區9', 'row' => 4, 'col' => 3, 'colspan' => 2],
            ['label' => '一般區10', 'zone' => '一般區10', 'row' => 4, 'col' => 5, 'colspan' => 1],
            ['label' => '學生區1', 'zone' => '學生區1', 'row' => 5, 'col' => 1, 'colspan' => 1],
            ['label' => '學生區2', 'zone' => '學生區2', 'row' => 5, 'col' => 2, 'colspan' => 1],
            ['label' => '學生區3', 'zone' => '學生區3', 'row' => 5, 'col' => 3, 'colspan' => 1],
            ['label' => '學生區4', 'zone' => '學生區4', 'row' => 5, 'col' => 4, 'colspan' => 1],
            ['label' => '學生區5', 'zone' => '學生區5', 'row' => 5, 'col' => 5, 'colspan' => 1],
            ['label' => '學生區6', 'zone' => '學生區6', 'row' => 5, 'col' => 6, 'colspan' => 1],
        ],
        3 => [
            ['label' => '至尊包廂1', 'zone' => '至尊包廂1', 'row' => 1, 'col' => 1, 'colspan' => 2],
            ['label' => '至尊包廂2', 'zone' => '至尊包廂2', 'row' => 1, 'col' => 5, 'colspan' => 2],
            ['label' => '搖滾站區1', 'zone' => '搖滾站區1', 'row' => 2, 'col' => 2, 'colspan' => 1],
            ['label' => '搖滾站區2', 'zone' => '搖滾站區2', 'row' => 2, 'col' => 3, 'colspan' => 2],
            ['label' => '搖滾站區3', 'zone' => '搖滾站區3', 'row' => 2, 'col' => 5, 'colspan' => 1],
            ['label' => '一樓座席1', 'zone' => '一樓座席1', 'row' => 3, 'col' => 2, 'colspan' => 1],
            ['label' => '一樓座席1', 'zone' => '一樓座席1', 'row' => 3, 'col' => 5, 'colspan' => 1],
            ['label' => '一樓座席2', 'zone' => '一樓座席2', 'row' => 4, 'col' => 3, 'colspan' => 1],
            ['label' => '一樓座席2', 'zone' => '一樓座席2', 'row' => 4, 'col' => 4, 'colspan' => 1],
            ['label' => '二樓座席1', 'zone' => '二樓座席1', 'row' => 5, 'col' => 2, 'colspan' => 1],
            ['label' => '二樓座席1', 'zone' => '二樓座席1', 'row' => 5, 'col' => 5, 'colspan' => 1],
            ['label' => '二樓座席2', 'zone' => '二樓座席2', 'row' => 6, 'col' => 3, 'colspan' => 1],
            ['label' => '二樓座席2', 'zone' => '二樓座席2', 'row' => 6, 'col' => 4, 'colspan' => 1],
        ],
    ];

    return $layouts[(int) $concertId] ?? [];
}

function getSeatZoneSummariesByShowId($showId) {
    $summaries = [];

    foreach (getSeatsByShowId($showId) as $seat) {
        $zone = seatZoneFromSeatNumber($seat['seat_number']);
        $key = $zone . '|' . $seat['price'];

        if (!isset($summaries[$key])) {
            $summaries[$key] = [
                'zone' => $zone,
                'price' => (int) $seat['price'],
                'remaining' => 0,
                'unit' => isPrivateBoxZone($zone) ? '間' : '張',
            ];
        }

        if ($seat['status'] === 'available') {
            $summaries[$key]['remaining']++;
        }
    }

    return array_values($summaries);
}

function seatZoneFromSeatNumber($seatNumber) {
    $parts = explode('_', $seatNumber, 2);

    return $parts[0];
}

function isPrivateBoxZone($zone) {
    return strpos($zone, '至尊包廂') === 0;
}

function countAvailableSeatsByConcertId($concertId) {
    $availableCount = 0;

    foreach (getShowDatesByConcertId($concertId) as $showDate) {
        foreach (getSeatsByShowId($showDate['show_id']) as $seat) {
            if ($seat['status'] === 'available') {
                $availableCount++;
            }
        }
    }

    return $availableCount;
}

function concertPriceRangeText($concertId) {
    $prices = [];

    foreach (getShowDatesByConcertId($concertId) as $showDate) {
        foreach (getSeatsByShowId($showDate['show_id']) as $seat) {
            $prices[] = (int) $seat['price'];
        }
    }

    if (count($prices) === 0) {
        return '尚未公布';
    }

    $lowestPrice = min($prices);
    $highestPrice = max($prices);

    if ($lowestPrice === $highestPrice) {
        return formatTicketPrice($lowestPrice);
    }

    return formatTicketPrice($lowestPrice) . ' - ' . formatTicketPrice($highestPrice);
}

function concertStatusText($concert) {
    $statuses = array_column($concert['show_dates'], 'status');

    if (in_array('available', $statuses, true)) {
        return '開放購票';
    }

    if (count($statuses) > 0 && count(array_unique($statuses)) === 1 && $statuses[0] === 'sold_out') {
        return '已售完';
    }

    if (count($statuses) > 0 && count(array_unique($statuses)) === 1 && $statuses[0] === 'ended') {
        return '已結束';
    }

    return '暫停販售';
}

function concertFirstShowTimestamp($concert) {
    if (count($concert['show_dates']) === 0) {
        return PHP_INT_MAX;
    }

    return strtotime($concert['show_dates'][0]['show_datetime']);
}

function formatTicketPrice($price) {
    return 'NT$' . number_format((int) $price);
}

function showDateDateText($showDate) {
    $dateTime = new DateTime($showDate['show_datetime']);

    return $dateTime->format('Y.m.d D');
}

function showDateTimeText($showDate) {
    $dateTime = new DateTime($showDate['show_datetime']);

    return $dateTime->format('H:i');
}

function showDateStatusText($status) {
    $statusMap = [
        'available' => '可購買',
        'sold_out' => '已售完',
        'ended' => '已結束',
    ];

    return $statusMap[$status] ?? $status;
}

function concertScheduleText($concert) {
    $showDates = $concert['show_dates'] ?? [];
    $showDateCount = count($showDates);

    if ($showDateCount > 1) {
        return '共 ' . $showDateCount . ' 場 · 首場 ' . showDateDateText($showDates[0]) . ' · ' . showDateTimeText($showDates[0]);
    }

    if ($showDateCount === 1) {
        return showDateDateText($showDates[0]) . ' · ' . showDateTimeText($showDates[0]);
    }

    return '尚未公布';
}
