<?php

function archiveCancelledTickets(PDO $pdo, int $orderId): void
{
    $archiveStmt = $pdo->prepare(
        "INSERT INTO CancelledTicket
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
         WHERE ticket.order_id = :order_id"
    );
    $archiveStmt->execute([':order_id' => $orderId]);
}

function abandonPendingOrderAndReleaseSeats(PDO $pdo, int $orderId, int $customerId): bool
{
    $pdo->beginTransaction();

    try {
        $orderStmt = $pdo->prepare(
            "SELECT order_id, status
             FROM Orders
             WHERE order_id = :order_id
               AND user_id = :customer_id
             FOR UPDATE"
        );
        $orderStmt->execute([
            ':order_id' => $orderId,
            ':customer_id' => $customerId,
        ]);
        $order = $orderStmt->fetch();

        if (!$order || $order['status'] !== 'pending_payment') {
            $pdo->rollBack();
            return false;
        }

        $seatStmt = $pdo->prepare(
            "UPDATE Seat s
             INNER JOIN Ticket t ON t.seat_id = s.seat_id
             SET s.status = 'available'
             WHERE t.order_id = :order_id"
        );
        $seatStmt->execute([':order_id' => $orderId]);

        $ticketStmt = $pdo->prepare('DELETE FROM Ticket WHERE order_id = :order_id');
        $ticketStmt->execute([':order_id' => $orderId]);

        $deleteOrderStmt = $pdo->prepare(
            "DELETE FROM Orders
             WHERE order_id = :order_id
               AND user_id = :customer_id
               AND status = 'pending_payment'"
        );
        $deleteOrderStmt->execute([
            ':order_id' => $orderId,
            ':customer_id' => $customerId,
        ]);

        if ($deleteOrderStmt->rowCount() !== 1) {
            throw new RuntimeException('Pending order status changed while abandoning.');
        }

        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function cancelPendingOrderAndReleaseSeats(PDO $pdo, int $orderId, ?int $customerId = null, bool $requireExpired = false): bool
{
    $pdo->beginTransaction();

    try {
        $params = [':order_id' => $orderId];
        $customerFilter = '';

        if ($customerId !== null) {
            $customerFilter = ' AND user_id = :customer_id';
            $params[':customer_id'] = $customerId;
        }

        $orderStmt = $pdo->prepare(
            "SELECT order_id, status, created_at
             FROM Orders
             WHERE order_id = :order_id
               $customerFilter
             FOR UPDATE"
        );
        $orderStmt->execute($params);
        $order = $orderStmt->fetch();

        if (!$order || $order['status'] !== 'pending_payment') {
            $pdo->rollBack();
            return false;
        }

        if ($requireExpired && orderPaymentSecondsRemaining($order['created_at']) > 0) {
            $pdo->rollBack();
            return false;
        }

        $ticketStmt = $pdo->prepare(
            'SELECT seat_id
             FROM Ticket
             WHERE order_id = :order_id'
        );
        $ticketStmt->execute([':order_id' => $orderId]);
        $seatIds = array_map('intval', $ticketStmt->fetchAll(PDO::FETCH_COLUMN));

        archiveCancelledTickets($pdo, $orderId);

        $seatStmt = $pdo->prepare(
            "UPDATE Seat
             SET status = 'available'
             WHERE seat_id = :seat_id"
        );

        foreach ($seatIds as $seatId) {
            $seatStmt->execute([':seat_id' => $seatId]);
        }

        $deleteStmt = $pdo->prepare('DELETE FROM Ticket WHERE order_id = :order_id');
        $deleteStmt->execute([':order_id' => $orderId]);

        $updateStmt = $pdo->prepare(
            "UPDATE Orders
             SET status = 'cancelled'
             WHERE order_id = :order_id
               AND status = 'pending_payment'"
        );
        $updateStmt->execute([':order_id' => $orderId]);

        if ($updateStmt->rowCount() !== 1) {
            throw new RuntimeException('Pending order status changed while cancelling.');
        }

        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function cancelOrderAndReleaseSeats(PDO $pdo, int $orderId, ?int $customerId = null): bool
{
    $pdo->beginTransaction();

    try {
        $params = [':order_id' => $orderId];
        $customerFilter = '';

        if ($customerId !== null) {
            $customerFilter = ' AND user_id = :customer_id';
            $params[':customer_id'] = $customerId;
        }

        $orderStmt = $pdo->prepare(
            "SELECT order_id, status
             FROM Orders
             WHERE order_id = :order_id
               $customerFilter
             FOR UPDATE"
        );
        $orderStmt->execute($params);
        $order = $orderStmt->fetch();

        if (!$order || !in_array($order['status'], ['pending_payment', 'paid'], true)) {
            $pdo->rollBack();
            return false;
        }

        $ticketStmt = $pdo->prepare(
            'SELECT seat_id
             FROM Ticket
             WHERE order_id = :order_id'
        );
        $ticketStmt->execute([':order_id' => $orderId]);
        $seatIds = array_map('intval', $ticketStmt->fetchAll(PDO::FETCH_COLUMN));

        archiveCancelledTickets($pdo, $orderId);

        $seatStmt = $pdo->prepare(
            "UPDATE Seat
             SET status = 'available'
             WHERE seat_id = :seat_id"
        );

        foreach ($seatIds as $seatId) {
            $seatStmt->execute([':seat_id' => $seatId]);
        }

        $deleteStmt = $pdo->prepare('DELETE FROM Ticket WHERE order_id = :order_id');
        $deleteStmt->execute([':order_id' => $orderId]);

        $updateStmt = $pdo->prepare(
            "UPDATE Orders
             SET status = 'cancelled'
             WHERE order_id = :order_id
               AND status IN ('pending_payment', 'paid')"
        );
        $updateStmt->execute([':order_id' => $orderId]);

        if ($updateStmt->rowCount() !== 1) {
            throw new RuntimeException('Order status changed while cancelling.');
        }

        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}
