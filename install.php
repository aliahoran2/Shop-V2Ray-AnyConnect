<?php
session_start();

$base = __DIR__;
$dataDir = $base . '/data';
$logsDir = $base . '/logs';

function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function write_json($file, $data) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$done = file_exists(__DIR__ . '/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$done) {
    $siteUrl = rtrim($_POST['site_url'] ?? '', '/');
    $siteName = trim($_POST['site_name'] ?? 'فروشگاه کانفیگ');
    $merchant = trim($_POST['merchant'] ?? '');
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = $_POST['admin_pass'] ?? '';

    if (!$siteUrl || !$merchant || strlen($adminPass) < 6) {
        $error = 'اطلاعات وارد شده معتبر نیست. رمز عبور حداقل ۶ کاراکتر باشد.';
    } else {
        if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
        if (!is_dir($logsDir)) mkdir($logsDir, 0755, true);

        $config = "<?php\n";
        $config .= "define('APP_INSTALLED', true);\n";
        $config .= "define('APP_DEBUG', false);\n";
        $config .= "define('APP_URL', '" . addslashes($siteUrl) . "');\n";
        $config .= "define('DATA_PATH', __DIR__ . '/data');\n";
        $config .= "define('LOG_PATH', __DIR__ . '/logs');\n";

        file_put_contents(__DIR__ . '/config.php', $config);

        write_json($dataDir . '/settings.json', [
            'site_name' => $siteName,
            'site_description' => 'فروش کانفیگ V2Ray و AnyConnect',
            'zibal_merchant' => $merchant,
            'currency' => 'ریال',
            'support_text' => 'پس از خرید، کانفیگ در همین صفحه نمایش داده می‌شود.',
            'mobile_required' => false
        ]);

        write_json($dataDir . '/admin.json', [
            'username' => $adminUser,
            'password_hash' => password_hash($adminPass, PASSWORD_DEFAULT)
        ]);

        write_json($dataDir . '/categories.json', [
            [
                'id' => 'cat_' . uniqid(),
                'title' => 'V2Ray یک ماهه',
                'description' => 'کانفیگ اختصاصی V2Ray با حجم مناسب',
                'price' => 160000,
                'active' => true,
                'created_at' => date('c')
            ],
            [
                'id' => 'cat_' . uniqid(),
                'title' => 'AnyConnect یک ماهه',
                'description' => 'اکانت AnyConnect پایدار',
                'price' => 180000,
                'active' => true,
                'created_at' => date('c')
            ]
        ]);

        write_json($dataDir . '/products.json', []);
        write_json($dataDir . '/orders.json', []);

        file_put_contents($dataDir . '/.htaccess', "Require all denied\n");
        file_put_contents($logsDir . '/.htaccess', "Require all denied\n");

        header('Location: install.php?installed=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>نصب فروشگاه</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="wrap small">
    <div class="card">
        <h1>نصب اولیه فروشگاه</h1>

        <?php if ($done || isset($_GET['installed'])): ?>
            <div class="alert success">
                نصب انجام شد. لطفاً فایل <b>install.php</b> را حذف کنید.
            </div>
            <p>
                <a class="btn" href="index.php">مشاهده سایت</a>
                <a class="btn secondary" href="admin/login.php">ورود ادمین</a>
            </p>
        <?php else: ?>
            <?php if (!empty($error)): ?>
                <div class="alert error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <label>آدرس سایت</label>
                <input name="site_url" required placeholder="https://example.com">

                <label>نام سایت</label>
                <input name="site_name" value="فروشگاه کانفیگ">

                <label>مرچنت زیبال</label>
                <input name="merchant" required placeholder="zibal یا merchant واقعی شما">

                <label>نام کاربری ادمین</label>
                <input name="admin_user" value="admin">

                <label>رمز عبور ادمین</label>
                <input type="password" name="admin_pass" required minlength="6">

                <button class="btn" type="submit">نصب فروشگاه</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
