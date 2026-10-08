<?php
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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
        // Cố gắng tự động vá Database: Thêm cột items nếu chưa có
        try {
            $pdo->exec("ALTER TABLE orders ADD COLUMN items TEXT");
        } catch (Throwable $ignore) {}

        try {
            $stmt = $pdo->query("SELECT id, customer, phone, address, order_date as date, total, status, pay, processed_by as processedBy, items FROM orders ORDER BY created_at DESC LIMIT 50");
            $rawOrders = $stmt->fetchAll();
            $orders = [];
            foreach ($rawOrders as $o) {
                // Parse chuỗi JSON items từ MySQL thành Mảng (Array) để Frontend hiển thị được
                $o['items'] = !empty($o['items']) ? json_decode($o['items'], true) : [];
                $orders[] = $o;
            }
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
        // Cố gắng tự động vá Database: Thêm cột items nếu chưa có
        try {
            $pdo->exec("ALTER TABLE orders ADD COLUMN items TEXT");
        } catch (Throwable $ignore) {}

        try {
            $stmt = $pdo->prepare("INSERT INTO orders (id, customer, phone, address, order_date, total, status, pay, processed_by, items) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $newOrder['id'],
                $newOrder['customer'],
                $newOrder['phone'],
                $newOrder['address'],
                $newOrder['date'],
                $newOrder['total'],
                $newOrder['status'],
                $newOrder['pay'],
                $newOrder['processedBy'],
                json_encode($newOrder['items'], JSON_UNESCAPED_UNICODE)
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

if ($method === 'DELETE') {
    $orderId = $_GET['id'] ?? '';
    if (!$orderId) {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $orderId = $input['id'] ?? '';
    }

    if ($orderId) {
        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
                $stmt->execute([$orderId]);
            } catch (Throwable $e) {}
        }
        $content = file_get_contents($dataFile);
        $orders = json_decode($content, true) ?: [];
        $orders = array_values(array_filter($orders, function($o) use ($orderId) {
            return $o['id'] !== $orderId;
        }));
        file_put_contents($dataFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    echo json_encode(['status' => 'success', 'message' => "Đã xóa đơn hàng {$orderId}"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================
// CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG (PUT)
// ==========================================
if ($method === 'PUT') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if ($input && !empty($input['id'])) {
        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
                $stmt->execute([$input['status'] ?? 'Đang giao', $input['id']]);
            } catch (Throwable $e) {}
        }
        
        $content = file_get_contents($dataFile);
        $orders = json_decode($content, true) ?: [];
        foreach ($orders as &$o) {
            if ((string)$o['id'] === (string)$input['id'] || (string)('#'.$o['id']) === (string)$input['id']) {
                if (isset($input['status'])) $o['status'] = $input['status'];
            }
        }
        file_put_contents($dataFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    echo json_encode(['status' => 'success']);
    exit;
}

