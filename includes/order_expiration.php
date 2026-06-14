<?php

const ORDER_PAYMENT_TIMEOUT_SECONDS = 600;
const ORDER_PAYMENT_TIMEZONE = 'Asia/Taipei';

function orderPaymentDeadlineTimestamp($createdAt)
{
    try {
        $createdDateTime = new DateTimeImmutable((string) $createdAt, new DateTimeZone(ORDER_PAYMENT_TIMEZONE));
    } catch (Exception $exception) {
        return null;
    }

    return $createdDateTime->getTimestamp() + ORDER_PAYMENT_TIMEOUT_SECONDS;
}

function orderPaymentSecondsRemaining($createdAt)
{
    $deadlineTimestamp = orderPaymentDeadlineTimestamp($createdAt);

    if ($deadlineTimestamp === null) {
        return 0;
    }

    $nowTimestamp = (new DateTimeImmutable('now', new DateTimeZone(ORDER_PAYMENT_TIMEZONE)))->getTimestamp();

    return max(0, $deadlineTimestamp - $nowTimestamp);
}

function releaseReservedSeatsForOrders($connection, $orderIds)
{
    if (!$orderIds) {
        return 0;
    }

    $placeholders = implode(', ', array_fill(0, count($orderIds), '?'));
    $stmt = $connection->prepare(
        "UPDATE Seat seat
         INNER JOIN Ticket ticket ON ticket.seat_id = seat.seat_id
         SET seat.status = 'available'
         WHERE ticket.order_id IN ($placeholders)
           AND seat.status = 'reserved'"
    );
    $stmt->execute(array_values($orderIds));

    return $stmt->rowCount();
}

function cancelExpiredPendingOrders($connection, $customerId = null)
{
    $cancelledCount = 0;

    try {
        $connection->beginTransaction();

        $params = [];
        $customerFilter = '';

        if ($customerId !== null) {
            $customerFilter = ' AND user_id = ?';
            $params[] = (int) $customerId;
        }

        $stmt = $connection->prepare(
            "SELECT order_id
             FROM Orders
             WHERE status = 'pending_payment'
               AND created_at <= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
               $customerFilter
             FOR UPDATE"
        );
        $stmt->execute($params);
        $orderIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if ($orderIds) {
            $placeholders = implode(', ', array_fill(0, count($orderIds), '?'));
            $stmt = $connection->prepare(
                "UPDATE Orders
                 SET status = 'cancelled'
                 WHERE status = 'pending_payment'
                   AND order_id IN ($placeholders)"
            );
            $stmt->execute($orderIds);
            $cancelledCount = $stmt->rowCount();

            releaseReservedSeatsForOrders($connection, $orderIds);
        }

        $connection->commit();
    } catch (PDOException $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        error_log('Cancel expired pending orders failed: ' . $exception->getMessage());
        throw $exception;
    }

    return $cancelledCount;
}

function cancelExpiredPendingOrderById($connection, $orderId, $customerId = null)
{
    $cancelled = false;

    try {
        $connection->beginTransaction();

        $params = [(int) $orderId];
        $customerFilter = '';

        if ($customerId !== null) {
            $customerFilter = ' AND user_id = ?';
            $params[] = (int) $customerId;
        }

        $stmt = $connection->prepare(
            "SELECT order_id, status, created_at
             FROM Orders
             WHERE order_id = ?
               $customerFilter
             FOR UPDATE"
        );
        $stmt->execute($params);
        $order = $stmt->fetch();

        if ($order
            && $order['status'] === 'pending_payment'
            && orderPaymentSecondsRemaining($order['created_at']) <= 0
        ) {
            $updateStmt = $connection->prepare(
                "UPDATE Orders
                 SET status = 'cancelled'
                 WHERE order_id = ?
                   AND status = 'pending_payment'"
            );
            $updateStmt->execute([(int) $orderId]);
            releaseReservedSeatsForOrders($connection, [(int) $orderId]);
            $cancelled = $updateStmt->rowCount() > 0;
        }

        $connection->commit();
    } catch (PDOException $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        error_log('Cancel expired pending order failed: ' . $exception->getMessage());
        throw $exception;
    }

    return $cancelled;
}
