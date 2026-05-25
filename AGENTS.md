# AGENTS.md

## 專案總覽

本專案是資料庫管理期末專題的「演唱會訂票系統」。目前主要工作是前端頁面設計，使用 PHP 檔案輸出頁面，搭配 CSS 與 JavaScript 做版面與互動效果。

目前首頁已包含：

- 近期演唱會輪播廣告。
- 三場演唱會資料卡片。
- 依日期自動排序的近期演唱會列表。
- 點擊「查看詳情」進入對應演唱會詳細頁。
- 右上角會員登入入口。

資料目前可先寫在 PHP 陣列或 `includes/concerts.php`，之後再改成從 MySQL 查詢。

## 技術棧

- PHP：頁面檔案與未來資料庫串接。
- HTML：頁面結構。
- CSS：視覺設計與響應式版面，主要檔案為 `assets/css/style.css`。
- JavaScript：首頁輪播互動，主要檔案為 `assets/js/homepage.js`。
- MAMP / AppServ：組員本機 PHP 伺服器環境。
- MySQL：後續資料庫串接使用。

## 設定方式

不需要安裝 Node.js 或前端套件。

請將整個專案資料夾放到本機伺服器根目錄。

MAMP：

```text
/Applications/MAMP/htdocs/concert_system
```

AppServ：

```text
C:\AppServ\www\concert_system
```

請確認圖片資料夾也有一起複製：

```text
assets/images/
```

## 執行方式

MAMP 常見網址：

```text
http://localhost:8888/concert_system/index.php
```

如果 MAMP Apache port 是 `80`：

```text
http://localhost/concert_system/index.php
```

AppServ 常見網址：

```text
http://localhost/concert_system/index.php
```

`localhost` 是每位組員自己電腦的本機伺服器，不是同一台遠端主機。

## 測試方式

目前沒有自動化測試。

修改前端後，請至少人工檢查：

- 首頁可正常開啟。
- 輪播可自動播放。
- 左右切換按鈕可切換海報。
- 輪播圓點可切換海報。
- 海報完整顯示在 16:9 區域內，不要裁切。
- 下方近期演唱會依日期由左到右排序。
- 下方卡片按鈕都顯示「查看詳情」。
- 點擊卡片後可帶 `id` 進入 `customer/concert_detail.php`。
- 手機寬度下文字、按鈕、卡片不重疊也不被裁切。

## 目錄結構

- `index.php`：首頁。
- `README.md`：給組員看的開啟方式與專案說明。
- `AGENTS.md`：給 AI coding agents 的專案規範。
- `assets/css/style.css`：全站主要樣式。
- `assets/js/homepage.js`：首頁輪播 JavaScript。
- `assets/images/`：首頁演唱會海報圖片。
- `customer/login.php`：會員登入頁，目前為占位頁。
- `customer/member.php`：會員資訊頁，目前為占位頁。
- `customer/concert_detail.php`：演唱會詳細頁，目前為占位頁。
- `customer/register.php`：會員註冊頁，目前尚待設計。
- `manager/dashboard.php`：管理者後台頁，目前尚待設計。
- `includes/concerts.php`：演唱會資料可抽離使用的位置。
- `includes/db.example.php`：未來資料庫連線範例。
- `sql/schema.sql`：未來資料庫 schema。

## 程式風格

- PHP 輸出資料時使用 `htmlspecialchars()`。
- CSS 儘量集中寫在 `assets/css/style.css`。
- JavaScript 只負責互動，不硬寫演唱會資料。
- 圖片路徑使用專案相對路徑，例如 `assets/images/concert-1.png`。
- 首頁演唱會資料應維持單一來源，避免輪播與卡片資料不同步。
- 若狀態是 `已售完` 或 `已結束`，首頁仍可進入詳細頁，但購買限制應放在詳細頁處理。
- 不要移除使用者提供的圖片，也不要任意更改圖片檔名。

## 部署備註

目前以本機 MAMP / AppServ 展示為主，沒有正式部署流程。

若之後串接資料庫，每位組員需要：

- 匯入 `sql/schema.sql`。
- 建立自己的 MySQL 資料庫。
- 依照本機帳號密碼設定資料庫連線檔。
- 確認資料庫名稱、帳號、密碼、port 與本機環境一致。

## 給 AI Coding Agents 的指示

- 修改前先閱讀 `README.md`、`AGENTS.md` 與相關 PHP/CSS/JS。
- 除非使用者明確要求，不要大幅重構整個專案。
- 若使用者只要求修改某一頁，修改範圍應限制在該頁與必要的 CSS/JS。
- 不要自行刪除 `.php` 頁面、圖片、README 或使用者新增的資料。
- 不要把登入、詳情、後台功能全部塞進首頁。
- 首頁只負責展示與導覽，詳細頁才負責顯示是否可購買。
- 完成後簡短說明修改了哪些檔案，以及如何在 MAMP / AppServ 中查看。
