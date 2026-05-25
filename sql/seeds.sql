-- 先切換到你們的資料庫
USE concert_system;

-- 1. 塞入基礎使用者 (密碼先打明碼，方便前端後續寫雜湊加密測試)
INSERT INTO User (username, email, password, role) VALUES 
('admin1', 'admin@nycu.edu.tw', '123456', 'manager'),
('customer1', 'buyer1@nycu.edu.tw', '123456', 'customer'),
('customer2', 'buyer2@nycu.edu.tw', '123456', 'customer');

-- 2. 塞入 3 場不同的演唱會資訊
INSERT INTO Concert (title, description) VALUES 
('NCT 2026 巡迴演唱會 - 台北場', 'NCT 狂潮來襲！🎤'),
('NMIXX Showcase - 過去回憶場', '這是一場已經辦完的感性回憶活動。✨'),
('TWICE <THIS IS FOR> WORLD TOUR', '地表最強搶票大戰，絕對秒殺！🔥');

-- 3. 塞入場次時間 (每場活動各 2 個場次，並對齊題目規定的狀態)
INSERT INTO ShowDate (concert_id, show_datetime, status) VALUES 
(1, '2026-08-15 19:30:00', 'available'), -- NCT 場次一: 可購買
(1, '2026-08-16 19:30:00', 'available'), -- NCT 場次二: 可購買
(2, '2026-01-10 19:00:00', 'ended'),     -- NMIXX 場次一: 已結束
(2, '2026-01-11 19:00:00', 'ended'),     -- NMIXX 場次二: 已結束
(3, '2026-12-24 19:30:00', 'sold_out'),  -- TWICE 場次一: 已售完
(3, '2026-12-25 19:30:00', 'sold_out');  -- TWICE 場次二: 已售完