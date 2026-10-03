<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'PHP CLI / Built-in Server';
$isIIS = (stripos($serverSoftware, 'Microsoft-IIS') !== false);
$isApache = (stripos($serverSoftware, 'Apache') !== false);

$nodeType = 'Other Node';
$nodeColor = '#10b981';

if ($isIIS) {
    $nodeType = 'Node Windows (IIS Web Server)';
    $nodeColor = '#0078d4';
} elseif ($isApache) {
    $nodeType = 'Node Linux (Apache Web Server)';
    $nodeColor = '#d9381e';
}

$hostname = gethostname();
$serverIp = $_SERVER['SERVER_ADDR'] ?? gethostbyname($hostname);
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

$response = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'project' => 'Đề tài 10 - Triển khai hệ thống Hosting E-commerce Đa nền tảng',
    'node' => [
        'name' => $nodeType,
        'color' => $nodeColor,
        'serverSoftware' => $serverSoftware,
        'os' => PHP_OS . ' (' . php_uname('s') . ' ' . php_uname('r') . ')',
        'hostname' => $hostname,
        'serverIp' => $serverIp,
        'serverPort' => $_SERVER['SERVER_PORT'] ?? 80,
    ],
    'database' => getDatabaseStatus(),
    'client' => [
        'ip' => $clientIp,
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'viaProxy' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? true : false
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
