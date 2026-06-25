<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/openai-support.php';

header('Content-Type: application/json; charset=utf-8');

function findProductMatches(mysqli $conn, string $message): array
{
    preg_match_all('/[a-zA-Z0-9]+/', strtolower($message), $wordMatches);
    $ignoredWords = [
        'a', 'an', 'and', 'are', 'can', 'cost', 'do', 'does', 'for', 'get', 'have', 'how', 'i',
        'is', 'it', 'me', 'much', 'of', 'please', 'price', 'show', 'tell', 'the', 'this', 'what',
        'you', 'your', 'available', 'availability', 'stock', 'buy', 'want', 'need', 'rm',
    ];
    $keywords = [];
    foreach ($wordMatches[0] as $word) {
        if (strlen($word) >= 3 && !in_array($word, $ignoredWords, true) && !in_array($word, $keywords, true)) {
            $keywords[] = $word;
        }
    }
    $keywords = array_slice($keywords, 0, 5);

    if ($keywords === []) {
        return [];
    }

    $conditions = [];
    $params = [];
    foreach ($keywords as $keyword) {
        $conditions[] = '(productName LIKE ? OR productDescription LIKE ?)';
        $like = '%' . $keyword . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "SELECT productName, price, stockQuantity
            FROM products
            WHERE status = 'Active'
              AND complianceStatus = 'Approved'
              AND (expiryDate IS NULL OR expiryDate >= CURDATE())
              AND (" . implode(' OR ', $conditions) . ')
            ORDER BY productName ASC
            LIMIT 5';
    $productStmt = $conn->prepare($sql);
    if (!$productStmt) {
        error_log('Support AI product lookup failed: ' . $conn->error);
        return [];
    }

    $types = str_repeat('s', count($params));
    $productStmt->bind_param($types, ...$params);
    $productStmt->execute();
    $result = $productStmt->get_result();
    $products = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $productStmt->close();

    return $products;
}

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'customer') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please sign in again before using AI Quick Help.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$request = json_decode(file_get_contents('php://input'), true);
$message = trim((string) ($request['message'] ?? ''));
if ($message === '' || mb_strlen($message) > 1000) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Please enter a question of up to 1,000 characters.']);
    exit;
}

$userId = (int) $_SESSION['userID'];
$customerId = null;
$customerStmt = $conn->prepare('SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1');
if ($customerStmt) {
    $customerStmt->bind_param('i', $userId);
    $customerStmt->execute();
    $customerResult = $customerStmt->get_result();
    if ($customer = $customerResult->fetch_assoc()) {
        $customerId = (int) $customer['customer_id'];
    }
    $customerStmt->close();
}

if ($customerId === null) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Customer profile not found. Please send your message to support.']);
    exit;
}

$orders = [];
$orderStmt = $conn->prepare(
    'SELECT order_code, status, order_date, total, paymentMethod
     FROM customer_orders
     WHERE customer_id = ?
     ORDER BY created_at DESC, order_id DESC
     LIMIT 5'
);
if ($orderStmt) {
    $orderStmt->bind_param('i', $customerId);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    while ($order = $orderResult->fetch_assoc()) {
        $orders[] = $order;
    }
    $orderStmt->close();
}

$productMatches = findProductMatches($conn, $message);

echo json_encode(getSupportAiReply($message, $orders, $productMatches), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
