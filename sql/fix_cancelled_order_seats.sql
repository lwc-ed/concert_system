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

START TRANSACTION;

INSERT INTO CancelledTicket
    (order_id, show_id, seat_id, seat_number, price, real_name, id_number)
SELECT
    orders_table.order_id,
    orders_table.show_id,
    seat.seat_id,
    seat.seat_number,
    seat.price,
    ticket.real_name,
    ticket.id_number
FROM Ticket ticket
INNER JOIN Orders orders_table ON orders_table.order_id = ticket.order_id
INNER JOIN Seat seat ON seat.seat_id = ticket.seat_id
WHERE orders_table.status = 'cancelled'
  AND NOT EXISTS (
      SELECT 1
      FROM CancelledTicket existing_cancelled_ticket
      WHERE existing_cancelled_ticket.order_id = ticket.order_id
        AND existing_cancelled_ticket.seat_id = ticket.seat_id
  );

START TRANSACTION;

UPDATE Seat seat
INNER JOIN Ticket ticket ON ticket.seat_id = seat.seat_id
INNER JOIN Orders orders_table ON orders_table.order_id = ticket.order_id
SET seat.status = 'available'
WHERE orders_table.status = 'cancelled';

DELETE ticket
FROM Ticket ticket
INNER JOIN Orders orders_table ON orders_table.order_id = ticket.order_id
WHERE orders_table.status = 'cancelled';

COMMIT;
