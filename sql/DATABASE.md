# 📊 演唱會售票系統 資料庫設計規格書 (DATABASE.md) - 最新校對版

本文件為 `concert_system` 售票專案之最新官方資料庫規格書。本版架構**已完全對齊最新修訂之實體 DDL 腳本**，供全組（前端網頁、後台網頁、System Manager）開發串接時作為唯一權威對齊標準。

## 🌐 資料庫全域設定與環境重置
* **資料庫名稱**：`concert_system`
* **儲存引擎**：預設為 `InnoDB` (支援外鍵約束與行級鎖定，確保高併發搶票時之交易安全性與防超賣)
* **語系編碼**：`utf8mb4_general_ci` (完整支援 Emoji 貼圖 `🎤/🔥` 存入與防呆的不區分大小寫登入)
* **環境重置機制**：腳本頂端已內建 `SET FOREIGN_KEY_CHECKS = 0; DROP TABLE IF EXISTS...` 機制。開發階段若有欄位微調，直接在 phpMyAdmin 重新匯入即可無痛乾淨重置，不會被外鍵限制卡住。

---

## 📊 資料表欄位詳細規格 (Table Schemas)

### 1. User (使用者與管理員名單)
* **用途**：控管全系統人員登入與詳細個人檔案（Profile）。前台會員與後台管理員共用此表。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與防呆說明 |
| :--- | :--- | :--- | :--- |
| `user_id` | INT | PK, AUTO_INCREMENT | 使用者唯一流水號 |
| `username` | VARCHAR(50) | NOT NULL, UNIQUE | 登入帳號，全系統不可重複，防註冊撞名 |
| `real_name` | VARCHAR(50) | NOT NULL | 使用者真實姓名 |
| `birth_date` | DATETIME | NOT NULL | 出生日期 |
| `phone_num` | VARCHAR(20) | NOT NULL | 聯絡電話 |
| `id_number` | VARCHAR(20) | NOT NULL, UNIQUE | 身分證字號/護照號，**【限制：UNIQUE】** 嚴格鎖定一人一帳號 |
| `email` | VARCHAR(100)| NOT NULL | 電子信箱 |
| `user_address` | VARCHAR(255)| NULL | 聯絡地址 (允許為空) |
| `password` | VARCHAR(255)| NOT NULL | 密碼雜湊值 (存放 PHP `password_hash()` 安全加密字串) |
| `role` | ENUM | DEFAULT 'customer' | 權限標籤：限制只能填 `'customer'` 或 `'manager'` |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP| 帳號註冊時間 |

---

### 2. PromoCode (促銷折扣碼)
* **用途**：管理折扣優惠代碼。管理員可後台 CRUD，客戶結帳時輸入可直接扣抵總額。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與防呆說明 |
| :--- | :--- | :--- | :--- |
| `promo_id` | INT | PK, AUTO_INCREMENT | 促銷碼流水號 |
| `code_name` | VARCHAR(50) | NOT NULL, UNIQUE | 折扣碼字串 (例如：`NCT2026`, `HAPPY500`) |
| `discount_amount`| INT | NOT NULL | 折抵之新台幣金額 (直接減免，如：`200`) |
| `is_active` | BOOLEAN | DEFAULT TRUE | 是否啟用 (TRUE 代表可用，FALSE 代表被管理員停用封鎖) |

---

### 3. Concert (演唱會主資料表)
* **用途**：存放活動的核心靜態與全域售票時間資訊。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與防呆說明 |
| :--- | :--- | :--- | :--- |
| `concert_id` | INT | PK, AUTO_INCREMENT | 演唱會主體 ID |
| `artist` | VARCHAR(100)| NOT NULL | 藝人/團體/表演者名稱 (如：`aespa`, `婉晴`) |
| `title` | VARCHAR(255)| NOT NULL | 演唱會完整標題 (如：`Taipei Arena Tour`) |
| `venue` | VARCHAR(100)| NOT NULL | 演出場館場地名稱 (如：`台北大巨蛋`, `台北小巨蛋`) |
| `concert_address`| VARCHAR(255)| NOT NULL | 場館實體地址 |
| `image` | VARCHAR(255)| NULL | 演唱會海報圖片相對路徑或外連 URL |
| `sale_start` | DATETIME | NOT NULL | 該活動網路售票**開賣時間** |
| `sale_end` | DATETIME | NOT NULL | 該活動網路售票**截止時間** |
| `description` | TEXT | NULL | 活動詳情文案介紹 (支援長文字) |
| `notice` | TEXT | NULL | 購票、取票及現場入場注意事項公告 |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP| 後台建立活動的時間紀錄 |

---

### 4. ShowDate (演唱會場次時間表)
* **用途**：對應「每場活動至少要有兩個場次時間」的死規定，用外鍵與 Concert 表 1 對多綁定。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與防呆說明 |
| :--- | :--- | :--- | :--- |
| `show_id` | INT | PK, AUTO_INCREMENT | 單一場次 ID (如：`101`, `102`) |
| `concert_id` | INT | FK, NOT NULL | 指向 `Concert`。**【ON DELETE CASCADE】主活動被刪，所有場次自動連帶清除** |
| `show_datetime` | DATETIME | NOT NULL | 該場次的確切開演日期與時間 |
| `status` | VARCHAR(50) | DEFAULT 'available' | 場次購票狀態。預設 `'available'`(可買)，其餘可填 `'sold_out'`(已售完), `'ended'`(已結束) |

---

### 5. Seat (單一場次座位庫存表)
* **用途**：管理該場次的所有椅子實體庫存與狀態。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與防呆說明 |
| :--- | :--- | :--- | :--- |
| `seat_id` | INT | PK, AUTO_INCREMENT | 實體位置全局唯一金鑰 ID |
| `show_id` | INT | FK, NOT NULL | 指向 `ShowDate`。**【ON DELETE CASCADE】場次刪除，座位自動清除** |
| `seat_number` | VARCHAR(50) | NOT NULL | 座位排號區標籤 (如：`幸福搖滾區_15號`、`特典區_30號`) |
| `price` | DECIMAL(10,2)| NOT NULL | **採高精確度 DECIMAL 型態**，符合財務交易與教授評分高分標準 |
| `status` | VARCHAR(50) | DEFAULT 'available' | 椅子即時狀態。預設 `'available'`(空位)，其餘可填 `'reserved'`(搶票保留中), `'sold'`(已售出) |

---

### 6. Orders (訂單總主表)
* **用途**：當使用者選定座位確認送出時，產生的交易與請款主紀錄。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與防呆說明 |
| :--- | :--- | :--- | :--- |
| `order_id` | INT | PK, AUTO_INCREMENT | 訂單主鍵編號 |
| `user_id` | INT | FK | 指向 `User`。下單買票的會員 |
| `show_id` | INT | FK | 指向 `ShowDate`。買哪一個活動場次 |
| `promo_id` | INT | FK, NULL | 指向 `PromoCode`。**【ON DELETE SET NULL】折扣碼若過期被後台刪除，訂單不受影響，此處自動轉 NULL** |
| `total_price` | INT | NOT NULL | 最終扣抵完折扣優惠後的總實付金額 |
| `status` | ENUM | DEFAULT 'pending_payment'| 交易進度：限制隻能填 `'pending_payment'`(待付款)、`'paid'`(已付款)、`'cancelled'`(已取消) |
| `payment_method` | VARCHAR(30) | NULL | 完成付款時記錄付款方式：`credit_card` 或 `atm_transfer` |
| `delivery_method` | VARCHAR(30) | NULL | 完成付款時記錄取票方式：`ibon` 或 `venue_pickup` |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP| 訂單建立時間。**用來發動倒數 10 分鐘未付款自動釋出座位的時間基準** |

---

### 7. Ticket (門票明細表 - 實名制核心)
* **用途**：實作「一筆訂單可購買多張票」，並在此強制綁定進場持票人的實名制證件資訊。
| 欄位名稱 (Column) | 資料型態 (Type) | 約束限制 (Constraints) | 白話功能與前端對齊說明 |
| :--- | :--- | :--- | :--- |
| `ticket_id` | INT | PK, AUTO_INCREMENT | 實體門票唯一號碼 |
| `order_id` | INT | FK | 指向 `Orders`。**【ON DELETE CASCADE】訂單若取消，門票明細自動整檔銷毀** |
| `seat_id` | INT | FK, **UNIQUE 限制** | 指向 `Seat`。**【核心防超賣約束】加上 UNIQUE 約束：全系統中，這張椅子在 Ticket 表只能出現一次。從資料庫底層徹底封印「兩個人同時買到同個座位」的 Bug！** |
| `real_name` | VARCHAR(100)| NOT NULL | **【加分挑戰：實名制】** 進場持票人真實姓名，購買後不允許修改 |
| `id_number` | VARCHAR(50) | NOT NULL | **【加分挑戰：實名制】** 進場持票人身分證字號或護照號碼，進場核對用 |

---

## 🧠 資料庫核心防呆（Foolproof）商業邏輯落實指引

請負責網頁後端與 System Manager 的同學，在撰寫 PHP 邏輯時，直接調用本資料庫的底層限制進行防呆判斷：

1. **座位保留與 10 分鐘超時自動釋出**：
   * 當客戶點選座位送出時，後端請開啟 **Database Transaction (交易鎖定)**，將 `Seat.status` 改為 `reserved`，並在 `Orders` 建立一筆狀態為 `pending_payment` 的紀錄。
   * 後台排程（System Manager）必須每分鐘執行以下清理 SQL，抓出超時未付的死單：
     ```sql
     SELECT order_id FROM Orders WHERE status = 'pending_payment' AND created_at < NOW() - INTERVAL 10 MINUTE;
     ```
   * 抓出後，將 `Orders.status` 改為 `'cancelled'`，並把該單綁定的 `Seat.status` 改回 `'available'`，即可無痛釋出座位庫存！
2. **每人每場限購 2 張票防呆**：
   * 在前台網頁按下確認購票前，後端必須先下這條 SQL 檢查該會員當前已經在該場次佔有的有效票數：
     ```sql
     SELECT COUNT(*) FROM Ticket 
     JOIN Orders ON Ticket.order_id = Orders.order_id 
     WHERE Orders.user_id = :user_id AND Orders.show_id = :show_id AND Orders.status != 'cancelled';
     ```
   * 如果回傳的數量加上他目前想買的張數 **大於 2**，後端必須直接終止寫入並拋出錯誤，即可完成規格書規定的限購防呆限制。
