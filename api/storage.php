<?php
// Proxy đọc ảnh từ MinIO (Dành riêng cho máy chủ Windows IIS không có NGINX)
// Chuyển tiếp request từ /storage/bucket/... sang http://100.72.145.103:9000/...

$path = $_GET['path'] ?? '';
if (!$path) {
    http_response_code(404);
    echo "Missing path";
    exit;
}

// Xây dựng URL gốc của MinIO
$cleanPath = ltrim($path, '/');
if (strpos($cleanPath, 'shop-assets') === 0) {
    // Nếu trong CSDL đường dẫn bị thiếu tên bucket, tự động bổ sung
    $cleanPath = 'bucket/' . $cleanPath;
}
$minioUrl = 'http://100.72.145.103:9000/' . $cleanPath;

// Dùng file_get_contents thay vì cURL để đảm bảo tương thích 100% trên mọi máy Windows IIS
$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 8,
        'ignore_errors' => true
    ]
]);

$content = @file_get_contents($minioUrl, false, $ctx);

if ($content === false) {
    http_response_code(404);
    echo "Image not found on MinIO";
    exit;
}

if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', $header, $matches)) {
            $status = (int)$matches[1];
            if ($status >= 400) {
                http_response_code($status);
                exit;
            }
        }
        if (stripos($header, 'Content-Type:') === 0) {
            header(trim($header));
        }
    }
}

echo $content;
exit;
