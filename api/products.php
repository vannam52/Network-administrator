<?php
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo      = getDatabaseConnection();
$dataDir  = __DIR__ . '/data';
$dataFile = $dataDir . '/products.json';

if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0777, true);
}
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, '[]');
}

/* ---------- Helpers cache JSON ---------- */

function readCache($file) {
    $c = @file_get_contents($file);
    $d = json_decode($c ?: '[]', true);
    return is_array($d) ? $d : [];
}

// Chỉ ghi khi nội dung thật sự thay đổi, ghi qua file tạm để tránh file hỏng khi nhiều request cùng lúc
function writeCache($file, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (@file_get_contents($file) === $json) {
        return;
    }
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) !== false) {
        if (!@rename($tmp, $file)) {
            @file_put_contents($file, $json, LOCK_EX);
            @unlink($tmp);
        }
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// 1. LẤY DANH SÁCH SẢN PHẨM (GET)
// ==========================================
if ($method === 'GET') {
    $products    = [];
    $dataSource  = 'Local JSON File';
    $queryError  = null;
    $loadedFromDb = false;

    if ($pdo !== null) {
        try {
            $stmt = $pdo->query("SELECT id, name, type as category, img, final_price as price, 10 as stock, final_price as cost FROM products ORDER BY id DESC");
            $rawProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rawProducts as $p) {
                $imgUrl = $p['img'] ?? $p['image_url'] ?? $p['image'] ?? '';
                $name   = $p['name'] ?? '';

                // Bỏ qua sản phẩm không phải Apple
                if (stripos($name, 'samsung') !== false || stripos($name, 'xiaomi') !== false ||
                    stripos($name, 'oppo') !== false || stripos($name, 'vivo') !== false ||
                    stripos($name, 'redmi') !== false) {
                    continue;
                }

                $p['brand'] = 'Apple';

                // Chuẩn hoá đường dẫn ảnh về dạng tuyệt đối (/img/...)
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
                        $shortName = urlencode(substr($name, 0, 15));
                        $imgUrl = "https://placehold.co/400x400/f1f5f9/475569?text=" . $shortName;
                    }
                } elseif (strpos($imgUrl, 'http') === 0) {
                    $parsed = parse_url($imgUrl);
                    if (isset($parsed['path'])) {
                        $imgUrl = $parsed['path'];
                    }
                } elseif (strpos($imgUrl, '/') !== 0) {
                    if (strpos($imgUrl, 'img/') !== 0) {
                        $imgUrl = '/img/' . $imgUrl;
                    } else {
                        $imgUrl = '/' . $imgUrl;
                    }
                }

                $p['code']      = !empty($p['code']) ? $p['code'] : $p['id'];
                $p['image_url'] = $imgUrl;
                $p['image']     = $imgUrl;
                $p['img']       = $imgUrl;
                $p['old']       = $p['old_price'] ?? $p['old'] ?? null;
                $p['isNew']     = isset($p['is_new']) ? (bool)$p['is_new'] : ($p['isNew'] ?? true);
                $p['price']     = (float)($p['price'] ?? 0);
                $p['cost']      = (float)($p['cost'] ?? 0);
                $p['stock']     = (int)($p['stock'] ?? 10);
                $p['rating']    = (float)($p['rating'] ?? 5.0);
                $p['sold']      = (int)($p['sold'] ?? 0);

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
                $products[] = $p;
            }

            $dataSource   = 'MySQL Central Database (VM 4)';
            $loadedFromDb = true;

            // Đồng bộ cache JSON (chỉ ghi khi có thay đổi)
            writeCache($dataFile, $products);
        } catch (Throwable $e) {
            $queryError = $e->getMessage();
            $loadedFromDb = false;
        }
    }

    // Dự phòng: đọc từ JSON cục bộ nếu MySQL không dùng được
    if (!$loadedFromDb) {
        $products = readCache($dataFile);
        if ($queryError === null) {
            $queryError = $GLOBALS['dbError'] ?? null;
        }
    }

    echo json_encode([
        'status'   => 'success',
        'source'   => $dataSource,
        'db_error' => $queryError,
        'server'   => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown Web Server',
        'total'    => count($products),
        'data'     => $products,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================
// 2. THÊM SẢN PHẨM MỚI (POST)
// ==========================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Tên sản phẩm không được trống'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $name     = trim($input['name']);
    $brand    = $input['brand'] ?? 'Apple';
    $category = $input['category'] ?? 'Điện thoại';
    $price    = (float)($input['price'] ?? 0);
    $cost     = (float)($input['cost'] ?? 0);
    $stock    = (int)($input['stock'] ?? 10);
    $rating   = 5.0;
    $imported = $stock;
    $sold     = 0;
    $img      = $input['img'] ?? $input['image_url'] ?? '/img/iphone17.webp';
    if (strpos($img, 'http') !== 0 && strpos($img, '/') !== 0) {
        $img = '/' . $img;
    }

    $products   = readCache($dataFile);
    $nextNum    = count($products) + 1;
    $defaultCode = 'IP' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    $code  = !empty($input['code']) ? $input['code'] : $defaultCode;
    $newId = $code;

    if ($pdo !== null) {
        try {
            $checkStmt = $pdo->query("SELECT id FROM products WHERE id LIKE 'IP%' ORDER BY id DESC LIMIT 1");
            $lastRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($lastRow && preg_match('/IP(\d+)/', $lastRow['id'], $m)) {
                $code  = 'IP' . str_pad((int)$m[1] + 1, 4, '0', STR_PAD_LEFT);
                $newId = $code;
            }

            $stmt = $pdo->prepare("INSERT INTO products (id, type, name, img, price, final_price, isNew, status, screen, ram, rom, camera) VALUES (?, ?, ?, ?, ?, ?, 1, 1, '-', 8, 128, '{}')");
            $stmt->execute([$newId, 'IPHONE', $name, $img, $cost, $price]);
        } catch (Throwable $e) {
            error_log('Lỗi insert product: ' . $e->getMessage());
        }
    }

    $newProduct = [
        'id' => $newId, 'code' => $code, 'name' => $name, 'brand' => $brand,
        'category' => $category, 'price' => $price, 'cost' => $cost, 'stock' => $stock,
        'rating' => $rating, 'isNew' => true, 'imported' => $imported, 'sold' => $sold,
        'img' => $img, 'image' => $img, 'image_url' => $img,
    ];

    array_unshift($products, $newProduct);
    writeCache($dataFile, $products);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Thêm sản phẩm thành công',
        'product' => $newProduct,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================
// 3. CẬP NHẬT SẢN PHẨM (PUT)
// ==========================================
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error']);
        exit;
    }

    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET name=?, final_price=?, type=? WHERE id=?");
            $stmt->execute([$input['name'] ?? '', $input['price'] ?? 0, $input['category'] ?? 'IPHONE', $input['id']]);
        } catch (Throwable $e) {
            error_log('Lỗi update product: ' . $e->getMessage());
        }
    }

    $products = readCache($dataFile);
    foreach ($products as &$p) {
        if ((string)$p['id'] === (string)$input['id']) {
            if (isset($input['name']))     $p['name']     = $input['name'];
            if (isset($input['price']))    $p['price']    = (float)$input['price'];
            if (isset($input['stock']))    $p['stock']    = (int)$input['stock'];
            if (isset($input['category'])) $p['category'] = $input['category'];
        }
    }
    unset($p);
    writeCache($dataFile, $products);
    echo json_encode(['status' => 'success']);
    exit;
}

// ==========================================
// 4. XÓA SẢN PHẨM (DELETE)
// ==========================================
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? '';
    }

    if ($id) {
        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
                $stmt->execute([$id]);
            } catch (Throwable $e) {
                error_log('Lỗi delete product: ' . $e->getMessage());
            }
        }
        $products = readCache($dataFile);
        $products = array_values(array_filter($products, function ($p) use ($id) {
            return (string)$p['id'] !== (string)$id;
        }));
        writeCache($dataFile, $products);
    }
    echo json_encode(['status' => 'success']);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);