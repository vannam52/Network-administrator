<?php
// Proxy đọc ảnh từ MinIO (Dành riêng cho máy chủ Windows IIS không có NGINX)
// Chuyển tiếp request từ /storage/bucket/... hoặc /img/... sang http://100.72.145.103:9000/...

$path = $_GET['path'] ?? '';
if (!$path) {
    http_response_code(404);
    echo "Missing path";
    exit;
}

// Hàm dự phòng: Đọc ảnh trực tiếp từ ổ đĩa máy chủ cục bộ nếu MinIO lỗi/404/offline
function serveLocalFallback($cleanPath) {
    $rootDir = dirname(__DIR__);
    $candidates = [
        $rootDir . '/storage/' . $cleanPath,
        $rootDir . '/storage/shop-assets/' . preg_replace('#^shop-assets/#', '', $cleanPath),
        $rootDir . '/' . $cleanPath
    ];

    foreach ($candidates as $file) {
        if (file_exists($file) && is_file($file)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $mime = 'image/jpeg';
            if ($ext === 'webp') $mime = 'image/webp';
            elseif ($ext === 'png') $mime = 'image/png';
            elseif ($ext === 'svg') $mime = 'image/svg+xml';
            elseif ($ext === 'gif') $mime = 'image/gif';
            header("Content-Type: $mime");
            header("Cache-Control: public, max-age=86400");
            readfile($file);
            exit;
        }
    }
}

// Xây dựng URL gốc của MinIO
$cleanPath = ltrim($path, '/');
if (strpos($cleanPath, 'storage/') === 0) {
    $cleanPath = substr($cleanPath, 8);
}
if (strpos($cleanPath, 'shop-assets/') !== 0 && $cleanPath !== 'shop-assets') {
    $cleanPath = 'shop-assets/' . $cleanPath;
}
$minioUrl = 'http://100.72.145.103:9000/' . $cleanPath;

// Dùng file_get_contents với timeout ngắn (2s) để tránh lag trang web
$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 2,
        'ignore_errors' => true
    ]
]);

$content = @file_get_contents($minioUrl, false, $ctx);

if ($content === false) {
    serveLocalFallback($cleanPath);
    http_response_code(404);
    echo "Image not found on MinIO and local storage";
    exit;
}

$status = 200;
if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', $header, $matches)) {
            $status = (int)$matches[1];
            break;
        }
    }
}

if ($status >= 400) {
    // MinIO báo lỗi 404/403 -> Đọc ngay từ file cục bộ
    serveLocalFallback($cleanPath);
    http_response_code($status);
    exit;
}

if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (stripos($header, 'Content-Type:') === 0) {
            header(trim($header));
        }
    }
}

header("Cache-Control: public, max-age=86400");
echo $content;
exit;
