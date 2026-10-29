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
$dataFile = __DIR__ . '/data/orders.json';

if (!file_exists($dataFile)) {
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0777, true);
    }
    file_put_contents($dataFile, '[]');
}

function mapStatusToVN($status) {
    $map = [
        'new'       => 'Mới đặt',
        'processed' => 'Đã xác nhận',
        'shipped'   => 'Đang giao',
        'cancelled' => 'Đã hủy'
    ];
    return $map[$status] ?? $status;
}

function mapStatusToDB($status) {
    $map = [
        'Mới đặt'     => 'new',
        'Đã xác nhận' => 'processed',
        'Đang xử lý'  => 'processed',
        'Đang giao'   => 'shipped',
        'Đã giao'     => 'shipped',
        'Hoàn thành'  => 'shipped',
        'Đã hủy'      => 'cancelled'
    ];
    return substr($map[$status] ?? $status, 0, 20);
}

$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// 1. LẤY DANH SÁCH ĐƠN HÀNG TỪ don_hang & chi_tiet_don_hang (GET)
// ==========================================
if ($method === 'GET') {
    $orders = [];
    $dataSource = 'Local JSON File';
    $dbErrorMsg = null;

    if ($pdo !== null) {
        try {
            $sql = "SELECT d.id, d.customerId, u.hoTen AS customerName, d.ngay, d.address, d.sdt, d.paymentMethod, d.total, d.status
                    FROM don_hang d
                    LEFT JOIN users u ON d.customerId = u.id
                    ORDER BY d.ngay DESC, LENGTH(d.id) DESC, d.id DESC";
            $stmt = $pdo->query($sql);
            $rawOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $detailSql = "SELECT ct.donHangId, ct.productId AS id, ct.quantity, p.name, p.final_price AS price, p.img
                          FROM chi_tiet_don_hang ct
                          LEFT JOIN products p ON ct.productId = p.id";
            $detailStmt = $pdo->query($detailSql);
            $allDetails = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

            $itemsByOrder = [];
            foreach ($allDetails as $dt) {
                $dhId = $dt['donHangId'];
                $imgUrl = $dt['img'] ?? '';
                if ($imgUrl && strpos($imgUrl, 'http') !== 0 && strpos($imgUrl, '/') !== 0) {
                    $imgUrl = '/' . $imgUrl;
                }
                $itemsByOrder[$dhId][] = [
                    'id'        => $dt['id'],
                    'productId' => $dt['id'],
                    'name'      => $dt['name'] ?? ('Sản phẩm ' . $dt['id']),
                    'price'     => (float)($dt['price'] ?? 0),
                    'quantity'  => (int)($dt['quantity'] ?? 1),
                    'qty'       => (int)($dt['quantity'] ?? 1),
                    'img'       => $imgUrl
                ];
            }

            foreach ($rawOrders as $o) {
                $oid = $o['id'];
                $orders[] = [
                    'id'          => $oid,
                    'customerId'  => $o['customerId'] ? (string)$o['customerId'] : null,
                    'customer'    => $o['customerName'] ?: 'Khách hàng',
                    'phone'       => $o['sdt'] ?? '',
                    'address'     => $o['address'] ?? '',
                    'date'        => $o['ngay'] ? date('d/m/Y', strtotime($o['ngay'])) : date('d/m/Y'),
                    'ngay'        => $o['ngay'],
                    'total'       => (float)($o['total'] ?? 0),
                    'status'      => mapStatusToVN($o['status'] ?? 'new'),
                    'rawStatus'   => $o['status'] ?? 'new',
                    'pay'         => $o['paymentMethod'] ?? 'tiền mặt',
                    'processedBy' => $_SERVER['SERVER_SOFTWARE'] ?? 'Web Server',
                    'items'       => $itemsByOrder[$oid] ?? []
                ];
            }

            $dataSource = 'MySQL Central Database (VM 4)';
            file_put_contents($dataFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            $dbErrorMsg = $e->getMessage();
            $pdo = null;
        }
    }

    if ($pdo === null) {
        $content = file_get_contents($dataFile);
        $orders = json_decode($content, true) ?: [];
    }

    echo json_encode([
        'status'   => 'success',
        'source'   => $dataSource,
        'db_error' => $dbErrorMsg,
        'server'   => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown Web Server',
        'total'    => count($orders),
        'data'     => $orders
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================
// 2. TẠO ĐƠN HÀNG MỚI VÀO don_hang & chi_tiet_don_hang (POST)
// ==========================================
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $customerName = trim($input['customer'] ?? $input['name'] ?? $input['hoTen'] ?? 'Khách vãng lai');
    $phone        = substr(trim($input['phone'] ?? $input['sdt'] ?? ''), 0, 15);
    $address      = substr(trim($input['address'] ?? $input['diaChi'] ?? ''), 0, 255);
    $pay          = substr(trim($input['pay'] ?? $input['paymentMethod'] ?? 'tiền mặt'), 0, 30);
    $total        = (int)round((float)($input['total'] ?? $input['tongTien'] ?? 0));
    $items        = $input['items'] ?? $input['cart'] ?? [];
    $customerId   = !empty($input['customerId']) ? trim((string)$input['customerId']) : (!empty($input['userId']) ? trim((string)$input['userId']) : null);

    // Sinh mã đơn <= 10 ký tự khớp với VARCHAR(10) của bảng don_hang
    $newId = '#DH' . rand(100, 999);
    $savedToMySQL = false;
    $dbErrorMsg   = null;

    if ($pdo !== null) {
        try {
            $pdo->beginTransaction();

            $maxStmt = $pdo->query("SELECT id FROM don_hang WHERE id LIKE '#DH%' ORDER BY LENGTH(id) DESC, id DESC LIMIT 1");
            $lastRow = $maxStmt->fetch(PDO::FETCH_ASSOC);
            if ($lastRow && preg_match('/#DH(\d+)/', $lastRow['id'], $m)) {
                $nextNum = (int)$m[1] + 1;
                $newId = '#DH' . str_pad((string)$nextNum, 3, '0', STR_PAD_LEFT);
            }
            $chkId = $pdo->prepare("SELECT id FROM don_hang WHERE id = ?");
            $chkId->execute([$newId]);
            if ($chkId->fetch()) {
                $newId = '#DH' . substr((string)time(), -6);
            }

            // Tìm customerId hợp lệ trong bảng users
            $validCustomerId = null;
            if ($customerId) {
                $uStmt = $pdo->prepare("SELECT id, hoTen FROM users WHERE id = ? LIMIT 1");
                $uStmt->execute([$customerId]);
                if ($uRow = $uStmt->fetch(PDO::FETCH_ASSOC)) {
                    $validCustomerId = $uRow['id'];
                    if ($customerName === 'Khách vãng lai') $customerName = $uRow['hoTen'];
                }
            }
            if (!$validCustomerId && $phone !== '') {
                $uStmt = $pdo->prepare("SELECT id, hoTen FROM users WHERE sdt = ? LIMIT 1");
                $uStmt->execute([$phone]);
                if ($uRow = $uStmt->fetch(PDO::FETCH_ASSOC)) {
                    $validCustomerId = $uRow['id'];
                    if ($customerName === 'Khách vãng lai') $customerName = $uRow['hoTen'];
                }
            }

            // 2b. Kiểm tra số lượng tồn kho thực tế trong CSDL
            if (is_array($items) && count($items) > 0) {
                $checkStock = $pdo->prepare("
                    SELECT p.id, p.name,
                           (COALESCE(nhap.total_imported, 10) - COALESCE(ban.total_sold, 0)) AS current_stock
                    FROM products p
                    LEFT JOIN (SELECT productId, SUM(soluong) AS total_imported FROM phieu_nhap_chi_tiet GROUP BY productId) nhap ON p.id = nhap.productId
                    LEFT JOIN (SELECT productId, SUM(quantity) AS total_sold FROM chi_tiet_don_hang GROUP BY productId) ban ON p.id = ban.productId
                    WHERE p.id = ? LIMIT 1
                ");

                foreach ($items as $item) {
                    $prodId = $item['id'] ?? $item['productId'] ?? $item['code'] ?? '';
                    $qty    = (int)($item['quantity'] ?? $item['qty'] ?? 1);
                    if ($qty <= 0) $qty = 1;

                    if ($prodId !== '') {
                        $checkStock->execute([$prodId]);
                        if ($stockRow = $checkStock->fetch(PDO::FETCH_ASSOC)) {
                            $avail = max(0, (int)$stockRow['current_stock']);
                            if ($avail < $qty) {
                                $pdo->rollBack();
                                http_response_code(400);
                                echo json_encode([
                                    'status' => 'error',
                                    'message' => "Sản phẩm '{$stockRow['name']}' không đủ số lượng trong kho (chỉ còn {$avail} sản phẩm, bạn đặt {$qty})!"
                                ], JSON_UNESCAPED_UNICODE);
                                exit;
                            }
                        }
                    }
                }
            }

            $insertOrder = $pdo->prepare("INSERT INTO don_hang (id, customerId, ngay, address, sdt, paymentMethod, total, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'new')");
            $insertOrder->execute([
                $newId,
                $validCustomerId,
                date('Y-m-d'),
                $address,
                $phone,
                $pay,
                max(0, $total)
            ]);

            if (is_array($items) && count($items) > 0) {
                $insertDetail = $pdo->prepare("INSERT INTO chi_tiet_don_hang (donHangId, productId, quantity) VALUES (?, ?, ?)");
                $checkProd    = $pdo->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");

                foreach ($items as $item) {
                    $prodId = $item['id'] ?? $item['productId'] ?? $item['code'] ?? '';
                    $qty    = (int)($item['quantity'] ?? $item['qty'] ?? 1);
                    if ($qty <= 0) $qty = 1;

                    if ($prodId !== '') {
                        $checkProd->execute([$prodId]);
                        if ($checkProd->fetch()) {
                            $insertDetail->execute([$newId, $prodId, $qty]);
                        }
                    }
                }
            }

            $pdo->commit();
            $savedToMySQL = true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $savedToMySQL = false;
            $dbErrorMsg   = $e->getMessage();
        }
    } else {
        // Kiểm tra tồn kho từ JSON file khi MySQL offline
        $prodFile = __DIR__ . '/data/products.json';
        if (file_exists($prodFile) && is_array($items) && count($items) > 0) {
            $prods = json_decode(file_get_contents($prodFile), true) ?: [];
            foreach ($items as $item) {
                $prodId = $item['id'] ?? $item['productId'] ?? $item['code'] ?? '';
                $qty    = (int)($item['quantity'] ?? $item['qty'] ?? 1);
                if ($qty <= 0) $qty = 1;
                foreach ($prods as $p) {
                    if ((string)($p['id'] ?? '') === (string)$prodId || (string)($p['code'] ?? '') === (string)$prodId) {
                        $avail = max(0, (int)($p['stock'] ?? 10));
                        if ($avail < $qty) {
                            http_response_code(400);
                            echo json_encode([
                                'status' => 'error',
                                'message' => "Sản phẩm '{$p['name']}' không đủ số lượng trong kho (chỉ còn {$avail} sản phẩm, bạn đặt {$qty})!"
                            ], JSON_UNESCAPED_UNICODE);
                            exit;
                        }
                    }
                }
            }
        }
    }

    $newOrder = [
        'id'          => $newId,
        'customerId'  => $validCustomerId ?? $customerId,
        'customer'    => $customerName,
        'phone'       => $phone,
        'address'     => $address,
        'date'        => date('d/m/Y'),
        'ngay'        => date('Y-m-d'),
        'total'       => (float)$total,
        'status'      => 'Mới đặt',
        'rawStatus'   => 'new',
        'pay'         => $pay,
        'items'       => $items,
        'processedBy' => $_SERVER['SERVER_SOFTWARE'] ?? 'Web Server'
    ];

    $content = file_get_contents($dataFile);
    $orders = json_decode($content, true) ?: [];
    array_unshift($orders, $newOrder);
    file_put_contents($dataFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Đồng bộ trừ tồn kho và tăng số lượng bán trong file cache products.json
    $prodFile = __DIR__ . '/data/products.json';
    if (file_exists($prodFile) && is_array($items) && count($items) > 0) {
        $prods = json_decode(file_get_contents($prodFile), true) ?: [];
        $changed = false;
        foreach ($items as $item) {
            $prodId = $item['id'] ?? $item['productId'] ?? $item['code'] ?? '';
            $qty    = (int)($item['quantity'] ?? $item['qty'] ?? 1);
            if ($qty <= 0) $qty = 1;
            foreach ($prods as &$p) {
                if ((string)($p['id'] ?? '') === (string)$prodId || (string)($p['code'] ?? '') === (string)$prodId) {
                    $p['sold']  = (int)($p['sold'] ?? 0) + $qty;
                    $p['stock'] = max(0, (int)($p['stock'] ?? 10) - $qty);
                    $changed = true;
                }
            }
            unset($p);
        }
        if ($changed) {
            file_put_contents($prodFile, json_encode($prods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    echo json_encode([
        'status'   => 'success',
        'message'  => 'Đặt hàng thành công và đã lưu trữ',
        'storage'  => $savedToMySQL ? 'MySQL (VM 4 - Thành Phát) + JSON Backup' : 'Local JSON File (Fallback)',
        'db_error' => $dbErrorMsg,
        'order'    => $newOrder
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================
// 3. XÓA ĐƠN HÀNG (DELETE)
// ==========================================
if ($method === 'DELETE') {
    $orderId = $_GET['id'] ?? '';
    if (!$orderId) {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        $orderId = $input['id'] ?? '';
    }
    $cleanId = ltrim((string)$orderId, '#');
    $hashId  = '#' . $cleanId;

    if ($cleanId) {
        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare("DELETE FROM don_hang WHERE id = ? OR id = ?");
                $stmt->execute([$hashId, $cleanId]);
            } catch (Throwable $e) {}
        }
        $content = file_get_contents($dataFile);
        $orders = json_decode($content, true) ?: [];
        $orders = array_values(array_filter($orders, function($o) use ($cleanId) {
            return ltrim((string)$o['id'], '#') !== $cleanId;
        }));
        file_put_contents($dataFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    echo json_encode(['status' => 'success', 'message' => "Đã xóa đơn hàng {$hashId}"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================
// 4. CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG (PUT)
// ==========================================
if ($method === 'PUT') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if ($input && !empty($input['id'])) {
        $cleanId   = ltrim((string)$input['id'], '#');
        $hashId    = '#' . $cleanId;
        $rawStatus = $input['status'] ?? 'Đang giao';
        $dbStatus  = mapStatusToDB($rawStatus);

        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare("UPDATE don_hang SET status = ? WHERE id = ? OR id = ?");
                $stmt->execute([$dbStatus, $hashId, $cleanId]);
            } catch (Throwable $e) {}
        }

        $content = file_get_contents($dataFile);
        $orders = json_decode($content, true) ?: [];
        foreach ($orders as &$o) {
            if (ltrim((string)$o['id'], '#') === $cleanId) {
                $o['status']    = mapStatusToVN($dbStatus);
                $o['rawStatus'] = $dbStatus;
            }
        }
        unset($o);
        file_put_contents($dataFile, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    echo json_encode(['status' => 'success']);
    exit;
}
