<?php
require_once 'auth.php';
require_once 'config/db.php';

// หากล็อกอินอยู่แล้ว ให้ข้ามไปยังหน้าหลัก index.php (ไม่ต้องล็อกอินซ้ำ)
if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_auth_token();

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'กรุณากรอกอีเมลและรหัสผ่าน';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            // สร้าง Session ID ใหม่เพื่อป้องกัน Session Fixation
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['full_name'] = $user['full_name'];

            header("Location: index.php");
            exit();
        } else {
            $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ - Find my meow meow 💖</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #fff0f3;
            font-family: 'Kanit', sans-serif;
            color: #444;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-card {
            background: #ffffff;
            border-radius: 24px;
            padding: 35px 30px;
            width: 100%;
            max-width: 420px;
            border: 1px solid #ffccd5;
            box-shadow: 0 8px 25px rgba(255, 117, 140, 0.15);
            text-align: center;
        }
        .login-brand {
            font-weight: bold;
            font-size: 24px;
            color: #d81b60;
            margin-bottom: 8px;
        }
        .login-sub {
            color: #777;
            font-size: 14px;
            margin-bottom: 25px;
        }
        .form-group {
            text-align: left;
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            font-size: 14px;
            color: #555;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ffccd5;
            border-radius: 14px;
            font-size: 14px;
            background: #fff8f9;
            font-family: 'Kanit', sans-serif;
            outline: none;
            transition: border 0.2s;
        }
        .form-group input:focus {
            border-color: #d81b60;
        }
        .btn-login {
            background: #d81b60;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            font-family: 'Kanit', sans-serif;
            box-shadow: 0 4px 12px rgba(216, 27, 96, 0.25);
            transition: background 0.2s;
        }
        .btn-login:hover {
            background: #ad1457;
        }
        .alert-error {
            background: #ffe4e6;
            color: #9f1239;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 18px;
            border: 1px solid #fecdd3;
        }
        .login-footer {
            margin-top: 20px;
            font-size: 13px;
            color: #666;
        }
        .login-footer a {
            color: #d81b60;
            text-decoration: none;
            font-weight: 500;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-brand">🐾 Find my meow meow</div>
    <div class="login-sub">ยินดีต้อนรับ เข้าสู่ระบบเพื่อดูแลน้องแมว 💖</div>

    <?php if ($error !== ''): ?>
        <div class="alert-error"><?= auth_escape($error) ?></div>
    <?php endif; ?>

    <form method="post" action="login.php">
        <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">

        <div class="form-group">
            <label for="email">📧 อีเมล (Email)</label>
            <input type="email" id="email" name="email" placeholder="example@email.com" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">🔒 รหัสผ่าน (Password)</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-login">เข้าสู่ระบบ / Sign In</button>
    </form>

    <div class="login-footer">
        ยังไม่มีบัญชีผู้ใช้งาน? <a href="register.php">สมัครสมาชิกที่นี่</a>
    </div>
</div>

</body>
</html>