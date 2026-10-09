<?php
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();
$dataFile = __DIR__ . '/data/users.json';

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$phone = trim($input['phone'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($phone) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập đủ thông tin']);
    exit;
}

// 1. Kiểm tra trong MySQL trước
if ($pdo !== null) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE sdt = ? OR email = ? LIMIT 1");
        $stmt->execute([$phone, $phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Verify password (Cột mật khẩu trong phone_store là matKhau)
            if (password_verify($password, $user['matKhau']) || $password === $user['matKhau']) {
                // Ánh xạ lại các cột cho React SPA hiểu
                $mappedUser = [
                    'id' => $user['id'],
                    'name' => $user['hoTen'],
                    'hoTen' => $user['hoTen'],
                    'email' => $user['email'],
                    'phone' => $user['sdt'],
                    'role' => $user['role']
                ];
                echo json_encode(['status' => 'success', 'user' => $mappedUser], JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Sai mật khẩu']);
                exit;
            }
        }
    } catch (Throwable $e) {}
}

// 2. Nếu MySQL không có, kiểm tra trong file JSON dự phòng
if (file_exists($dataFile)) {
    $users = json_decode(file_get_contents($dataFile), true) ?: [];
    foreach ($users as $u) {
        if (($u['phone'] === $phone || $u['email'] === $phone)) {
            // Trong file JSON cũ không lưu pass, hoặc lưu dạng plain text
            $savedPass = $u['password'] ?? '123456'; 
            if ($password === $savedPass) {
                unset($u['password']);
                echo json_encode(['status' => 'success', 'user' => $u], JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Sai mật khẩu']);
                exit;
            }
        }
    }
}

echo json_encode(['status' => 'error', 'message' => 'Tài khoản không tồn tại']);
exit;
