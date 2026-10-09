<?php
$dbHost = '100.72.145.103';
$dbPort = '3306';
$dbName = 'phone_store';
$dbUser = 'shop_web';
$dbPass = 'ShopWeb@123';

$pdo = null;
$dbError = null;
$dbTried = false;

const DB_RETRY_AFTER = 15; // giây: sau khi lỗi, nghỉ bấy lâu mới thử lại
$dbBreakerFile = __DIR__ . '/data/.db_down';

// timeout đọc/ghi socket, tránh query treo vô hạn
@ini_set('default_socket_timeout', '3');
@ini_set('mysqlnd.net_read_timeout', '3');

function getDatabaseConnection() {
    global $pdo, $dbError, $dbTried, $dbBreakerFile;
    global $dbHost, $dbPort, $dbName, $dbUser, $dbPass;

    if ($dbTried) return $pdo;          // mỗi request chỉ thử 1 lần
    $dbTried = true;

    // Vừa lỗi gần đây -> bỏ qua, dùng JSON ngay (không tốn 2s)
    if (is_file($dbBreakerFile) && (time() - filemtime($dbBreakerFile)) < DB_RETRY_AFTER) {
        $dbError = 'MySQL tạm bỏ qua (vừa lỗi trong ' . DB_RETRY_AFTER . 's gần đây)';
        return null;
    }

    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 1,   // timeout kết nối 1s
        ]);
        @unlink($dbBreakerFile);
    } catch (Throwable $e) {
        $pdo = null;
        $dbError = $e->getMessage();
        if (!is_dir(dirname($dbBreakerFile))) @mkdir(dirname($dbBreakerFile), 0777, true);
        @touch($dbBreakerFile);
    }
    return $pdo;
}

function getDatabaseStatus() {
    global $dbHost, $dbPort, $dbName, $dbError;
    $pdo = getDatabaseConnection();
    if ($pdo !== null) {
        return [
            'connected' => true,
            'mode' => 'MySQL Remote (VM 4 - Tailscale)',
            'host' => "{$dbHost}:{$dbPort}",
            'database' => $dbName,
            'message' => 'Đã kết nối thành công Database tập trung MySQL trên VM 4 (Thành Phát)'
        ];
    }
    return [
        'connected' => false,
        'mode' => 'Local File JSON Fallback (Resilient)',
        'targetHost' => "{$dbHost}:{$dbPort}",
        'database' => $dbName,
        'reason' => $dbError ? 'MySQL chưa sẵn sàng: ' . $dbError : 'Chưa bật MySQL server',
        'message' => 'Máy chủ MySQL (VM 4) chưa online, hệ thống tự động chạy chế độ CSDL JSON cục bộ an toàn'
    ];
}