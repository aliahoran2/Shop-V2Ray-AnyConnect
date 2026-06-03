<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!file_exists(dirname(__DIR__) . '/config.php')) {
    die('فروشگاه نصب نشده است. ابتدا install.php را اجرا کنید.');
}

require_once dirname(__DIR__) . '/config.php';

function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function app_url($path = '') {
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function data_file($name) {
    return DATA_PATH . '/' . $name;
}

function json_read($file, $default = []) {
    if (!file_exists($file)) return $default;

    $fp = fopen($file, 'r');
    if (!$fp) return $default;

    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    $data = json_decode($content, true);
    return is_array($data) ? $data : $default;
}

function json_write($file, $data) {
    $fp = fopen($file, 'c+');
    if (!$fp) {
        throw new Exception('Cannot open file: ' . $file);
    }

    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function json_update($file, callable $callback, $default = []) {
    $fp = fopen($file, 'c+');
    if (!$fp) {
        throw new Exception('Cannot open file: ' . $file);
    }

    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $data = json_decode($content, true);
    if (!is_array($data)) $data = $default;

    $result = $callback($data);

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($fp);

    flock($fp, LOCK_UN);
    fclose($fp);

    return $result;
}

function settings() {
    return json_read(data_file('settings.json'), []);
}

function categories() {
    return json_read(data_file('categories.json'), []);
}

function products() {
    return json_read(data_file('products.json'), []);
}

function orders() {
    return json_read(data_file('orders.json'), []);
}

function find_category($id) {
    foreach (categories() as $cat) {
        if (($cat['id'] ?? '') === $id) {
            return $cat;
        }
    }
    return null;
}

function stock_count($categoryId) {
    $count = 0;
    foreach (products() as $p) {
        if (
            ($p['category_id'] ?? '') === $categoryId &&
            empty($p['sold']) &&
            !empty($p['active'])
        ) {
            $count++;
        }
    }
    return $count;
}

function csrf_token() {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check() {
    $token = $_POST['_csrf'] ?? '';
    if (!$token || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $token)) {
        http_response_code(403);
        die('CSRF token invalid');
    }
}

function is_admin() {
    return !empty($_SESSION['admin_logged_in']);
}

function require_admin() {
    if (!is_admin()) {
        header('Location: login.php');
        exit;
    }
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function zibal_request($amount, $callbackUrl, $description, $orderId, $mobile = '') {
    $s = settings();

    $payload = [
        'merchant' => $s['zibal_merchant'] ?? '',
        'amount' => (int)$amount,
        'callbackUrl' => $callbackUrl,
        'description' => $description,
        'orderId' => $orderId
    ];

    if ($mobile) {
        $payload['mobile'] = $mobile;
    }

    return http_json_post('https://gateway.zibal.ir/v1/request', $payload);
}

function zibal_verify($trackId) {
    $s = settings();

    return http_json_post('https://gateway.zibal.ir/v1/verify', [
        'merchant' => $s['zibal_merchant'] ?? '',
        'trackId' => (int)$trackId
    ]);
}

function http_json_post($url, array $payload) {
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30
    ]);

    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($err) {
        return [
            'ok' => false,
            'error' => $err,
            'http_code' => $code
        ];
    }

    $json = json_decode($body, true);

    if (!is_array($json)) {
        return [
            'ok' => false,
            'error' => 'Invalid JSON response',
            'raw' => $body,
            'http_code' => $code
        ];
    }

    $json['ok'] = true;
    $json['http_code'] = $code;

    return $json;
}

function order_status_label($status) {
    return [
        'pending' => 'در انتظار پرداخت',
        'paid' => 'پرداخت موفق',
        'failed' => 'ناموفق',
        'no_stock' => 'پرداخت شده، بدون موجودی'
    ][$status] ?? $status;
}

function money($amount) {
    $s = settings();
    return number_format((int)$amount) . ' ' . h($s['currency'] ?? 'ریال');
}
