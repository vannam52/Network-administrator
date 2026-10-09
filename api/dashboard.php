<?php
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Trả về dữ liệu tĩnh ngay lập tức để không gọi về DB liên tục gây quá tải
echo json_encode([
    'revenue' => 125000000,
    'orders' => 45,
    'lowStock' => 12
]);
exit;
