<?php
$dbHost = '100.72.145.103';
$dbPort = '3306';
$dbName = 'shop_db';
$dbUser = 'shop_user';
$dbPass = 'Shop@123456';

$pdo = null;
$dbError = null;

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2
    ];
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (Throwable $e) {
    $pdo = null;
    $dbError = $e->getMessage();
}

/**
 * Lấy đối tượng kết nối PDO MySQL
 * @return PDO|null
 */
function getDatabaseConnection() {
    global $pdo;
    return $pdo;
}

/**
 * Kiểm tra trạng thái Database tập trung (MySQL vs Fallback JSON)
 * @return array
 */
function getDatabaseStatus() {
    global $pdo, $dbHost, $dbPort, $dbName, $dbError;
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
