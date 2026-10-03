<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();
$dataFile = __DIR__ . '/data/orders.json';

// Khởi tạo file JSON dự phòng nếu chưa có
if (!file_exists($dataFile)) {
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0777, true);
    }
    file_put_contents($dataFile, '[]');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $orders = [];
    $dataSource = 'Local JSON File';

    if ($pdo !== null) {
        try {
            $stmt = $pdo->query("SELECT id, customer, phone, address, order_date as date, total, status, pay, processed_by as processedBy FROM orders ORDER BY created_at DESC LIMIT 50");
            $orders = $stmt->fetchAll();
            $dataSource = 'MySQL Central Database (VM 4)';
        } catch (Throwable $e) {
            $pdo = null;
        }
    }

    if ($pdo === null) {
        $content = file_get_contents($dataFile);
        $orders = json_decode($content, true) ?: [];
    }

    echo json_encode([
        'status' => 'success',
        'source' => $dataSource,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown Web Server',
        'total' => count($orders),
        'data' => $orders
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }

    $newId = 'DH' . date('ymd') . rand(100, 999);
    $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Web Server';
    $newOrder = [
        'id' => $newId,
        'customer' => $input['customer'] ?? 'Khách vãng lai',
        'phone' => $input['phone'] ?? '',
        'address' => $input['address'] ?? '',
        'date' => date('d/m/Y'),
        'total' => (float)($input['total'] ?? 0),
        'status' => 'Mới đặt',
        'pay' => $input['pay'] ?? 'Tiền mặt',
        'items' => $input['items'] ?? [],
        'processedBy' => $serverSoftware
    ];

    $savedToMySQL = false;
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare("INSERT INTO orders (id, customer, phone, address, order_date, total, status, pay, processed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $newOrder['id'],
                $newOrder['customer'],
                $newOrder['phone'],
                $newOrder['address'],
                $newOrder['date'],
                $newOrder['total'],
                $newOrder['status'],
                $newOrder['pay'],
                $newOrder['processedBy']
            ]);
            $savedToMySQL = true;
        } catch (Throwable $e) {
            $savedToMySQL = false;
        }
    }

    // Luôn lưu bản sao vào file JSON cục bộ
    $content = file_get_contents($dataFile);
    $orders = json_decode($content, true) ?: [];
    array_unshift($orders, $newOrder);
    file_put_contents($dataFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'status' => 'success',
        'message' => 'Đặt hàng thành công và đã lưu trữ',
        'storage' => $savedToMySQL ? 'MySQL (VM 4 - Thành Phát) + JSON Backup' : 'Local JSON File (Fallback)',
        'order' => $newOrder
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
