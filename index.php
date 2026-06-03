<?php
require_once __DIR__ . '/includes/core.php';

$s = settings();
$cats = categories();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title><?= h($s['site_name'] ?? 'فروشگاه کانفیگ') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<header class="hero">
    <div class="hero-inner">
        <h1><?= h($s['site_name'] ?? 'فروشگاه کانفیگ') ?></h1>
        <p><?= h($s['site_description'] ?? '') ?></p>
        <a href="#services" class="btn">مشاهده سرویس‌ها</a>
    </div>
</header>

<main class="wrap" id="services">
    <div class="section-title">
        <h2>دسته‌بندی سرویس‌ها</h2>
        <p>سرویس موردنظر خود را انتخاب و بعد از پرداخت، کانفیگ را دریافت کنید.</p>
    </div>

    <div class="grid">
        <?php foreach ($cats as $cat): ?>
            <?php if (empty($cat['active'])) continue; ?>
            <?php $stock = stock_count($cat['id']); ?>
            <div class="card service-card">
                <div class="badge <?= $stock > 0 ? 'ok' : 'off' ?>">
                    <?= $stock > 0 ? 'موجود' : 'ناموجود' ?>
                </div>
                <h3><?= h($cat['title']) ?></h3>
                <p><?= h($cat['description']) ?></p>
                <div class="price"><?= money($cat['price']) ?></div>
                <div class="stock">موجودی: <?= (int)$stock ?></div>

                <?php if ($stock > 0): ?>
                    <a class="btn" href="category.php?id=<?= urlencode($cat['id']) ?>">جزئیات و خرید</a>
                <?php else: ?>
                    <button class="btn disabled" disabled>ناموجود</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<footer class="footer">
    <p>Powered by PHP + JSON</p>
</footer>

</body>
</html>
