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

function readCache($file) {
    $c = @file_get_contents($file);
    $d = json_decode($c ?: '[]', true);
    return is_array($d) ? $d : [];
}

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

function normalizeProductType($categoryOrName) {
    if (stripos($categoryOrName, 'samsung') !== false) return 'Samsung';
    if (stripos($categoryOrName, 'xiaomi') !== false || stripos($categoryOrName, 'redmi') !== false) return 'Xiaomi';
    return 'IP';
}

$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// 1. LẤY DANH SÁCH SẢN PHẨM (GET)
// ==========================================
if ($method === 'GET') {
    $products     = [];
    $dataSource   = 'Local JSON File';
    $queryError   = null;
    $loadedFromDb = false;

    if ($pdo !== null) {
        try {
            $sql = "SELECT p.id, p.name, p.type AS category, p.img, p.final_price AS price, p.price AS cost, p.isNew,
                           COALESCE(nhap.total_imported, 10) AS imported,
                           COALESCE(ban.total_sold, 0) AS sold
                    FROM products p
                    LEFT JOIN (
                        SELECT productId, SUM(soluong) AS total_imported
                        FROM phieu_nhap_chi_tiet
                        GROUP BY productId
                    ) nhap ON p.id = nhap.productId
                    LEFT JOIN (
                        SELECT productId, SUM(quantity) AS total_sold
                        FROM chi_tiet_don_hang
                        GROUP BY productId
                    ) ban ON p.id = ban.productId
                    WHERE p.status = 1
                    ORDER BY p.id DESC";
            $stmt = $pdo->query($sql);
            $rawProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rawProducts as $p) {
                $imgUrl = $p['img'] ?? '';
                $name   = $p['name'] ?? '';

                if (stripos($name, 'samsung') !== false || stripos($name, 'xiaomi') !== false ||
                    stripos($name, 'oppo') !== false || stripos($name, 'vivo') !== false ||
                    stripos($name, 'redmi') !== false) {
                    continue;
                }

                $p['brand'] = 'Apple';

                if (empty($imgUrl)) {
                    $imgUrl = '/img/iphone17.webp';
                } elseif (strpos($imgUrl, 'http') === 0) {
                    if (strpos($imgUrl, '100.72.145.103:9000') !== false) {
                        $parsed = parse_url($imgUrl);
                        $pathPart = $parsed['path'] ?? '';
                        if (strpos($pathPart, '/storage/') === 0) {
                            $imgUrl = $pathPart;
                        } elseif (strpos($pathPart, '/shop-assets/') === 0) {
                            $imgUrl = '/storage' . $pathPart;
                        } else {
                            $imgUrl = '/storage/' . ltrim($pathPart, '/');
                        }
                    }
                } elseif (strpos($imgUrl, 'storage/') === 0) {
                    $imgUrl = '/' . $imgUrl;
                } elseif (strpos($imgUrl, 'shop-assets/') === 0) {
                    $imgUrl = '/storage/' . $imgUrl;
                } elseif (strpos($imgUrl, '/shop-assets/') === 0) {
                    $imgUrl = '/storage' . $imgUrl;
                } elseif (strpos($imgUrl, '/') !== 0) {
                    $imgUrl = (strpos($imgUrl, 'img/') !== 0) ? ('/img/' . $imgUrl) : ('/' . $imgUrl);
                }

                $imported = (int)($p['imported'] ?? 10);
                $sold     = (int)($p['sold'] ?? 0);
                $stock    = max(0, $imported - $sold);

                $p['code']      = $p['id'];
                $p['image_url'] = $imgUrl;
                $p['image']     = $imgUrl;
                $p['img']       = $imgUrl;
                $p['old']       = null;
                $p['isNew']     = (bool)($p['isNew'] ?? false);
                $p['price']     = (float)($p['price'] ?? $p['cost'] ?? 0);
                $p['cost']      = (float)($p['cost'] ?? $p['price'] ?? 0);
                $p['imported']  = $imported;
                $p['sold']      = $sold;
                $p['stock']     = $stock;
                $p['rating']    = 5.0;

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
            writeCache($dataFile, $products);
        } catch (Throwable $e) {
            $queryError   = $e->getMessage();
            $loadedFromDb = false;
        }
    }

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
    $category = $input['category'] ?? 'iPhone 17 Series';
    $price    = (int)round((float)($input['price'] ?? 0));
    $cost     = (int)round((float)($input['cost'] ?? $price));
    $stock    = (int)($input['stock'] ?? 10);
    $img      = $input['img'] ?? $input['image_url'] ?? 'img/iphone17.webp';
    $dbType   = normalizeProductType($name . ' ' . $category);

    $products    = readCache($dataFile);
    $nextNum     = count($products) + 40;
    $defaultCode = 'IP' . str_pad((string)$nextNum, 4, '0', STR_PAD_LEFT);
    $newId       = !empty($input['code']) ? substr(trim($input['code']), 0, 10) : $defaultCode;
    $dbErrorMsg  = null;

    if ($pdo !== null) {
        try {
            $checkStmt = $pdo->query("SELECT id FROM products WHERE id LIKE 'IP%' ORDER BY LENGTH(id) DESC, id DESC LIMIT 1");
            $lastRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($lastRow && preg_match('/IP(\d+)/', $lastRow['id'], $m)) {
                $newId = 'IP' . str_pad((string)((int)$m[1] + 1), 4, '0', STR_PAD_LEFT);
            }

            $stmt = $pdo->prepare("INSERT INTO products (id, type, name, img, price, final_price, interest_rate, screen, ram, rom, isNew, refresh_rate, camera, status, monitor, vendor) VALUES (?, ?, ?, ?, ?, ?, 25, '6.3 inches', 8, 256, 1, '120', '[]', 1, 'Dynamic Island', 'NCC002')");
            $stmt->execute([$newId, $dbType, $name, ltrim($img, '/'), max(0, $cost), max(0, $price)]);
        } catch (Throwable $e) {
            $dbErrorMsg = $e->getMessage();
        }
    }

    $displayImg = (strpos($img, 'http') === 0 || strpos($img, '/') === 0) ? $img : ('/' . $img);
    $newProduct = [
        'id' => $newId, 'code' => $newId, 'name' => $name, 'brand' => $brand,
        'category' => $category, 'price' => (float)$price, 'cost' => (float)$cost, 'stock' => $stock,
        'rating' => 5.0, 'isNew' => true, 'imported' => $stock, 'sold' => 0,
        'img' => $displayImg, 'image' => $displayImg, 'image_url' => $displayImg,
    ];

    array_unshift($products, $newProduct);
    writeCache($dataFile, $products);

    echo json_encode([
        'status'   => 'success',
        'message'  => 'Thêm sản phẩm thành công',
        'db_error' => $dbErrorMsg,
        'product'  => $newProduct,
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

    $dbErrorMsg = null;
    if ($pdo !== null) {
        try {
            $dbType   = normalizeProductType(($input['name'] ?? '') . ' ' . ($input['category'] ?? ''));
            $priceVal = (int)round((float)($input['price'] ?? 0));
            $costVal  = isset($input['cost']) ? (int)round((float)$input['cost']) : $priceVal;
            $stmt = $pdo->prepare("UPDATE products SET name = ?, final_price = ?, price = ?, type = ? WHERE id = ?");
            $stmt->execute([$input['name'] ?? '', $priceVal, $costVal, $dbType, $input['id']]);
        } catch (Throwable $e) {
            $dbErrorMsg = $e->getMessage();
        }
    }

    $products = readCache($dataFile);
    foreach ($products as &$p) {
        if ((string)$p['id'] === (string)$input['id']) {
            if (isset($input['name']))     $p['name']     = $input['name'];
            if (isset($input['price']))    $p['price']    = (float)$input['price'];
            if (isset($input['cost']))     $p['cost']     = (float)$input['cost'];
            if (isset($input['stock']))    $p['stock']    = (int)$input['stock'];
            if (isset($input['category'])) $p['category'] = $input['category'];
        }
    }
    unset($p);
    writeCache($dataFile, $products);
    echo json_encode(['status' => 'success', 'db_error' => $dbErrorMsg]);
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
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
            } catch (Throwable $e) {
                // Nếu sản phẩm đã có trong đơn hàng/phiếu nhập (vướng ON DELETE RESTRICT), ẩn sản phẩm bằng status = 0
                try {
                    $softDel = $pdo->prepare("UPDATE products SET status = 0 WHERE id = ?");
                    $softDel->execute([$id]);
                } catch (Throwable $ignore) {}
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
