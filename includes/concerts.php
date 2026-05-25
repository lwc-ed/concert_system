<?php

/**
 * Temporary data source for the concert pages.
 *
 * When the database is ready, keep the returned array keys the same and replace
 * the body of getConcerts() / findConcertById() with SELECT queries.
 */
function getConcerts() {
    $concerts = [
        [
            'id' => 1,
            'artist' => '史詩級跨界合作 <幸福崴孟演唱會 x æspa>',
            'title' => '2026 Taipei Arena Tour',
            'date' => '2026.06.28 Sun',
            'time' => '19:30',
            'venue' => '台北大巨蛋',
            'address' => '台北市信義區忠孝東路四段515號',
            'price' => 'NT$1,800 - NT$5,800',
            'status' => '已售完',
            'image' => 'assets/images/concert-1.png',
            'sale_start' => '2026.05.01 12:00',
            'sale_end' => '2026.06.27 23:59',
            'description' => '跨界舞台、沉浸式燈光與完整現場樂隊編制，打造期末展示用的高規格演唱會頁面範例。',
            'notice' => '本場次目前已售完，可先保留詳情頁與座位資料作為資料庫展示使用。',
            'seat_zones' => [
                ['zone' => 'VIP 搖滾區', 'price' => 5800, 'remaining' => 0],
                ['zone' => 'A 區', 'price' => 4200, 'remaining' => 0],
                ['zone' => 'B 區', 'price' => 2800, 'remaining' => 0],
                ['zone' => 'C 區', 'price' => 1800, 'remaining' => 0],
            ],
        ],
        [
            'id' => 2,
            'artist' => '婉晴粉絲見面會',
            'title' => '全台巡迴中',
            'date' => '2026.04.01 Sun',
            'time' => '20:00',
            'venue' => '台北小巨蛋',
            'address' => '台北市松山區南京東路四段2號',
            'price' => 'NT$1 - NT$500',
            'status' => '已結束',
            'image' => 'assets/images/concert-2.png',
            'sale_start' => '2026.03.01 10:00',
            'sale_end' => '2026.03.31 23:59',
            'description' => '近距離互動、拍照抽選與粉絲限定舞台內容，適合展示不同活動型態的訂票資訊。',
            'notice' => '活動已結束，訂票按鈕會自動呈現不可購買狀態。',
            'seat_zones' => [
                ['zone' => '特典區', 'price' => 500, 'remaining' => 0],
                ['zone' => '一般區', 'price' => 300, 'remaining' => 0],
                ['zone' => '學生區', 'price' => 1, 'remaining' => 0],
            ],
        ],
        [
            'id' => 3,
            'artist' => '史上最屌演唱會',
            'title' => 'Final Call ',
            'date' => '2027.06.05 Sat',
            'time' => '19:00',
            'venue' => '百老匯',
            'address' => 'New York, NY, United States',
            'price' => 'NT$9,000 - NT$100,000',
            'status' => '開放購票',
            'image' => 'assets/images/concert-3.png',
            'sale_start' => '2027.01.10 12:00',
            'sale_end' => '2027.06.04 23:59',
            'description' => '壓軸場次以大型舞台機關與海外場館規格呈現，頁面保留票區、剩餘票數與訂票流程入口。',
            'notice' => '每位會員每筆訂單最多可購買 4 張票，實際付款與座位鎖定可於後續串接訂單資料表。',
            'seat_zones' => [
                ['zone' => '至尊包廂', 'price' => 100000, 'remaining' => 8],
                ['zone' => '搖滾站區', 'price' => 36000, 'remaining' => 42],
                ['zone' => '一樓座席', 'price' => 18000, 'remaining' => 120],
                ['zone' => '二樓座席', 'price' => 9000, 'remaining' => 260],
            ],
        ],
    ];

    usort($concerts, function ($firstConcert, $secondConcert) {
        return concertDateValue($firstConcert['date']) <=> concertDateValue($secondConcert['date']);
    });

    return $concerts;
}

function findConcertById($concertId) {
    foreach (getConcerts() as $concert) {
        if ((int) $concert['id'] === (int) $concertId) {
            return $concert;
        }
    }

    return null;
}

function concertDateValue($dateText) {
    if (preg_match('/^(\d{4})\.(\d{2})\.(\d{2})/', $dateText, $matches)) {
        return (int) ($matches[1] . $matches[2] . $matches[3]);
    }

    return 0;
}

function formatTicketPrice($price) {
    return 'NT$' . number_format((int) $price);
}
