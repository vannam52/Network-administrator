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
$dataFile = __DIR__ . '/data/users.json';

if (!file_exists($dataFile)) {
    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0777, true);
    }
    file_put_contents($dataFile, '[]');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $users = [];
    $dataSource = 'Local JSON File';

    if ($pdo !== null) {
        try {
            $stmt = $pdo->query("SELECT id, hoTen as name, hoTen, email, sdt as phone, NULL as joined, 0 as orders, 0 as locked FROM users ORDER BY id DESC");
            $users = $stmt->fetchAll();
            $dataSource = 'MySQL Central Database (VM 4)';
        } catch (Throwable $e) {
            $pdo = null;
        }
    }

    if ($pdo === null) {
        $content = file_get_contents($dataFile);
        $users = json_decode($content, true) ?: [];
    }

    echo json_encode([
        'status' => 'success',
        'source' => $dataSource,
        'total' => count($users),
        'data' => $users
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!$input || empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ']);
        exit;
    }

    $content = file_get_contents($dataFile);
    $users = json_decode($content, true) ?: [];

    $reqPhone = trim($input['phone'] ?? '');
    $reqEmail = trim($input['email'] ?? '');

    // Kiểm tra trùng lặp (nếu đã có trong JSON)
    foreach ($users as $u) {
        if (($reqPhone && $u['phone'] === $reqPhone) || ($reqEmail && $u['email'] === $reqEmail)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Số điện thoại hoặc Email đã được đăng ký!']);
            exit;
        }
    }

    $newId = 'KH' . str_pad((string)(count($users) + 1), 3, '0', STR_PAD_LEFT);
    $nameVal = trim($input['name'] ?? $input['hoTen'] ?? 'Khách hàng');
    $newUser = [
        'id' => $newId,
        'name' => $nameVal,
        'hoTen' => $nameVal, // Bổ sung cho React SPA
        'email' => $reqEmail,
        'phone' => $reqPhone,
        'joined' => date('d/m/Y'),
        'orders' => 0,
        'locked' => false
    ];

    $savedToMySQL = false;
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (hoTen, email, sdt, matKhau, role) VALUES (?, ?, ?, ?, 'customer')");
            
            // Xử lý email rỗng để không bị lỗi UNIQUE của MySQL
            $safeEmail = trim($newUser['email']);
            if (empty($safeEmail)) {
                $safeEmail = 'kh_' . uniqid() . '@noemail.com';
            }

            $stmt->execute([
                $newUser['name'],
                $safeEmail,
                $newUser['phone'],
                password_hash($input['password'] ?? '123456', PASSWORD_DEFAULT)
            ]);
            $savedToMySQL = true;
        } catch (Throwable $e) {
            $savedToMySQL = false;
        }
    }

    array_unshift($users, $newUser);
    file_put_contents($dataFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'status' => 'success',
        'message' => 'Đăng ký tài khoản thành công',
        'storage' => $savedToMySQL ? 'MySQL + JSON Backup' : 'Local JSON File',
        'user' => $newUser
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
                $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
                $stmt->execute([$id]);
            } catch (Throwable $e) {}
        }
        $content = file_get_contents($dataFile);
        $users = json_decode($content, true) ?: [];
        $users = array_values(array_filter($users, function($u) use ($id) { return (string)$u['id'] !== (string)$id; }));
        file_put_contents($dataFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    echo json_encode(['status' => 'success']);
    exit;
}

// ==========================================
// 4. KHÓA TÀI KHOẢN (PUT)
// ==========================================
if ($method === 'PUT') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if ($input && !empty($input['id'])) {
        if ($pdo !== null) {
            try {
                // Đảo trạng thái khóa (1 là khóa, 0 là mở)
                $stmt = $pdo->prepare("UPDATE users SET role=? WHERE id=?");
                // Mượn cột role để demo (hoặc tạo thêm cột locked nếu cần)
                $newRole = (isset($input['locked']) && $input['locked']) ? 'locked' : 'customer';
                $stmt->execute([$newRole, $input['id']]);
            } catch (Throwable $e) {}
        }
        $content = file_get_contents($dataFile);
        $users = json_decode($content, true) ?: [];
        foreach ($users as &$u) {
            if ((string)$u['id'] === (string)$input['id']) {
                if (isset($input['locked'])) $u['locked'] = $input['locked'];
            }
        }
        file_put_contents($dataFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    echo json_encode(['status' => 'success']);
    exit;
}
