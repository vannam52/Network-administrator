<?php
if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db.php';
$pdo = getDatabaseConnection();

$revenue  = 125000000;
$orders   = 45;
$lowStock = 12;

if ($pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT COALESCE(SUM(total), 0) AS rev, COUNT(id) AS cnt FROM don_hang WHERE status != 'cancelled'");
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $revenue = (float)$row['rev'];
            $orders  = (int)$row['cnt'];
        }
    } catch (Throwable $e) {}
} else {
    $ordersFile = __DIR__ . '/data/orders.json';
    if (file_exists($ordersFile)) {
        $list = json_decode(file_get_contents($ordersFile), true) ?: [];
        $orders = count($list);
        $revenue = 0;
        foreach ($list as $o) {
            if (($o['status'] ?? '') !== 'Đã hủy' && ($o['rawStatus'] ?? '') !== 'cancelled') {
                $revenue += (float)($o['total'] ?? 0);
            }
        }
    }
}

echo json_encode([
    'revenue'  => $revenue,
    'orders'   => $orders,
    'lowStock' => $lowStock
]);
exit;
