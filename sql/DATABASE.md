# 📊 演唱會售票系統 資料庫設計規格書 (DATABASE.md)

本文件為 `concert_system` 售票專案之最新修訂版資料庫規格書。本版架構**已完全對齊前端 PHP 樣板（Mock Data）之欄位命名與邏輯**，供全組（前端網頁、後台網頁、System Manager）開發串接時作為唯一權威對齊標準。

## 🌐 資料庫全域設定
* **資料庫名稱**：`concert_system`
* **儲存引擎**：`InnoDB` (支援外鍵約束與 Row-level Locking，確保搶票時不發生併發衝突與超賣)
* **語系編碼**：`utf8mb4_general_ci`
  * *DBA 備註*：全面支援韓團、日星名稱與使用者回饋中的 **Emoji 貼圖 (🎤, 🎸, 🔥)**；`_ci` 限制確保會員登入時帳號不區分大小寫，具備高度防呆特性。

---

## 🗺️ 最新架構關係圖 (ERD 邏輯異動說明)
根據最新前端頁面重構邏輯：
* 將 `artist`(藝人)、`venue`(場館)、`address`(地址)、`sale_start/end`(售票起迄) 統一收攏於 **`Concert` (主活動表)**。
* 本設計代表：「同一個演唱會主題（如：Taipei Arena Tour）的所有日期場次，皆預設在同一個表演場館舉行」。
* 每一場 Concert 透過外鍵長出多個 **`ShowDate` (場次日期時間)**；每個場次再長出專屬的 **`Seat` (座位椅子庫存)**。

---

## 📊 資料表欄位詳細規格 (Table Schemas)

### 1. Concert (演唱會主資料表)
* **用途**：存放活動的核心靜態與售票時間資訊。完美對齊 `getConcertTable()`。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與前端對齊說明 |
| :--- | :--- | :--- | :--- |
| `concert_id` | INT | PK, AUTO_INCREMENT | 演唱會唯一代碼 (自動遞增) |
| `artist` | VARCHAR(255) | NOT NULL | 藝人/表演者名稱 (如：`史詩級跨界合作 <幸福崴孟演唱會 x æspa>`) |
| `title` | VARCHAR(255) | NOT NULL | 巡迴活動標題 (如：`2026 Taipei Arena Tour`) |
| `venue` | VARCHAR(255) | NOT NULL | 演出場館場地名稱 (如：`台北大巨蛋`) |
| `address` | VARCHAR(255) | NOT NULL | 場館詳細實體地址 (如：`台北市信義區...`) |
| `image` | VARCHAR(255) | NOT NULL | 演唱會海報海報圖片路徑 (如：`assets/images/concert-1.png`) |
| `sale_start` | DATETIME | NOT NULL | 網路**開放購票**開始時間 |
| `sale_end` | DATETIME | NOT NULL | 網路**截止售票**結束時間 |
| `description` | TEXT | NULL | 活動詳情文字介紹 |
| `notice` | TEXT | NULL | 購票注意事項、重要公告與提醒 |

---

### 2. ShowDate (演唱會場次時間表)
* **用途**：管理特定演唱會底下的多個場次。完美對齊 `getShowDateTable()`。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與前端對齊說明 |
| :--- | :--- | :--- | :--- |
| `show_id` | INT | PK | 場次編號 (假資料對齊：`101`, `102`, `201`) |
| `concert_id` | INT | FK, NOT NULL | 指向 `Concert`。**【ON DELETE CASCADE】主活動刪除，所有日期場次自動清除** |
| `show_datetime` | DATETIME | NOT NULL | 該場次的確切開演日期與時間 (如：`2026-06-28 19:30:00`) |
| `status` | VARCHAR(50) | DEFAULT 'available' | 場次售票狀態：限制填入 `available`(可購買)、`sold_out`(已售完)、`ended`(已結束) |

---

### 3. Seat (單一場次座位庫存表)
* **用途**：全系統最核心的椅子庫存。用 `show_id` 來隔離周杰倫場與五月天場的座位。完美對齊 `getSeatTable()`。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與前端對齊說明 |
| :--- | :--- | :--- | :--- |
| `seat_id` | INT | PK, AUTO_INCREMENT | 實體椅子全局唯一 ID |
| `show_id` | INT | FK, NOT NULL | 指向 `ShowDate`。**【ON DELETE CASCADE】場次刪除，座位自動清除** |
| `seat_number` | VARCHAR(50) | NOT NULL | 座位排號區標籤 (如：`幸福搖滾區_12號`、`至尊包廂_1號`) |
| `price` | DECIMAL(10,2)| NOT NULL | 票價。**採精確金錢型態** (如：`5800.00`)，符合教授對財務系統的高分規範 |
| `status` | VARCHAR(50) | DEFAULT 'available' | 椅子即時狀態：`available`(空位)、`reserved`(保留中/搶票中)、`sold`(已售出) |

---

### 4. User (使用者與管理員名單)
* **用途**：前台會員登入與後台 System Manager 身分認證中心。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與前端對齊說明 |
| :--- | :--- | :--- | :--- |
| `user_id` | INT | PK, AUTO_INCREMENT | 使用者 ID |
| `username` | VARCHAR(50) | NOT NULL, UNIQUE | 登入帳號，全系統不可重複，防註冊撞名 |
| `email` | VARCHAR(100)| NOT NULL | 會員電子信箱，用於發送購票成功通知信 |
| `password` | VARCHAR(255)| NOT NULL | 密碼 (存放 PHP `password_hash()` 雜湊後的安全字串) |
| `role` | ENUM | DEFAULT 'customer' | 嚴格限定填入 `'customer'` (前台會員) 或 `'manager'` (後台管理員) |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP| 帳號註冊時間 |

---

### 5. PromoCode (促銷折扣碼表)
* **用途**：後台管理員可 CRUD，前台客戶結帳時輸入可直接扣抵票價總額。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與前端對齊說明 |
| :--- | :--- | :--- | :--- |
| `promo_id` | INT | PK, AUTO_INCREMENT | 折扣碼代碼流水號 |
| `code_name` | VARCHAR(50) | NOT NULL, UNIQUE | 折扣碼字串 (例如：`NCT2026`、`HAPPYWEN`) |
| `discount_amount`| INT | NOT NULL | 折抵之新台幣金額 (直接減免，如：`200`) |
| `is_active` | BOOLEAN | DEFAULT TRUE | 是否啟用 (TRUE 代表可用，FALSE 代表被管理員封鎖過期) |

---

### 6. Orders (訂單主總表)
* **用途**：當使用者選定座位確認送出時，產生的交易主紀錄。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與前端對齊說明 |
| :--- | :--- | :--- | :--- |
| `order_id` | INT | PK, AUTO_INCREMENT | 訂單編號流水號 |
| `user_id` | INT | FK, NOT NULL | 指向 `User`。買票的會員 |
| `show_id` | INT | FK, NOT NULL | 指向 `ShowDate`。購買的演唱會場次 |
| `promo_id` | INT | FK, NULL | 指向 `PromoCode`。**【ON DELETE SET NULL】折扣碼若被刪除，訂單不消失，此處自動轉 NULL** |
| `total_price` | INT | NOT NULL | 扣除折抵金額後的最終實付總金額 |
| `status` | ENUM | DEFAULT 'pending_payment'| 訂單進度：`pending_payment`(待付款)、`paid`(已付款)、`cancelled`(已取消) |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP| **訂單建立時間。用於後台監控「10分鐘未付款釋出座位」核心邏輯** |

---

### 7. Ticket (門票明細表 - 實名制核心)
* **用途**：實作「一筆訂單可購買多張票」，並強制綁定每一張票的持票人身分證件。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與前端對齊說明 |
| :--- | :--- | :--- | :--- |
| `ticket_id` | INT | PK, AUTO_INCREMENT | 實體門票唯一號碼 |
| `order_id` | INT | FK, NOT NULL | 指向 `Orders`。**【ON DELETE CASCADE】訂單若取消，門票明細自動整檔銷毀** |
| `seat_id` | INT | FK, **UNIQUE 限制** | 指向 `Seat`。**【核心防呆約束】加上 UNIQUE 約束：全資料庫中，這張椅子在門票表只能出現一次。從系統底層徹底封印「超賣、兩個人買到同個座位」的致命 Bug！** |
| `real_name` | VARCHAR(100)| NOT NULL | **【加分挑戰：實名制】** 入場人真實姓名，購買後不允許修改 |
| `id_number` | VARCHAR(50) | NOT NULL | **【加分挑戰：實名制】** 入場人身分證字號或護照號碼，進場核對用 |

---

## 🛠️ 後端與系統管理（System Manager）核心商業邏輯防呆

1. **搶票座位保留與超時 10 分鐘自動釋出**：
   * 當前台使用者點擊「訂票」時，後端必須發動 **Database Transaction**（交易鎖定）。
   * 將該 `Seat.status` 改為 `reserved`，並在 `Orders` 建立一筆 `pending_payment` 的訂單。
   * 系統管理員（System Manager）的排程或後台網頁，必須隨時執行以下清理 SQL，抓出超時未付的現行訂單：
     ```sql
     -- 找出下單超過 10 分鐘且還沒付錢的悲催訂單
     SELECT order_id FROM Orders WHERE status = 'pending_payment' AND created_at < NOW() - INTERVAL 10 MINUTE;
     ```
   * 抓出後，將 `Orders.status` 改為 `cancelled`，並把該訂單連動的 `Seat.status` 改回 `available` 釋出座位！
2. **每人每場限購 2 張票約束檢查**：
   * 在使用者按下確認購票送出前，後端必須先下這條 SQL 檢查他之前有沒有買過這場演唱會：
     ```sql
     SELECT COUNT(*) FROM Ticket 
     JOIN Orders ON Ticket.order_id = Orders.order_id 
     WHERE Orders.user_id = :user_id AND Orders.show_id = :show_id AND Orders.status != 'cancelled';
     ```
   * 如果回傳的加總數量 + 目前想買的張數 **大於 2**，後端必須直接終止寫入並回傳錯誤訊息，這就是教授評分最愛的 Fool-proof（防呆限制）。