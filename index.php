<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ตั้งค่า Timezone ประเทศไทยเป็นมาตรฐาน
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'config/db.php';
require_once 'config/lang.php'; // เพิ่มบรรทัดนี้
require_login();

$user_id = $_SESSION['user_id'];

// 1. รับค่าและบันทึกโพสต์ใหม่ด้วยเพียว PHP (เก็บลงใน Session เพื่อแสดงผลทันที)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_content'])) {
    $content = trim((string)$_POST['post_content']);
    if ($content !== '') {
        if (!isset($_SESSION['user_posts'])) {
            $_SESSION['user_posts'] = [];
        }
        // ดึงข้อมูลผู้โพสต์ปัจจุบัน
        $stmt_u = $conn->prepare("SELECT full_name, avatar FROM users WHERE id = ? LIMIT 1");
        $stmt_u->bind_param("i", $user_id);
        $stmt_u->execute();
        $author_data = $stmt_u->get_result()->fetch_assoc();
        $stmt_u->close();

        // บันทึกโพสต์ใหม่ไว้บนสุดของฟีด
        array_unshift($_SESSION['user_posts'], [
            'author' => $author_data['full_name'] ?? 'ผู้ใช้งาน',
            'avatar' => !empty($author_data['avatar']) ? $author_data['avatar'] : 'student.jpg',
            'content' => $content,
            'time' => 'เมื่อสักครู่'
        ]);

        header("Location: index.php");
        exit();
    }
}

// ดึงข้อมูลผู้ใช้งานปัจจุบัน
$stmt = $conn->prepare("SELECT full_name, email, avatar, status, nickname FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$avatar_img = !empty($current_user['avatar']) ? $current_user['avatar'] : 'student.jpg';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>หน้าแรก - Find my meow meow 💖</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff0f3; font-family: 'Kanit', sans-serif; color: #444; }
        .dashboard-layout { display: grid; grid-template-columns: 260px 1fr 310px; min-height: 100vh; }
        .dash-sidebar { background: #ffffff; border-right: 1px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 12px; position: sticky; top: 0; height: 100vh; }
        .dash-brand { font-weight: bold; font-size: 20px; color: #d81b60; margin-bottom: 15px; }
        .dash-nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 14px; text-decoration: none; color: #555; font-weight: 500; font-size: 15px; transition: all 0.2s; }
        .dash-nav-item.active, .dash-nav-item:hover { background: #ff758c; color: white; box-shadow: 0 4px 12px rgba(255, 117, 140, 0.3); }
        .dash-main { padding: 25px 30px; overflow-y: auto; }
        .post-box { background: white; border-radius: 20px; padding: 18px 22px; border: 1px solid #ffccd5; box-shadow: 0 4px 15px rgba(0,0,0,0.02); margin-bottom: 25px; }
        .post-input-row { display: flex; gap: 15px; align-items: center; }
        .post-input-row input { flex: 1; padding: 12px 20px; border-radius: 25px; border: 1px solid #ffccd5; background: #fff8f9; font-family: 'Kanit', sans-serif; font-size: 14px; outline: none; }
        .btn-post { background: #d81b60; color: white; border: none; padding: 10px 24px; border-radius: 20px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; }
        .feed-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #ffccd5; box-shadow: 0 4px 15px rgba(0,0,0,0.02); margin-bottom: 25px; }
        .feed-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .feed-user-info { display: flex; align-items: center; gap: 12px; }
        .feed-user-info img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; }
        .post-actions { display: flex; gap: 10px; margin-top: 15px; border-top: 1px solid #fff0f3; padding-top: 12px; }
        .act-btn { background: #fff0f3; border: 1px solid #ffccd5; border-radius: 14px; padding: 8px 18px; font-size: 13px; color: #d81b60; cursor: pointer; font-family: 'Kanit', sans-serif; }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <!-- 1. แถบเมนูด้านซ้าย -->
   <aside class="dash-sidebar">
    <div class="dash-brand">🐾 Find my meow meow</div>
    <a href="index.php" class="dash-nav-item active"><?= $L['nav_home'] ?></a>
    <a href="profile.php" class="dash-nav-item"><?= $L['nav_profile'] ?></a>
    <a href="contact.php" class="dash-nav-item"><?= $L['nav_contact'] ?></a>
    <a href="buy_cat.php" class="dash-nav-item"><?= $L['nav_buy'] ?></a>
    <a href="adopter.php" class="dash-nav-item"><?= $L['nav_chat'] ?></a>
    <a href="settings.php" class="dash-nav-item"><?= $L['nav_settings'] ?></a>
    <div style="margin-top: auto;">
        <a href="logout.php" class="dash-nav-item" style="background: #fff0f3; color: #d81b60; border: 1px solid #ffccd5;"><?= $L['nav_logout'] ?></a>
    </div>
</aside>

    <!-- 2. พื้นที่ตรงกลาง (Main Content) -->
    <main class="dash-main">
        <!-- แถบค้นหาด้านบน -->
        <?php if (file_exists('config/search.php')) include 'config/search.php'; ?>

        <!-- กล่องแชร์โพสต์ -->
        <form method="POST" action="index.php" class="post-box">
            <div class="post-input-row">
                <img src="assets/images/<?= htmlspecialchars($avatar_img) ?>" onerror="this.src='assets/images/student.jpg';" style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover;">
                <input type="text" name="post_content" required placeholder="คุณอยากแชร์เรื่องราวอะไรเกี่ยวกับน้องแมววันนี้..." autocomplete="off">
                <button type="submit" class="btn-post">แชร์โพสต์</button>
            </div>
        </form>

        <!-- แสดงโพสต์ใหม่ที่ผู้ใช้เพิ่งโพสต์ -->
        <?php if (!empty($_SESSION['user_posts'])): ?>
            <?php foreach ($_SESSION['user_posts'] as $p): ?>
                <div class="feed-card">
                    <div class="feed-header">
                        <div class="feed-user-info">
                            <img src="assets/images/<?= htmlspecialchars($p['avatar']) ?>" onerror="this.src='assets/images/student.jpg';">
                            <div>
                                <strong style="font-size: 15px; color: #333;"><?= htmlspecialchars($p['author']) ?></strong>
                                <div style="font-size: 12px; color: #888;"><?= htmlspecialchars($p['time']) ?></div>
                            </div>
                        </div>
                    </div>
                    <p style="font-size: 14px; line-height: 1.6; color: #333; margin: 10px 0;">
                        <?= htmlspecialchars($p['content']) ?>
                    </p>
                    <div class="post-actions">
                        <button type="button" class="act-btn">💖 ถูกใจ (0)</button>
                        <button type="button" class="act-btn">💬 คอมเมนต์ (0)</button>
                        <button type="button" class="act-btn">🔗 แชร์ลิงก์</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- โพสต์ตัวอย่างประจำระบบ -->
        <div class="feed-card">
            <div class="feed-header">
                <div class="feed-user-info">
                    <img src="assets/images/student.jpg" alt="User Avatar">
                    <div>
                        <strong style="font-size: 15px; color: #333;">CatLover_Official 🐾</strong>
                        <div style="font-size: 12px; color: #888;">2 ชั่วโมงที่แล้ว</div>
                    </div>
                </div>
                <button type="button" style="background:#ff758c; color:white; border:none; padding:6px 14px; border-radius:12px; font-size:12px; cursor:pointer;">+ ติดตาม</button>
            </div>
            <p style="font-size: 14px; line-height: 1.6; color: #333;">
                น้องเหมียวตัวน้อยหาบ้านใหม่ครับ! น้องอายุ 2 เดือน สุขภาพแข็งแรง ขี้อ้อนมาก กินเก่ง ขับถ่ายในกระบะทรายเป็นแล้วครับ ท่านใดสนใจรับเลี้ยงติดต่อได้เลยครับ 💖🐾
            </p>
            <img src="assets/images/cat.png" onerror="this.src='assets/images/student.jpg';" style="width: 100%; max-height: 350px; border-radius: 14px; object-fit: cover; margin-top: 8px;">
            <div class="post-actions">
                <button type="button" class="act-btn">💖 ถูกใจ (24)</button>
                <button type="button" class="act-btn">💬 คอมเมนต์ (5)</button>
                <button type="button" class="act-btn">🔗 แชร์ลิงก์</button>
            </div>
        </div>
    </main>

    <!-- 3. แถบขวามือ: รวมปฏิทินและเวลามาตรฐานเดียวกับทุกหน้า -->
    <?php include 'config/rightbar.php'; ?>
</div>
</body>
</html>