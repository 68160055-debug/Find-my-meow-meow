<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'auth.php';
require 'config/db.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['profile_id'])) {
    header('Location: index.php'); exit;
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_auth_token();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'กรุณากรอกอีเมลและรหัสผ่านให้ถูกต้อง';
    } else {
        // ดึง profile_id ตรงจากตาราง users ของผู้ใช้นั้น
        $stmt = $conn->prepare("SELECT id, full_name, password_hash, profile_id FROM users WHERE email = ? LIMIT 1");
        if (!$stmt) {
            $error = 'DB error: ' . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['profile_id'] = (int)$user['profile_id']; // ใช้ profile_id ของตัวเองจริงๆ
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $email;

                header("Location: index.php");
                exit();
            } else {
                $error = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>เข้าสู่ระบบ - Find my meow meow</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="panel">
        <h1>🐾 เข้าสู่ระบบ</h1>
        <p>เข้าสู่ระบบเพื่อใช้งาน Find my meow meow 💖</p>
        <?php if(isset($_GET['registered'])): ?><p class="ok">สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ</p><?php endif; ?>
        <?php if(isset($_GET['logged_out'])): ?><p class="ok">ออกจากระบบเรียบร้อยแล้ว</p><?php endif; ?>
        <?php if($error): ?><p class="err"><?=auth_escape($error)?></p><?php endif; ?>
        
        <div style="text-align: center; margin-bottom: 15px;">
            <img src="assets/images/cat.png" alt="Cat Logo" width="80" style="border-radius: 50%;">
        </div>

        <form method="post">
            <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
            <label>อีเมล</label>
            <input type="email" name="email" required autocomplete="username">
            <label>รหัสผ่าน</label>
            <input type="password" name="password" required autocomplete="current-password">
            <button type="submit">เข้าสู่ระบบ</button>
        </form>
        <p>ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิกที่นี่</a></p>
    </div>
</body>
</html>