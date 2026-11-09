<?php
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