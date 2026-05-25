# Concert System

資料庫管理期末專題：演唱會訂票系統。

目前主要是前端頁面設計，使用 PHP 檔案搭配 CSS、JavaScript，在 MAMP 或 AppServ 本機 PHP 伺服器環境中執行。

## 如何在本機開啟

`localhost` 是每個人自己電腦上的本機伺服器位址。組員只要把整個專案資料夾放到自己的伺服器根目錄，就可以用自己的 `localhost` 開網站。

### MAMP

把專案放在：

```text
/Applications/MAMP/htdocs/concert_system
```

開啟 MAMP，啟動 Apache 後，在瀏覽器輸入：

```text
http://localhost:8888/concert_system/index.php
```

如果 MAMP 的 Apache port 設成 `80`，則使用：

```text
http://localhost/concert_system/index.php
```

### AppServ

把專案放在：

```text
C:\AppServ\www\concert_system
```

啟動 AppServ / Apache 後，在瀏覽器輸入：

```text
http://localhost/concert_system/index.php
```

## 注意事項

- 每個人的 `localhost` 都是自己的電腦，不是別人的電腦。
- 請確認整個專案資料夾都有複製，包含 `assets/images/` 裡的演唱會海報圖片。
- 如果圖片沒有顯示，先檢查圖片檔名和 `index.php` 裡的路徑是否一致。
- 如果 Apache 使用的 port 不是 `80` 或 `8888`，網址要改成自己的 port。
- 之後若有串接資料庫，每位組員都需要匯入 SQL，並依照自己的 MySQL 帳號密碼設定資料庫連線檔。

## 目前首頁功能

- 近期演唱會輪播廣告。
- 三場演唱會資料卡片。
- 卡片按日期自動排序。
- 點擊「查看詳情」會進入對應的演唱會詳細頁。
- 右上角會員登入入口。
