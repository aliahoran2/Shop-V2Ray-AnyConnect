<?php
require_once dirname(__DIR__) . '/includes/core.php';

if (is_admin()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $admin = json_read(data_file('admin.json'), []);

    if (
        $username === ($admin['username'] ?? '') &&
        password_verify($password, $admin['password_hash'] ?? '')
    ) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        redirect('index.php');
    } else {
        $error = 'نام کاربری یا رمز عبور اشتباه است.';
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>ورود ادمین</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<div class="wrap small">
    <div class="card">
        <h1>ورود به پنل مدیریت</h1>

        <?php if ($error): ?>
            <div class="alert error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>

            <label>نام کاربری</label>
            <input name="username" required>

            <label>رمز عبور</label>
            <input type="password" name="password" required>

            <button class="btn wide" type="submit">ورود</button>
        </form>
    </div>
</div>

</body>
</html>
