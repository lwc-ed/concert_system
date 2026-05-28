USE concert_system;

CREATE TABLE IF NOT EXISTS Organizer (
    organizer_id INT AUTO_INCREMENT PRIMARY KEY,
    organizer_name VARCHAR(100) NOT NULL UNIQUE,
    contact_person VARCHAR(100) NULL,
    contact_email VARCHAR(100) NULL,
    contact_phone VARCHAR(30) NULL,
    organizer_address VARCHAR(255) NULL,
    note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE Concert
ADD COLUMN organizer_id INT NULL;

ALTER TABLE Concert
ADD CONSTRAINT fk_concert_organizer
FOREIGN KEY (organizer_id) REFERENCES Organizer(organizer_id)
ON DELETE SET NULL;

INSERT IGNORE INTO Organizer
    (organizer_name, contact_person, contact_email, contact_phone, organizer_address, note)
VALUES
    ('幸福娛樂股份有限公司', '王幸福', 'happy@example.com', '02-2345-6789', '台北市信義區幸福路 1 號', '負責大型流行音樂與跨界合作活動。'),
    ('晴天活動企劃', '陳婉晴', 'sunny@example.com', '02-8765-4321', '台北市松山區南京東路四段 2 號', '粉絲見面會與小型巡迴活動主辦。'),
    ('Final Call Global', 'Alex Chen', 'finalcall@example.com', '+1-212-555-0199', 'New York, NY, United States', '海外場館與國際巡演窗口。');

UPDATE Concert c
JOIN Organizer o ON o.organizer_name = '幸福娛樂股份有限公司'
SET c.organizer_id = o.organizer_id
WHERE c.concert_id = 1;

UPDATE Concert c
JOIN Organizer o ON o.organizer_name = '晴天活動企劃'
SET c.organizer_id = o.organizer_id
WHERE c.concert_id = 2;

UPDATE Concert c
JOIN Organizer o ON o.organizer_name = 'Final Call Global'
SET c.organizer_id = o.organizer_id
WHERE c.concert_id = 3;
