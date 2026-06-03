<?php
require_once dirname(__DIR__) . '/includes/core.php';
require_admin();

$tab = $_GET['tab'] ?? 'dashboard';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $settings = settings();

        $settings['site_name'] = trim($_POST['site_name'] ?? '');
        $settings['site_description'] = trim($_POST['site_description'] ?? '');
        $settings['zibal_merchant'] = trim($_POST['zibal_merchant'] ?? '');
        $settings['currency'] = trim($_POST['currency'] ?? 'ریال');
        $settings['support_text'] = trim($_POST['support_text'] ?? '');

        json_write(data_file('settings.json'), $settings);
        $msg = 'تنظیمات ذخیره شد.';
        $tab = 'settings';
    }

    if ($action === 'add_category') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (int)($_POST['price'] ?? 0);

        if ($title && $price > 0) {
            json_update(data_file('categories.json'), function (&$cats) use ($title, $description, $price) {
                $cats[] = [
                    'id' => 'cat_' . uniqid(),
                    'title' => $title,
                    'description' => $description,
                    'price' => $price,
                    'active' => true,
                    'created_at' => date('c')
                ];
            });

            $msg = 'دسته‌بندی اضافه شد.';
        }

        $tab = 'categories';
    }

    if ($action === 'update_category') {
        $id = $_POST['id'] ?? '';

        json_update(data_file('categories.json'), function (&$cats) use ($id) {
            foreach ($cats as &$c) {
                if ($c['id'] === $id) {
                    $c['title'] = trim($_POST['title'] ?? '');
                    $c['description'] = trim($_POST['description'] ?? '');
                    $c['price'] = (int)($_POST['price'] ?? 0);
                    $c['active'] = !empty($_POST['active']);
                    break;
                }
            }
        });

        $msg = 'دسته‌بندی ویرایش شد.';
        $tab = 'categories';
    }

    if ($action === 'delete_category') {
        $id = $_POST['id'] ?? '';

        json_update(data_file('categories.json'), function (&$cats) use ($id) {
            $cats = array_values(array_filter($cats, fn($c) => $c['id'] !== $id));
        });

        $msg = 'دسته‌بندی حذف شد.';
        $tab = 'categories';
    }

    if ($action === 'add_product') {
        $categoryId = $_POST['category_id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $config = trim($_POST['config'] ?? '');

        if ($categoryId && $config) {
            json_update(data_file('products.json'), function (&$products) use ($categoryId, $title, $config) {
                $products[] = [
                    'id' => 'prd_' . uniqid(),
                    'category_id' => $categoryId,
                    'title' => $title ?: 'کانفیگ',
                    'config' => $config,
                    'active' => true,
                    'sold' => false,
                    'sold_order_id' => null,
                    'sold_at' => null,
                    'created_at' => date('c')
                ];
            });

            $msg = 'محصول اضافه شد.';
        }

        $tab = 'products';
    }

    if ($action === 'bulk_add_products') {
        $categoryId = $_POST['category_id'] ?? '';
        $configs = trim($_POST['configs'] ?? '');

        if ($categoryId && $configs) {
            $lines = preg_split('/\r\n|\r|\n/', $configs);
            $lines = array_values(array_filter(array_map('trim', $lines)));

            json_update(data_file('products.json'), function (&$products) use ($categoryId, $lines) {
                foreach ($lines as $line) {
                    $products[] = [
                        'id' => 'prd_' . uniqid(),
                        'category_id' => $categoryId,
                        'title' => 'کانفیگ',
                        'config' => $line,
                        'active' => true,
                        'sold' => false,
                        'sold_order_id' => null,
                        'sold_at' => null,
                        'created_at' => date('c')
                    ];
                }
            });

            $msg = count($lines) . ' کانفیگ اضافه شد.';
        }

        $tab = 'products';
    }

    if ($action === 'delete_product') {
        $id = $_POST['id'] ?? '';

        json_update(data_file('products.json'), function (&$products) use ($id) {
            $products = array_values(array_filter($products, fn($p) => $p['id'] !== $id));
        });

        $msg = 'محصول حذف شد.';
        $tab = 'products';
    }

    if ($action === 'toggle_product') {
        $id = $_POST['id'] ?? '';

        json_update(data_file('products.json'), function (&$products) use ($id) {
            foreach ($products as &$p) {
                if ($p['id'] === $id) {
                    $p['active'] = empty($p['active']);
                    break;
                }
            }
        });

        $msg = 'وضعیت محصول تغییر کرد.';
        $tab = 'products';
    }
}

$s = settings();
$cats = categories();
$products = products();
$orders = orders();

$totalOrders = count($orders);
$paidOrders = count(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'paid'));
$totalIncome = array_sum(array_map(fn($o) => ($o['status'] ?? '') === 'paid' ? (int)$o['amount'] : 0, $orders));
$totalStock = count(array_filter($products, fn($p) => empty($p['sold']) && !empty($p['active'])));
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>پنل مدیریت</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="admin-layout">

    <aside class="sidebar">
        <h2>مدیریت</h2>
        <a href="?tab=dashboard">داشبورد</a>
        <a href="?tab=categories">دسته‌بندی‌ها</a>
        <a href="?tab=products">محصولات / کانفیگ‌ها</a>
        <a href="?tab=orders">سفارش‌ها</a>
        <a href="?tab=settings">تنظیمات</a>
        <a href="../index.php" target="_blank">مشاهده سایت</a>
        <a href="logout.php">خروج</a>
    </aside>

    <main class="admin-main">
        <div class="topbar">
            <h1><?= h($s['site_name'] ?? 'فروشگاه') ?></h1>
        </div>

        <?php if ($msg): ?>
            <div class="alert success"><?= h($msg) ?></div>
        <?php endif; ?>

        <?php if ($tab === 'dashboard'): ?>
            <div class="stats">
                <div class="stat">
                    <span>کل سفارش‌ها</span>
                    <b><?= (int)$totalOrders ?></b>
                </div>
                <div class="stat">
                    <span>سفارش موفق</span>
                    <b><?= (int)$paidOrders ?></b>
                </div>
                <div class="stat">
                    <span>درآمد موفق</span>
                    <b><?= money($totalIncome) ?></b>
                </div>
                <div class="stat">
                    <span>موجودی فعال</span>
                    <b><?= (int)$totalStock ?></b>
                </div>
            </div>

            <div class="card">
                <h2>آخرین سفارش‌ها</h2>
                <?php render_orders_table(array_slice(array_reverse($orders), 0, 10)); ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'categories'): ?>
            <div class="card">
                <h2>افزودن دسته‌بندی</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_category">

                    <label>عنوان</label>
                    <input name="title" required>

                    <label>توضیح</label>
                    <textarea name="description"></textarea>

                    <label>قیمت، ریال</label>
                    <input name="price" type="number" required>

                    <button class="btn">افزودن</button>
                </form>
            </div>

            <div class="card">
                <h2>لیست دسته‌بندی‌ها</h2>

                <?php foreach ($cats as $cat): ?>
                    <form method="post" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_category">
                        <input type="hidden" name="id" value="<?= h($cat['id']) ?>">

                        <input name="title" value="<?= h($cat['title']) ?>">
                        <input name="price" type="number" value="<?= h($cat['price']) ?>">
                        <input name="description" value="<?= h($cat['description']) ?>">
                        <label class="check">
                            <input type="checkbox" name="active" <?= !empty($cat['active']) ? 'checked' : '' ?>>
                            فعال
                        </label>
                        <button class="btn small-btn">ذخیره</button>
                    </form>

                    <form method="post" onsubmit="return confirm('حذف شود؟')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="id" value="<?= h($cat['id']) ?>">
                        <button class="btn danger small-btn">حذف</button>
                    </form>
                    <hr>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'products'): ?>
            <div class="card">
                <h2>افزودن کانفیگ تکی</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_product">

                    <label>دسته‌بندی</label>
                    <select name="category_id" required>
                        <?php foreach ($cats as $cat): ?>
                            <option value="<?= h($cat['id']) ?>"><?= h($cat['title']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label>عنوان</label>
                    <input name="title" placeholder="مثلاً کانفیگ شماره ۱">

                    <label>کانفیگ</label>
                    <textarea name="config" required></textarea>

                    <button class="btn">افزودن</button>
                </form>
            </div>

            <div class="card">
                <h2>افزودن گروهی کانفیگ‌ها</h2>
                <p>هر خط، یک کانفیگ جداگانه محسوب می‌شود.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_add_products">

                    <label>دسته‌بندی</label>
                    <select name="category_id" required>
                        <?php foreach ($cats as $cat): ?>
                            <option value="<?= h($cat['id']) ?>"><?= h($cat['title']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label>کانفیگ‌ها</label>
                    <textarea name="configs" rows="8" required></textarea>

                    <button class="btn">افزودن گروهی</button>
                </form>
            </div>

            <div class="card">
                <h2>لیست محصولات</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>عنوان</th>
                            <th>دسته</th>
                            <th>وضعیت</th>
                            <th>فروش</th>
                            <th>عملیات</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_reverse($products) as $p): ?>
                            <tr>
                                <td><?= h($p['title']) ?></td>
                                <td><?= h(category_title($p['category_id'], $cats)) ?></td>
                                <td><?= !empty($p['active']) ? 'فعال' : 'غیرفعال' ?></td>
                                <td><?= !empty($p['sold']) ? 'فروخته شده' : 'آزاد' ?></td>
                                <td>
                                    <form method="post" class="mini-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_product">
                                        <input type="hidden" name="id" value="<?= h($p['id']) ?>">
                                        <button class="btn small-btn">تغییر وضعیت</button>
                                    </form>

                                    <?php if (empty($p['sold'])): ?>
                                        <form method="post" class="mini-form" onsubmit="return confirm('حذف شود؟')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="id" value="<?= h($p['id']) ?>">
                                            <button class="btn danger small-btn">حذف</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'orders'): ?>
            <div class="card">
                <h2>سفارش‌ها و پرداخت‌ها</h2>
                <?php render_orders_table(array_reverse($orders)); ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'settings'): ?>
            <div class="card">
                <h2>تنظیمات سایت</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_settings">

                    <label>نام سایت</label>
                    <input name="site_name" value="<?= h($s['site_name'] ?? '') ?>">

                    <label>توضیح سایت</label>
                    <input name="site_description" value="<?= h($s['site_description'] ?? '') ?>">

                    <label>مرچنت زیبال</label>
                    <input name="zibal_merchant" value="<?= h($s['zibal_merchant'] ?? '') ?>">

                    <label>واحد پول</label>
                    <input name="currency" value="<?= h($s['currency'] ?? 'ریال') ?>">

                    <label>متن پشتیبانی / توضیح خرید</label>
                    <textarea name="support_text"><?= h($s['support_text'] ?? '') ?></textarea>

                    <button class="btn">ذخیره تنظیمات</button>
                </form>
            </div>
        <?php endif; ?>

    </main>
</div>

</body>
</html>

<?php
function category_title($id, $cats) {
    foreach ($cats as $c) {
        if ($c['id'] === $id) return $c['title'];
    }
    return 'نامشخص';
}

function render_orders_table($orders) {
    ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>شماره</th>
                <th>سرویس</th>
                <th>مبلغ</th>
                <th>وضعیت</th>
                <th>TrackId</th>
                <th>تاریخ</th>
                <th>کانفیگ</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?= h($o['id'] ?? '') ?></td>
                    <td><?= h($o['category_title'] ?? '') ?></td>
                    <td><?= money($o['amount'] ?? 0) ?></td>
                    <td><?= h(order_status_label($o['status'] ?? '')) ?></td>
                    <td><?= h($o['trackId'] ?? '') ?></td>
                    <td><?= h($o['created_at'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($o['config_snapshot'])): ?>
                            <details>
                                <summary>نمایش</summary>
                                <pre><?= h($o['config_snapshot']) ?></pre>
                            </details>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
