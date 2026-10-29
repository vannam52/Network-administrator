<?php
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();
$dataFile = __DIR__ . '/data/users.json';

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// Nhận linh hoạt SĐT, Email hoặc Tên đăng nhập từ React
$identifier = trim($input['phone'] ?? $input['email'] ?? $input['sdt'] ?? $input['username'] ?? $input['account'] ?? '');
$password   = trim((string)($input['password'] ?? $input['matKhau'] ?? ''));

if (empty($identifier) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập đủ thông tin'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Kiểm tra trong MySQL trước
if ($pdo !== null) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE sdt = ? OR email = ? OR hoTen = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Chặn tài khoản bị khóa (khớp cả 'Bị khoá' trong SQL lẫn 'Bi khoá' hoặc 'Bị khóa')
            $status = $user['trangThai'] ?? '';
            if ($status === 'Bị khoá' || $status === 'Bị khóa' || $status === 'Bi khoá') {
                echo json_encode(['status' => 'error', 'message' => 'Tài khoản của bạn đã bị khóa'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $dbPass = (string)($user['matKhau'] ?? '');
            if (password_verify($password, $dbPass) || $password === $dbPass) {
                $mappedUser = [
                    'id'    => (string)$user['id'],
                    'name'  => $user['hoTen'],
                    'hoTen' => $user['hoTen'],
                    'email' => $user['email'],
                    'phone' => $user['sdt'],
                    'sdt'   => $user['sdt'],
                    'role'  => $user['role'] ?? 'customer'
                ];
                echo json_encode(['status' => 'success', 'user' => $mappedUser], JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Sai mật khẩu'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } catch (Throwable $e) {}
}

// 2. Nếu không có trong MySQL, kiểm tra trong file JSON dự phòng
if (file_exists($dataFile)) {
    $users = json_decode(file_get_contents($dataFile), true) ?: [];
    foreach ($users as $u) {
        $uPhone = $u['phone'] ?? $u['sdt'] ?? '';
        $uEmail = $u['email'] ?? '';
        $uName  = $u['name'] ?? $u['hoTen'] ?? '';

        if (($uPhone !== '' && $uPhone === $identifier) || 
            ($uEmail !== '' && strcasecmp($uEmail, $identifier) === 0) ||
            ($uName !== '' && strcasecmp($uName, $identifier) === 0)) {
            if (!empty($u['locked'])) {
                echo json_encode(['status' => 'error', 'message' => 'Tài khoản của bạn đã bị khóa'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $savedPass = (string)($u['password'] ?? '123456');
            if (password_verify($password, $savedPass) || $password === $savedPass) {
                unset($u['password']);
                $u['id']    = (string)$u['id'];
                $u['name']  = $u['name'] ?? $u['hoTen'] ?? 'Khách hàng';
                $u['hoTen'] = $u['hoTen'] ?? $u['name'];
                $u['phone'] = $uPhone;
                $u['sdt']   = $uPhone;
                $u['role']  = $u['role'] ?? 'customer';
                echo json_encode(['status' => 'success', 'user' => $u], JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Sai mật khẩu'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
}

echo json_encode(['status' => 'error', 'message' => 'Tài khoản không tồn tại'], JSON_UNESCAPED_UNICODE);
exit;
