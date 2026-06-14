<?php

require_once __DIR__ . '/order_helpers.php';

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

function cancelExpiredPendingOrders($connection, $customerId = null)
{
    $cancelledCount = 0;

    try {
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
               $customerFilter"
        );
        $stmt->execute($params);
        $orderIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        foreach ($orderIds as $orderId) {
            if (cancelPendingOrderAndReleaseSeats($connection, $orderId, $customerId, true)) {
                $cancelledCount++;
            }
        }
    } catch (Throwable $exception) {
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
    try {
        return cancelPendingOrderAndReleaseSeats(
            $connection,
            (int) $orderId,
            $customerId === null ? null : (int) $customerId,
            true
        );
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        error_log('Cancel expired pending order failed: ' . $exception->getMessage());
        throw $exception;
    }

}
