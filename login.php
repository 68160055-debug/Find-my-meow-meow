<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'auth.php';
require 'config/db.php';

if (!empty($_SESSION['user_id'])) {
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
                $_SESSION['profile_id'] = (int)$user['profile_id'];
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
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <header class="navbar">
        <div class="logo">🐾 Find my meow meow 💖</div>
        <nav class="nav-links">
            <a href="index.php">หน้าหลัก</a>
            <a href="register.php" class="btn-reg">สมัครสมาชิก</a>
            <a href="login.php" class="btn-login">เข้าสู่ระบบ</a>
        </nav>
    </header>

    <div class="login-wrapper">
        <div class="login-banner">
            <h2>Adopt · Care · Love 💖</h2>
            <p>ยินดีต้อนรับกลับมา! เข้าสู่ระบบเพื่อใช้งาน Find my meow meow 💖</p>
            <img src="assets/images/cat.png" alt="Cat" class="banner-cat-img" onerror="this.src='assets/images/student.jpg';">
        </div>

        <div class="login-card">
            <div class="card-header">
                <span>🐾</span>
                <h2>เข้าสู่ระบบ</h2>
            </div>
            <p class="card-sub">เข้าสู่ระบบเพื่อใช้งาน Find my meow meow 💖</p>

            <?php if(isset($_GET['registered'])): ?><p class="ok" style="color:green; font-size:13px; margin-bottom:10px;">สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบ</p><?php endif; ?>
            <?php if(isset($_GET['logged_out'])): ?><p class="ok" style="color:green; font-size:13px; margin-bottom:10px;">ออกจากระบบเรียบร้อยแล้ว</p><?php endif; ?>
            <?php if($error): ?><p class="err" style="color:red; font-size:13px; margin-bottom:10px;"><?=auth_escape($error)?></p><?php endif; ?>

            <form method="post">
                <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
                <label>อีเมล</label>
                <input type="email" name="email" required autocomplete="username" placeholder="name@example.com">
                <label>รหัสผ่าน</label>
                <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                
                <div class="form-actions">
                    <label style="display:flex; align-items:center; gap:6px; font-weight:normal; cursor:pointer;">
                        <input type="checkbox" name="remember" style="width:auto; margin:0;"> จำฉันไว้ในระบบ
                    </label>
                    <a href="#" class="forgot-link">ลืมรหัสผ่าน?</a>
                </div>

                <button type="submit" class="btn-submit">เข้าสู่ระบบ</button>
            </form>
            
            <p class="register-prompt">
                ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิกที่นี่</a>
            </p>
            <div style="text-align: center; margin-top: 15px;">
                <a href="index.php" style="color: #666; font-size: 13px; text-decoration: none;">← กลับไปหน้าหลัก</a>
            </div>
        </div>
    </div>
</body>
</html>