<?php
if (ob_get_length()) ob_clean();
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

    $queryError = null;
    if ($pdo !== null) {
        try {
            $stmt = $pdo->query("SELECT * FROM products ORDER BY id ASC");
            $rawProducts = $stmt->fetchAll();
            $products = [];
            foreach ($rawProducts as $p) {
                $imgUrl = $p['img'] ?? $p['image_url'] ?? $p['image'] ?? '';
                $name = $p['name'] ?? '';
                if (empty($imgUrl)) {
                    if (stripos($name, '17') !== false || stripos($name, 'Air') !== false) {
                        $imgUrl = 'img/iphone17.webp';
                    } elseif (stripos($name, '16 Pro') !== false) {
                        $imgUrl = 'img/iphone-16-pro-max.png';
                    } elseif (stripos($name, '16') !== false) {
                        $imgUrl = 'img/iphone-16-den-128.webp';
                    } elseif (stripos($name, '15 Pro') !== false) {
                        $imgUrl = 'img/iphone15-pro-max-titan-xanh.webp';
                    } elseif (stripos($name, '15') !== false) {
                        $imgUrl = 'img/iphone15-hong.webp';
                    } elseif (stripos($name, '14 Pro') !== false) {
                        $imgUrl = 'img/iphone-14-pro-max.png';
                    } elseif (stripos($name, '14') !== false) {
                        $imgUrl = 'img/iphone14.webp';
                    } elseif (stripos($name, '13') !== false) {
                        $imgUrl = 'img/iphone13.webp';
                    } else {
                        $imgUrl = 'img/iphone-11.webp';
                    }
                }
                $p['code'] = $p['code'] ?? ('SP0' . str_pad($p['id'] ?? 1, 2, '0', STR_PAD_LEFT));
                $p['image_url'] = $imgUrl;
                $p['image'] = $imgUrl;
                $p['img'] = $imgUrl;
                $p['old'] = $p['old_price'] ?? $p['old'] ?? null;
                $p['isNew'] = isset($p['is_new']) ? (bool)$p['is_new'] : ($p['isNew'] ?? true);
                $p['price'] = (float)($p['price'] ?? 0);
                $p['cost'] = (float)($p['cost'] ?? 0);
                $p['stock'] = (int)($p['stock'] ?? 10);
                $p['rating'] = (float)($p['rating'] ?? 5.0);
                $p['sold'] = (int)($p['sold'] ?? 0);
                $name = $p['name'] ?? '';
                if (stripos($name, 'iPhone 17') !== false || stripos($name, 'iPhone Air') !== false) {
                    $p['category'] = 'iPhone 17 Series';
                } elseif (stripos($name, 'iPhone 16') !== false) {
                    $p['category'] = 'iPhone 16 Series';
                } elseif (stripos($name, 'iPhone 15') !== false) {
                    $p['category'] = 'iPhone 15 Series';
                } elseif (stripos($name, 'iPhone 14') !== false) {
                    $p['category'] = 'iPhone 14 Series';
                } else {
                    $p['category'] = 'iPhone 11 - 13';
                }
                $p['brand'] = 'Apple';
                $products[] = $p;
            }
            $dataSource = 'MySQL Central Database (VM 4)';
        } catch (Throwable $e) {
            $queryError = $e->getMessage();
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
        'db_error' => $queryError,
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
