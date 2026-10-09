<?php
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

// ==========================================
// 1. LẤY DANH SÁCH SẢN PHẨM (GET)
// ==========================================
if ($method === 'GET') {
    $products = [];
    $dataSource = 'Local JSON File';
    $queryError = null;

    if ($pdo !== null) {
        try {
            // Ánh xạ cột từ DB của khách hàng sang định dạng React SPA mong muốn
            // DB có: type, name, img, price, final_price, ...
            // React cần: id, name, category, brand, img, price, stock
            $stmt = $pdo->query("SELECT id, name, type as category, 'Apple' as brand, img, final_price as price, 10 as stock, final_price as cost FROM products ORDER BY id DESC");
            $rawProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $products = [];

            foreach ($rawProducts as $p) {
                $imgUrl = $p['img'] ?? $p['image_url'] ?? $p['image'] ?? '';
                $name = $p['name'] ?? '';

                // Bổ sung dấu / ở đầu để đường dẫn tuyệt đối, không vỡ ảnh trong thư mục /admin/
                if (empty($imgUrl)) {
                    if (stripos($name, '17') !== false || stripos($name, 'Air') !== false) {
                        $imgUrl = '/img/iphone17.webp';
                    } elseif (stripos($name, '16 Pro') !== false) {
                        $imgUrl = '/img/iphone-16-pro-max.png';
                    } elseif (stripos($name, '16') !== false) {
                        $imgUrl = '/img/iphone-16-den-128.webp';
                    } elseif (stripos($name, '15 Pro') !== false) {
                        $imgUrl = '/img/iphone15-pro-max-titan-xanh.webp';
                    } elseif (stripos($name, '15') !== false) {
                        $imgUrl = '/img/iphone15-hong.webp';
                    } elseif (stripos($name, '14 Pro') !== false) {
                        $imgUrl = '/img/iphone-14-pro-max.png';
                    } elseif (stripos($name, '14') !== false) {
                        $imgUrl = '/img/iphone14.webp';
                    } elseif (stripos($name, '13') !== false) {
                        $imgUrl = '/img/iphone13.webp';
                    } else {
                        $imgUrl = '/img/iphone-11.webp';
                    }
                } elseif (strpos($imgUrl, 'http') !== 0 && strpos($imgUrl, '/') !== 0) {
                    $imgUrl = '/' . $imgUrl;
                }

                // Gán mã và ID tương thích với kiểu chuỗi VARCHAR (ví dụ: IP0030)
                $p['code'] = !empty($p['code']) ? $p['code'] : $p['id'];
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
                $p['brand'] = $p['brand'] ?? 'Apple';
                $products[] = $p;
            }

            $dataSource = 'MySQL Central Database (VM 4)';

            // Đồng bộ dữ liệu MySQL vào cache file JSON cục bộ
            @file_put_contents($dataFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            $queryError = $e->getMessage();
            $pdo = null;
        }
    }

    // Dự phòng: đọc từ JSON cục bộ nếu MySQL mất kết nối
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

// ==========================================
// 2. THÊM SẢN PHẨM MỚI (POST)
// ==========================================
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!$input || empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Tên sản phẩm không được trống'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $name = trim($input['name']);
    $brand = $input['brand'] ?? 'Apple';
    $category = $input['category'] ?? 'Điện thoại';
    $price = (float)($input['price'] ?? 0);
    $cost = (float)($input['cost'] ?? 0);
    $stock = (int)($input['stock'] ?? 10);
    $rating = 5.0;
    $isNew = 1;
    $imported = $stock;
    $sold = 0;
    $img = $input['img'] ?? $input['image_url'] ?? '/img/iphone17.webp';
    if (strpos($img, 'http') !== 0 && strpos($img, '/') !== 0) {
        $img = '/' . $img;
    }

    // Đọc cache JSON để xác định mã tăng tiếp theo
    $content = file_get_contents($dataFile);
    $products = json_decode($content, true) ?: [];

    // Tự sinh mã định dạng IPxxxx nếu người dùng không truyền mã
    $nextNum = count($products) + 1;
    $defaultCode = 'IP' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    $code = !empty($input['code']) ? $input['code'] : $defaultCode;
    $newId = $code;

    // Ghi trực tiếp vào MySQL nếu có kết nối
    if ($pdo !== null) {
        try {
            // Truy vấn lấy ID lớn nhất hiện tại để tránh trùng khóa chính
            $checkStmt = $pdo->query("SELECT id FROM products WHERE id LIKE 'IP%' ORDER BY id DESC LIMIT 1");
            $lastRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($lastRow && preg_match('/IP(\d+)/', $lastRow['id'], $m)) {
                $code = 'IP' . str_pad((int)$m[1] + 1, 4, '0', STR_PAD_LEFT);
                $newId = $code;
            }

            // Ép vào các cột thực tế của bảng products
            $stmt = $pdo->prepare("INSERT INTO products (id, type, name, img, price, final_price, isNew, status, screen, ram, rom, camera) VALUES (?, ?, ?, ?, ?, ?, 1, 1, '-', 8, 128, '{}')");
            $stmt->execute([$newId, 'IPHONE', $name, $img, $cost, $price]);
        } catch (Throwable $e) {
            // Bỏ qua nếu lỗi
            error_log('Lỗi insert product: ' . $e->getMessage());
        }
    }

    $newProduct = [
        'id' => $newId,
        'code' => $code,
        'name' => $name,
        'brand' => $brand,
        'category' => $category,
        'price' => $price,
        'cost' => $cost,
        'stock' => $stock,
        'rating' => $rating,
        'isNew' => true,
        'imported' => $imported,
        'sold' => $sold,
        'img' => $img,
        'image' => $img,
        'image_url' => $img
    ];

    array_unshift($products, $newProduct);
    file_put_contents($dataFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'status' => 'success',
        'message' => 'Thêm sản phẩm thành công',
        'product' => $newProduct
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================
// 3. CẬP NHẬT SẢN PHẨM (PUT)
// ==========================================
if ($method === 'PUT') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input || empty($input['id'])) {
        http_response_code(400); echo json_encode(['status' => 'error']); exit;
    }
    
    // Update MySQL
    if ($pdo !== null) {
        try {
            // DB khách hàng có name, final_price. Category sẽ map tạm vào cột type.
            $stmt = $pdo->prepare("UPDATE products SET name=?, final_price=?, type=? WHERE id=?");
            $stmt->execute([$input['name'] ?? '', $input['price'] ?? 0, $input['category'] ?? 'IPHONE', $input['id']]);
        } catch (Throwable $e) {}
    }

    // Update JSON
    $content = file_get_contents($dataFile);
    $products = json_decode($content, true) ?: [];
    foreach ($products as &$p) {
        if ((string)$p['id'] === (string)$input['id']) {
            if (isset($input['name'])) $p['name'] = $input['name'];
            if (isset($input['price'])) $p['price'] = (float)$input['price'];
            if (isset($input['stock'])) $p['stock'] = (int)$input['stock'];
            if (isset($input['category'])) $p['category'] = $input['category'];
        }
    }
    file_put_contents($dataFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['status' => 'success']);
    exit;
}

// ==========================================
// 4. XÓA SẢN PHẨM (DELETE)
// ==========================================
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $id = $input['id'] ?? '';
    }
    
    if ($id) {
        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
                $stmt->execute([$id]);
            } catch (Throwable $e) {}
        }
        $content = file_get_contents($dataFile);
        $products = json_decode($content, true) ?: [];
        $products = array_values(array_filter($products, function($p) use ($id) { return (string)$p['id'] !== (string)$id; }));
        file_put_contents($dataFile, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    echo json_encode(['status' => 'success']);
    exit;
}