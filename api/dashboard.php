<?php
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();

$totalRevenue = 0;
$orderCount = 0;
$lowStockCount = 0;

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT SUM(total) as revenue, COUNT(id) as count FROM orders WHERE status != 'Đã huỷ'");
        $res = $stmt->fetch();
        $totalRevenue = $res['revenue'] ?? 0;
        $orderCount = $res['count'] ?? 0;

        $stmt2 = $pdo->query("SELECT COUNT(id) as low_stock FROM products WHERE stock <= 10");
        $res2 = $stmt2->fetch();
        $lowStockCount = $res2['low_stock'] ?? 0;
    } catch (Throwable $e) {}
} else {
    // Fallback to JSON data
    $ordersFile = __DIR__ . '/data/orders.json';
    if (file_exists($ordersFile)) {
        $orders = json_decode(file_get_contents($ordersFile), true) ?: [];
        foreach ($orders as $o) {
            if ($o['status'] !== 'Đã huỷ') {
                $totalRevenue += (float)($o['total'] ?? 0);
                $orderCount++;
            }
        }
    }
    $productsFile = __DIR__ . '/data/products.json';
    if (file_exists($productsFile)) {
        $products = json_decode(file_get_contents($productsFile), true) ?: [];
        foreach ($products as $p) {
            if (($p['stock'] ?? 10) <= 10) {
                $lowStockCount++;
            }
        }
    }
}

echo json_encode([
    'revenue' => $totalRevenue,
    'orders' => $orderCount,
    'lowStock' => $lowStockCount
]);
