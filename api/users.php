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
            $stmt = $pdo->query("SELECT id, name, email, phone, created_at as joined, 0 as orders, 0 as locked FROM users ORDER BY created_at DESC");
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

    $newId = 'KH' . str_pad((string)(count($users) + 1), 3, '0', STR_PAD_LEFT);
    $newUser = [
        'id' => $newId,
        'name' => trim($input['name']),
        'email' => trim($input['email'] ?? ''),
        'phone' => trim($input['phone'] ?? ''),
        'joined' => date('d/m/Y'),
        'orders' => 0,
        'locked' => false
    ];

    $savedToMySQL = false;
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (id, name, email, phone, password, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $newUser['id'],
                $newUser['name'],
                $newUser['email'],
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
