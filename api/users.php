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
$dataFile = __DIR__ . '/data/users.json';

if (!file_exists($dataFile)) {
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0777, true);
    }
    file_put_contents($dataFile, '[]');
}

$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// 1. LẤY DANH SÁCH NGƯỜI DÙNG (GET)
// ==========================================
if ($method === 'GET') {
    $users = [];
    $dataSource = 'Local JSON File';

    if ($pdo !== null) {
        try {
            // Kết nối bảng users với bảng don_hang để đếm đúng số đơn hàng của từng khách
            $sql = "SELECT u.id, u.hoTen AS name, u.hoTen, u.email, u.sdt AS phone, u.diaChi AS address, u.diaChi, u.role, u.trangThai,
                           COUNT(d.id) AS orders
                    FROM users u
                    LEFT JOIN don_hang d ON u.id = d.customerId
                    GROUP BY u.id
                    ORDER BY u.id DESC";
            $stmt = $pdo->query($sql);
            $rawUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rawUsers as $u) {
                $st = $u['trangThai'] ?? '';
                $isLocked = ($st === 'Bị khoá' || $st === 'Bị khóa' || $st === 'Bi khoá');
                $users[] = [
                    'id'      => (string)$u['id'],
                    'name'    => $u['name'],
                    'hoTen'   => $u['hoTen'],
                    'email'   => $u['email'] ?? '',
                    'phone'   => $u['phone'] ?? '',
                    'sdt'     => $u['phone'] ?? '',
                    'address' => $u['address'] ?? $u['diaChi'] ?? '',
                    'diaChi'  => $u['diaChi'] ?? '',
                    'role'    => $u['role'] ?? 'customer',
                    'joined'  => '2025',
                    'orders'  => (int)($u['orders'] ?? 0),
                    'locked'  => $isLocked
                ];
            }
            $dataSource = 'MySQL Central Database (VM 4)';
        } catch (Throwable $e) {
            $pdo = null;
        }
    }

    if ($pdo === null) {
        $content = file_get_contents($dataFile);
        $rawUsers = json_decode($content, true) ?: [];
        foreach ($rawUsers as $u) {
            unset($u['password']);
            $users[] = $u;
        }
    }

    echo json_encode([
        'status' => 'success',
        'source' => $dataSource,
        'total'  => count($users),
        'data'   => $users
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================
// 2. ĐĂNG KÝ TÀI KHOẢN MỚI (POST)
// ==========================================
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    $nameVal  = trim($input['name'] ?? $input['hoTen'] ?? '');
    $reqPhone = substr(trim($input['phone'] ?? $input['sdt'] ?? ''), 0, 15);
    $reqEmail = substr(trim($input['email'] ?? ''), 0, 100);
    $rawPass  = trim((string)($input['password'] ?? $input['matKhau'] ?? '123456'));
    $address  = substr(trim($input['address'] ?? $input['diaChi'] ?? ''), 0, 255);

    if (!$input || (empty($nameVal) && empty($reqPhone) && empty($reqEmail))) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập đầy đủ thông tin đăng ký'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (empty($nameVal)) $nameVal = 'Khách hàng';

    $content = file_get_contents($dataFile);
    $users = json_decode($content, true) ?: [];

    // Dùng chung 1 ID BIGINT (13 chữ số tương tự dữ liệu mẫu 1715878800001) cho cả MySQL và JSON
    $newUserIdInt   = (string)(time() * 1000 + rand(100, 999));
    $hashedPassword = password_hash($rawPass, PASSWORD_DEFAULT);
    $dbEmail        = $reqEmail !== '' ? $reqEmail : null;
    $dbPhone        = $reqPhone !== '' ? $reqPhone : null;

    $savedToMySQL = false;
    $dbErrorMsg   = null;

    if ($pdo !== null) {
        try {
            // Kiểm tra trùng SĐT hoặc Email trong MySQL
            $conditions = [];
            $params     = [];
            if ($dbPhone !== null) {
                $conditions[] = "(sdt IS NOT NULL AND sdt != '' AND sdt = ?)";
                $params[]     = $dbPhone;
            }
            if ($dbEmail !== null) {
                $conditions[] = "(email IS NOT NULL AND email != '' AND email = ?)";
                $params[]     = $dbEmail;
            }
            if (!empty($conditions)) {
                $checkSql = "SELECT id FROM users WHERE " . implode(" OR ", $conditions) . " LIMIT 1";
                $checkStmt = $pdo->prepare($checkSql);
                $checkStmt->execute($params);
                if ($checkStmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'message' => 'Số điện thoại hoặc Email đã được đăng ký!'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO users (id, hoTen, sdt, diaChi, email, matKhau, trangThai, role) VALUES (?, ?, ?, ?, ?, ?, 'Hoạt động', 'customer')");
            $stmt->execute([
                $newUserIdInt,
                $nameVal,
                $dbPhone,
                $address ?: null,
                $dbEmail,
                $hashedPassword
            ]);
            $savedToMySQL = true;
        } catch (Throwable $e) {
            $savedToMySQL = false;
            $dbErrorMsg   = $e->getMessage();
        }
    }

    // Kiểm tra trùng trong JSON nếu đang chạy chế độ Fallback
    if (!$savedToMySQL) {
        foreach ($users as $u) {
            if (($reqPhone !== '' && ($u['phone'] ?? '') === $reqPhone) || ($reqEmail !== '' && ($u['email'] ?? '') === $reqEmail)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Số điện thoại hoặc Email đã được đăng ký!'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    $newUser = [
        'id'       => $newUserIdInt,
        'name'     => $nameVal,
        'hoTen'    => $nameVal,
        'email'    => $reqEmail,
        'phone'    => $reqPhone,
        'sdt'      => $reqPhone,
        'password' => $hashedPassword,
        'role'     => 'customer',
        'joined'   => date('d/m/Y'),
        'orders'   => 0,
        'locked'   => false
    ];

    array_unshift($users, $newUser);
    file_put_contents($dataFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $resUser = $newUser;
    unset($resUser['password']);

    echo json_encode([
        'status'   => 'success',
        'message'  => 'Đăng ký tài khoản thành công',
        'storage'  => $savedToMySQL ? 'MySQL + JSON Backup' : 'Local JSON File (Fallback)',
        'db_error' => $dbErrorMsg,
        'user'     => $resUser
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================
// 3. XÓA NGƯỜI DÙNG (DELETE)
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
                // Nếu user đã có đơn hàng (vướng fk_orders_customer ON DELETE RESTRICT), gỡ khóa ngoại về NULL trước khi xóa
                $unlink = $pdo->prepare("UPDATE don_hang SET customerId = NULL WHERE customerId = ?");
                $unlink->execute([(string)$id]);

                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([(string)$id]);
            } catch (Throwable $e) {}
        }
        $content = file_get_contents($dataFile);
        $users = json_decode($content, true) ?: [];
        $users = array_values(array_filter($users, function($u) use ($id) {
            return (string)$u['id'] !== (string)$id;
        }));
        file_put_contents($dataFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// ==========================================
// 4. CẬP NHẬT THÔNG TIN / KHÓA TÀI KHOẢN (PUT)
// ==========================================
if ($method === 'PUT') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if ($input && !empty($input['id'])) {
        $userId       = (string)$input['id'];
        $hasLockField = isset($input['locked']);
        $nameVal      = (isset($input['name']) || isset($input['hoTen'])) ? trim($input['name'] ?? $input['hoTen'] ?? '') : null;
        $phoneVal     = (isset($input['phone']) || isset($input['sdt'])) ? trim($input['phone'] ?? $input['sdt'] ?? '') : null;
        $emailVal     = isset($input['email']) ? trim($input['email']) : null;
        $addrVal      = (isset($input['address']) || isset($input['diaChi'])) ? trim($input['address'] ?? $input['diaChi'] ?? '') : null;

        if ($pdo !== null) {
            try {
                if ($hasLockField) {
                    $isLocked = !empty($input['locked']);
                    $newStatus = $isLocked ? 'Bị khoá' : 'Hoạt động';
                    $stmt = $pdo->prepare("UPDATE users SET trangThai = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $userId]);
                }
                $updates = [];
                $params  = [];
                if ($nameVal !== null)  { $updates[] = "hoTen = ?";  $params[] = $nameVal; }
                if ($phoneVal !== null) { $updates[] = "sdt = ?";    $params[] = $phoneVal; }
                if ($emailVal !== null) { $updates[] = "email = ?";  $params[] = $emailVal; }
                if ($addrVal !== null)  { $updates[] = "diaChi = ?"; $params[] = $addrVal; }

                if (!empty($updates)) {
                    $params[] = $userId;
                    $stmt = $pdo->prepare("UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?");
                    $stmt->execute($params);
                }
            } catch (Throwable $e) {}
        }

        $content = file_get_contents($dataFile);
        $users = json_decode($content, true) ?: [];
        foreach ($users as &$u) {
            if ((string)$u['id'] === $userId) {
                if ($hasLockField)       $u['locked']  = !empty($input['locked']);
                if ($nameVal !== null)  { $u['name']    = $nameVal; $u['hoTen'] = $nameVal; }
                if ($phoneVal !== null) { $u['phone']   = $phoneVal; $u['sdt'] = $phoneVal; }
                if ($emailVal !== null)   $u['email']   = $emailVal;
                if ($addrVal !== null)  { $u['address'] = $addrVal; $u['diaChi'] = $addrVal; }
            }
        }
        unset($u);
        file_put_contents($dataFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    echo json_encode(['status' => 'success']);
    exit;
}
