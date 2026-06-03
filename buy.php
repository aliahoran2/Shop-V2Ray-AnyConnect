<?php
require_once __DIR__ . '/includes/core.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

csrf_check();

$categoryId = $_POST['category_id'] ?? '';
$mobile = trim($_POST['mobile'] ?? '');

$cat = find_category($categoryId);

if (!$cat || empty($cat['active'])) {
    die('سرویس معتبر نیست.');
}

if (stock_count($categoryId) <= 0) {
    die('موجودی این سرویس تمام شده است.');
}

$orderId = 'ORD-' . date('YmdHis') . '-' . random_int(1000, 9999);
$amount = (int)$cat['price'];

$order = [
    'id' => $orderId,
    'category_id' => $categoryId,
    'category_title' => $cat['title'],
    'amount' => $amount,
    'mobile' => $mobile,
    'status' => 'pending',
    'trackId' => null,
    'zibal_result' => null,
    'config_snapshot' => null,
    'product_id' => null,
    'created_at' => date('c'),
    'paid_at' => null,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
];

json_update(data_file('orders.json'), function (&$orders) use ($order) {
    $orders[] = $order;
});

$callback = app_url('callback.php');
$description = 'خرید ' . $cat['title'] . ' - سفارش ' . $orderId;

$response = zibal_request($amount, $callback, $description, $orderId, $mobile);

if (!empty($response['result']) && (int)$response['result'] === 100 && !empty($response['trackId'])) {
    $trackId = $response['trackId'];

    json_update(data_file('orders.json'), function (&$orders) use ($orderId, $trackId, $response) {
        foreach ($orders as &$o) {
            if ($o['id'] === $orderId) {
                $o['trackId'] = $trackId;
                $o['zibal_request'] = $response;
                break;
            }
        }
    });

    redirect('https://gateway.zibal.ir/start/' . urlencode($trackId));
}

json_update(data_file('orders.json'), function (&$orders) use ($orderId, $response) {
    foreach ($orders as &$o) {
        if ($o['id'] === $orderId) {
            $o['status'] = 'failed';
            $o['zibal_request'] = $response;
            break;
        }
    }
});

die('خطا در اتصال به درگاه پرداخت. لطفاً بعداً تلاش کنید.');
