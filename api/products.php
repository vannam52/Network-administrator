<?php
// ========================================================
// ĐỀ TÀI 10 - QUẢN TRỊ MẠNG: PRODUCTS API (DUAL-MODE DATABASE)
// ========================================================
// Hỗ trợ đồng thời MySQL tập trung (VM 4) và JSON fallback cục bộ.

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
$dataFile = __DIR__ . '/data/products.json';

if (!file_exists($dataFile)) {
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0777, true);
    }
    file_put_contents($dataFile, '[]');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $products = [];
    $dataSource = 'Local JSON File';

    if ($pdo !== null) {
        try {
            $stmt = $pdo->query("SELECT id, code, name, brand, category, price, cost, old_price as old, ram, rom, chip, screen, camera, battery, stock, rating, is_new as isNew, imported, sold FROM products ORDER BY id ASC");
            $products = $stmt->fetchAll();
            $dataSource = 'MySQL Central Database (VM 4)';
        } catch (Throwable $e) {
            $pdo = null;
        }
    }

    if ($pdo === null) {
        $content = file_get_contents($dataFile);
        $products = json_decode($content, true) ?: [];
    }

    echo json_encode([
        'status' => 'success',
        'source' => $dataSource,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown Web Server',
        'total' => count($products),
        'data' => $products
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!$input || empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Tên sản phẩm không được trống']);
        exit;
    }

    $content = file_get_contents($dataFile);
    $products = json_decode($content, true) ?: [];

    $newId = count($products) > 0 ? max(array_column($products, 'id')) + 1 : 1;
    $newProduct = [
        'id' => $newId,
        'code' => $input['code'] ?? ('SP0' . str_pad($newId, 2, '0', STR_PAD_LEFT)),
        'name' => $input['name'],
        'brand' => $input['brand'] ?? 'Khác',
        'category' => $input['category'] ?? 'Điện thoại',
        'price' => (float)($input['price'] ?? 0),
        'cost' => (float)($input['cost'] ?? 0),
        'stock' => (int)($input['stock'] ?? 10),
        'rating' => 5.0,
        'isNew' => true,
        'imported' => (int)($input['stock'] ?? 10),
        'sold' => 0
    ];

    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (code, name, brand, category, price, cost, stock, rating, is_new, imported, sold) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 0)");
            $stmt->execute([
                $newProduct['code'],
                $newProduct['name'],
                $newProduct['brand'],
                $newProduct['category'],
                $newProduct['price'],
                $newProduct['cost'],
                $newProduct['stock'],
                $newProduct['rating'],
                $newProduct['imported']
            ]);
        } catch (Throwable $e) {}
    }

    $products[] = $newProduct;
    file_put_contents($dataFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'status' => 'success',
        'message' => 'Thêm sản phẩm thành công',
        'product' => $newProduct
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
