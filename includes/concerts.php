<?php

/**
 * Temporary normalized data source.
 *
 * The mock data below mirrors the future database tables:
 * - Concert(concert_id, artist, title, venue, address, image, sale_start, sale_end, description, notice)
 * - ShowDate(show_id, concert_id, show_datetime, status)
 * - Seat(seat_id, show_id, seat_number, price, status)
 *
 * When MySQL is ready, replace the table functions with SELECT queries and keep
 * the public helper functions. The PHP pages should not need structural changes.
 */
function getConcertTable() {
    return [
        [
            'concert_id' => 1,
            'artist' => '史詩級跨界合作 <幸福崴孟演唱會 x æspa>',
            'title' => '2026 Taipei Arena Tour',
            'venue' => '台北大巨蛋',
            'address' => '台北市信義區忠孝東路四段515號',
            'image' => 'assets/images/concert-1.png',
            'sale_start' => '2026.05.01 12:00',
            'sale_end' => '2026.06.27 23:59',
            'description' => '跨界幸福舞台、沉浸式燈光與完整現場樂隊編制，打造幸福期末演唱會，有參加都會有幸福草莓蛋糕喔！',
            'notice' => '本場次目前已售完，可先保留詳情頁與座位資料作為資料庫展示使用。',
        ],
        [
            'concert_id' => 2,
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
            'artist' => '史上最屌演唱會',
            'title' => 'Final Call ',
            'venue' => '百老匯',
            'address' => 'New York, NY, United States',
            'image' => 'assets/images/concert-3.png',
            'sale_start' => '2027.01.10 12:00',
            'sale_end' => '2027.06.04 23:59',
            'description' => '壓軸場次以大型舞台機關與海外場館規格呈現，頁面保留票區、剩餘票數與訂票流程入口。',
            'notice' => '每位會員每筆訂單最多可購買 4 張票，實際付款與座位鎖定可於後續串接訂單資料表。',
        ],
    ];
}

function getShowDateTable() {
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
    $seats = [];
    $nextSeatId = 1;

    appendSeats($seats, $nextSeatId, 101, '幸福搖滾區', 5800, 12, 'sold');
    appendSeats($seats, $nextSeatId, 101, '幸福崴區', 4200, 80, 'sold');
    appendSeats($seats, $nextSeatId, 101, '幸福孟區', 2800, 120, 'sold');
    appendSeats($seats, $nextSeatId, 101, '幸福崴孟區', 1800, 160, 'sold');
    appendSeats($seats, $nextSeatId, 102, '幸福搖滾區', 5800, 12, 'sold');
    appendSeats($seats, $nextSeatId, 102, '幸福崴區', 4200, 80, 'sold');
    appendSeats($seats, $nextSeatId, 102, '幸福孟區', 2800, 120, 'available');
    appendSeats($seats, $nextSeatId, 102, '幸福崴孟區', 1800, 160, 'available');

    appendSeats($seats, $nextSeatId, 201, '特典區', 500, 30, 'sold');
    appendSeats($seats, $nextSeatId, 201, '一般區', 300, 120, 'sold');
    appendSeats($seats, $nextSeatId, 201, '學生區', 1, 50, 'sold');
    appendSeats($seats, $nextSeatId, 202, '特典區', 500, 30, 'sold');
    appendSeats($seats, $nextSeatId, 202, '一般區', 300, 120, 'sold');
    appendSeats($seats, $nextSeatId, 202, '學生區', 1, 50, 'sold');
    appendSeats($seats, $nextSeatId, 203, '特典區', 500, 30, 'sold');
    appendSeats($seats, $nextSeatId, 203, '一般區', 300, 120, 'sold');
    appendSeats($seats, $nextSeatId, 203, '學生區', 1, 50, 'sold');

    appendSeats($seats, $nextSeatId, 301, '至尊包廂', 100000, 8, 'available');
    appendSeats($seats, $nextSeatId, 301, '搖滾站區', 36000, 42, 'available');
    appendSeats($seats, $nextSeatId, 301, '一樓座席', 18000, 120, 'available');
    appendSeats($seats, $nextSeatId, 301, '二樓座席', 9000, 260, 'available');
    appendSeats($seats, $nextSeatId, 302, '至尊包廂', 100000, 6, 'available');
    appendSeats($seats, $nextSeatId, 302, '搖滾站區', 36000, 35, 'available');
    appendSeats($seats, $nextSeatId, 302, '一樓座席', 18000, 110, 'available');
    appendSeats($seats, $nextSeatId, 302, '二樓座席', 9000, 239, 'available');

    return $seats;
}

function appendSeats(&$seats, &$nextSeatId, $showId, $zone, $price, $quantity, $status) {
    for ($seatIndex = 1; $seatIndex <= $quantity; $seatIndex++) {
        $seats[] = [
            'seat_id' => $nextSeatId,
            'show_id' => $showId,
            'seat_number' => $zone . '_' . $seatIndex . '號',
            'price' => $price,
            'status' => $status,
        ];
        $nextSeatId++;
    }
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
