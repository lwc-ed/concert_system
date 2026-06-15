USE concert_system;

CREATE TABLE IF NOT EXISTS CancelledTicket (
    cancelled_ticket_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    show_id INT NOT NULL,
    seat_id INT NULL,
    seat_number VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    real_name VARCHAR(100) NOT NULL,
    id_number VARCHAR(50) NOT NULL,
    cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cancelled_ticket_show (show_id),
    INDEX idx_cancelled_ticket_order (order_id),
    FOREIGN KEY (order_id) REFERENCES Orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (show_id) REFERENCES ShowDate(show_id) ON DELETE CASCADE,
    FOREIGN KEY (seat_id) REFERENCES Seat(seat_id) ON DELETE SET NULL
) ENGINE=InnoDB;
