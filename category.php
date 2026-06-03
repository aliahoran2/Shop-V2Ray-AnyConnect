<?php
require_once __DIR__ . '/includes/core.php';

$id = $_GET['id'] ?? '';
$cat = find_category($id);

if (!$cat || empty($cat['active'])) {
    http_response_code(404);
    die('دسته‌بندی یافت نشد.');
}

$stock = stock_count($cat['id']);
$s = settings();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title><?= h($cat['title']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="wrap small">
    <div class="card detail">
        <a href="index.php" class="link">بازگشت به صفحه اصلی</a>

        <h1><?= h($cat['title']) ?></h1>
        <p><?= h($cat['description']) ?></p>

        <div class="price big"><?= money($cat['price']) ?></div>
        <div class="stock">موجودی فعلی: <?= (int)$stock ?></div>

        <hr>

        <?php if ($stock > 0): ?>
            <form method="post" action="buy.php">
                <?= csrf_field() ?>
                <input type="hidden" name="category_id" value="<?= h($cat['id']) ?>">

                <label>شماره موبایل، اختیاری</label>
                <input name="mobile" placeholder="09123456789">

                <button class="btn wide" type="submit">پرداخت و دریافت کانفیگ</button>
            </form>
        <?php else: ?>
            <div class="alert error">این سرویس فعلاً موجود نیست.</div>
        <?php endif; ?>

        <div class="note">
            <?= nl2br(h($s['support_text'] ?? '')) ?>
        </div>
    </div>
</div>

</body>
</html>
