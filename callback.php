<?php
require_once __DIR__ . '/includes/core.php';

$trackId = $_GET['trackId'] ?? '';
$success = $_GET['success'] ?? '';
$status = $_GET['status'] ?? '';
$orderId = $_GET['orderId'] ?? '';

if (!$trackId) {
    die('trackId نامعتبر است.');
}

$displayOrder = null;
$message = '';
$ok = false;

if ((string)$success !== '1') {
    json_update(data_file('orders.json'), function (&$orders) use ($trackId, &$displayOrder) {
        foreach ($orders as &$o) {
            if ((string)$o['trackId'] === (string)$trackId) {
                $o['status'] = 'failed';
                $o['zibal_callback'] = $_GET;
                $displayOrder = $o;
                break;
            }
        }
    });

    $message = 'پرداخت توسط کاربر لغو شد یا ناموفق بود.';
} else {
    $verify = zibal_verify($trackId);

    $verifySuccess = !empty($verify['result']) && in_array((int)$verify['result'], [100, 201], true);

    if (!$verifySuccess) {
        json_update(data_file('orders.json'), function (&$orders) use ($trackId, $verify, &$displayOrder) {
            foreach ($orders as &$o) {
                if ((string)$o['trackId'] === (string)$trackId) {
                    $o['status'] = 'failed';
                    $o['zibal_verify'] = $verify;
                    $o['zibal_callback'] = $_GET;
                    $displayOrder = $o;
                    break;
                }
            }
        });

        $message = 'پرداخت تایید نشد.';
    } else {
        /*
         * جلوگیری از فروش همزمان:
         * فایل products.json با LOCK_EX باز می‌شود.
         * اولین محصول آزاد همان دسته انتخاب و sold می‌شود.
         */
        $result = json_update(data_file('products.json'), function (&$products) use ($trackId, $verify, &$displayOrder) {
            $orders = json_read(data_file('orders.json'), []);

            $orderIndex = null;
            foreach ($orders as $i => $o) {
                if ((string)$o['trackId'] === (string)$trackId) {
                    $orderIndex = $i;
                    break;
                }
            }

            if ($orderIndex === null) {
                return [
                    'ok' => false,
                    'reason' => 'order_not_found'
                ];
            }

            if (($orders[$orderIndex]['status'] ?? '') === 'paid') {
                $displayOrder = $orders[$orderIndex];
                return [
                    'ok' => true,
                    'already_paid' => true
                ];
            }

            $categoryId = $orders[$orderIndex]['category_id'];

            foreach ($products as &$p) {
                if (
                    ($p['category_id'] ?? '') === $categoryId &&
                    empty($p['sold']) &&
                    !empty($p['active'])
                ) {
                    $p['sold'] = true;
                    $p['sold_order_id'] = $orders[$orderIndex]['id'];
                    $p['sold_at'] = date('c');

                    $orders[$orderIndex]['status'] = 'paid';
                    $orders[$orderIndex]['paid_at'] = date('c');
                    $orders[$orderIndex]['product_id'] = $p['id'];
                    $orders[$orderIndex]['config_snapshot'] = $p['config'];
                    $orders[$orderIndex]['zibal_verify'] = $verify;
                    $orders[$orderIndex]['zibal_callback'] = $_GET;

                    json_write(data_file('orders.json'), $orders);

                    $displayOrder = $orders[$orderIndex];

                    return [
                        'ok' => true
                    ];
                }
            }

            $orders[$orderIndex]['status'] = 'no_stock';
            $orders[$orderIndex]['paid_at'] = date('c');
            $orders[$orderIndex]['zibal_verify'] = $verify;
            $orders[$orderIndex]['zibal_callback'] = $_GET;
            json_write(data_file('orders.json'), $orders);

            $displayOrder = $orders[$orderIndex];

            return [
                'ok' => false,
                'reason' => 'no_stock'
            ];
        });

        if (!empty($result['ok'])) {
            $ok = true;
            $message = 'پرداخت با موفقیت انجام شد.';
        } elseif (($result['reason'] ?? '') === 'no_stock') {
            $message = 'پرداخت انجام شد اما موجودی به پایان رسیده است. لطفاً با پشتیبانی تماس بگیرید.';
        } else {
            $message = 'خطای پردازش سفارش.';
        }
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>نتیجه پرداخت</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="wrap small">
    <div class="card">
        <h1>نتیجه پرداخت</h1>

        <div class="alert <?= $ok ? 'success' : 'error' ?>">
            <?= h($message) ?>
        </div>

        <?php if ($displayOrder): ?>
            <p>شماره سفارش: <b><?= h($displayOrder['id']) ?></b></p>
            <p>سرویس: <b><?= h($displayOrder['category_title']) ?></b></p>
            <p>مبلغ: <b><?= money($displayOrder['amount']) ?></b></p>
            <p>وضعیت: <b><?= h(order_status_label($displayOrder['status'])) ?></b></p>

            <?php if (!empty($displayOrder['config_snapshot'])): ?>
                <h2>کانفیگ خریداری‌شده</h2>
                <textarea class="config-box" readonly><?= h($displayOrder['config_snapshot']) ?></textarea>
            <?php endif; ?>
        <?php endif; ?>

        <a class="btn" href="index.php">بازگشت به فروشگاه</a>
    </div>
</div>

</body>
</html>
