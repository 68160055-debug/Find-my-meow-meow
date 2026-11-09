<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';  // เดิมเป็น auth.php
require 'config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_auth_token();
    $full_name = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if ($full_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน (รหัสผ่านอย่างน้อย 6 ตัวอักษร)';
    } elseif ($password !== $confirm_password) {
        $error = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = 'อีเมลนี้ถูกใช้งานแล้ว กรุณาใช้อีเมลอื่น';
        } else {
            $stmt->close();
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            mysqli_begin_transaction($conn);
            try {
                $ps = $conn->prepare("INSERT INTO profile (full_name, headline, email, phone, address, about_me, photo) VALUES (?, '', ?, '', '', '', 'student.jpg')");
                $ps->bind_param("ss", $full_name, $email);
                $ps->execute();
                $new_profile_id = $conn->insert_id;
                $ps->close();

                $insert = $conn->prepare("INSERT INTO users (profile_id, full_name, email, password_hash) VALUES (?, ?, ?, ?)");
                $insert->bind_param("isss", $new_profile_id, $full_name, $email, $password_hash);
                $insert->execute();
                $insert->close();

                mysqli_commit($conn);
                header("Location: login.php?registered=1");
                exit();
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $error = 'สมัครสมาชิกไม่สำเร็จ: ' . $e->getMessage();
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>สมัครสมาชิก - Find my meow meow</title>
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
            <h2>Find a home... for every cat 💖</h2>
            <p>สร้างบัญชีใหม่ เพื่อเริ่มต้นใช้งาน Find my meow meow 💖</p>
            <img src="assets/images/cat.png" alt="Cat" class="banner-cat-img" onerror="this.src='assets/images/student.jpg';">
        </div>

        <div class="login-card">
            <div class="card-header">
                <span>🐾</span>
                <h2>สมัครสมาชิก</h2>
            </div>
            <p class="card-sub">ร่วมเป็นส่วนหนึ่งในการดูแลแมวไปด้วยกัน 💖</p>

            <?php if($error): ?><p class="err" style="color:red; font-size:13px; margin-bottom:10px;"><?=auth_escape($error)?></p><?php endif; ?>

            <form method="post">
                <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
                <label>ชื่อ-นามสกุล</label>
                <input type="text" name="full_name" required placeholder="กรอกชื่อ-นามสกุล">
                <label>อีเมล</label>
                <input type="email" name="email" required placeholder="name@example.com">
                <label>รหัสผ่าน</label>
                <input type="password" name="password" required placeholder="อย่างน้อย 6 ตัวอักษร">
                <label>ยืนยันรหัสผ่าน</label>
                <input type="password" name="confirm_password" required placeholder="กรอกรหัสผ่านอีกครั้ง">
                
                <button type="submit" class="btn-submit" style="margin-top: 10px;">สมัครสมาชิก</button>
            </form>
            
            <p class="register-prompt">
                มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบที่นี่</a>
            </p>
            <div style="text-align: center; margin-top: 15px;">
                <a href="index.php" style="color: #666; font-size: 13px; text-decoration: none;">← กลับไปหน้าหลัก</a>
            </div>
        </div>
    </div>
</body>
</html>