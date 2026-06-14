USE concert_system;

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
