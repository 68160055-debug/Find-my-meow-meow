<?php
$active = 'Adopter.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function auth_token() {
    if (empty($_SESSION['auth_csrf'])) {
        $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['auth_csrf'];
}

function check_auth_token() {
    if (!isset($_POST['auth_csrf']) || $_POST['auth_csrf'] !== $_SESSION['auth_csrf']) {
        die("CSRF token validation failed.");
    }
}

function auth_escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>

<?php
// ตั้งเวลาไทยให้ทุกหน้า (แก้ปัญหาปฏิทินไฮไลต์วันที่ผิดเพราะเซิร์ฟเวอร์ใช้ UTC)
date_default_timezone_set('Asia/Bangkok');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function auth_token() {
    if (empty($_SESSION['auth_csrf'])) {
        $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['auth_csrf'];
}

function check_auth_token() {
    $sent = (string)($_POST['auth_csrf'] ?? '');
    $real = (string)($_SESSION['auth_csrf'] ?? '');
    if ($real === '' || !hash_equals($real, $sent)) {
        http_response_code(403);
        die("CSRF token validation failed. กรุณากลับไปโหลดหน้าใหม่แล้วลองอีกครั้ง");
    }
}

function auth_escape($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>