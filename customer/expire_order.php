<?php
session_start();
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/order_expiration.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['expired' => false, 'error' => 'login_required']);
    exit;
}

if (!$pdo instanceof PDO) {
    http_response_code(503);
    echo json_encode(['expired' => false, 'error' => 'database_unavailable']);
    exit;
}

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$orderId) {
    http_response_code(400);
    echo json_encode(['expired' => false, 'error' => 'invalid_order_id']);
    exit;
}

try {
    $expired = cancelExpiredPendingOrderById($pdo, $orderId, (int) $_SESSION['customer_id']);
    echo json_encode(['expired' => $expired]);
} catch (PDOException $exception) {
    http_response_code(500);
    echo json_encode(['expired' => false, 'error' => 'server_error']);
}
