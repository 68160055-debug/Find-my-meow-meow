<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'config/db.php';
require_login();

$user_id = $_SESSION['user_id'] ?? 0;

// ดึงข้อมูลผู้ใช้งานปัจจุบัน
$stmt = $conn->prepare("SELECT full_name, email, avatar, nickname FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$current_avatar = !empty($current_user['avatar']) ? $current_user['avatar'] : 'student.jpg';
$current_name = !empty($current_user['full_name']) ? $current_user['full_name'] : 'ผู้ใช้งาน';

// กำหนดประวัติข้อความเริ่มต้นถ้ายังไม่มี
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [
        ['sender' => 'other', 'name' => 'MeowLover', 'text' => 'สวัสดีค่ะ ยินดีที่ได้คุยกันนะคะ 🌸💕', 'time' => '15:42'],
        ['sender' => 'me', 'name' => $current_name, 'text' => 'ขอบคุณค่ะ มีน้องแมวให้ดูเพิ่มเติมไหมคะ 🥰', 'time' => '15:45'],
        ['sender' => 'other', 'name' => 'MeowLover', 'text' => 'ยินดีค่ะ มีอะไรสอบถามได้เลยนะคะ 😊', 'time' => '15:46']
    ];
}

// ระบบรับและส่งข้อความแชตด้วยเพียว PHP (Form POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_message'])) {
    $msg = trim((string)$_POST['chat_message']);
    if ($msg !== '') {
        $_SESSION['chat_history'][] = [
            'sender' => 'me',
            'name' => $current_name,
            'text' => $msg,
            'time' => date('H:i')
        ];
        header("Location: adopter.php");
        exit();
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แชทติดต่อผู้ขอรับเลี้ยง - Find my meow meow 💖</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff0f5; font-family: 'Kanit', sans-serif; color: #444; }
        .chat-app-layout { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; }
        
        /* แถบเมนูด้านซ้าย */
        .dash-sidebar { background: #ffffff; border-right: 1.5px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 10px; position: sticky; top: 0; height: 100vh; }
        .brand-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .brand-header .paw-icon { font-size: 28px; color: #ff4081; }
        .brand-title { font-weight: bold; font-size: 19px; color: #d81b60; line-height: 1.2; }
        
        .dash-nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 18px; border-radius: 16px; text-decoration: none; color: #555; font-weight: 500; font-size: 14px; transition: all 0.2s; }
        .dash-nav-item.active, .dash-nav-item:hover { background: #ff758c; color: white; box-shadow: 0 4px 12px rgba(255, 117, 140, 0.3); }
        .dash-nav-item .icon { font-size: 18px; }
        
        /* พื้นที่แชตหลัก */
        .chat-main-area { padding: 20px 30px; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        
        /* แถบค้นหาด้านบนสุด */
        .top-search-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 25px; background: #ffffff; border-radius: 20px; border: 1.5px solid #ffccd5; margin-bottom: 18px; box-shadow: 0 4px 15px rgba(255, 117, 140, 0.05); }
        .search-form { display: flex; align-items: center; flex: 1; max-width: 550px; position: relative; }
        .search-form input { width: 100%; padding: 10px 45px 10px 42px; border-radius: 25px; border: 1.5px solid #ffccd5; background: #fff8f9; font-family: 'Kanit'; font-size: 14px; outline: none; }
        .search-form .btn-search { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: #d81b60; color: white; border: none; border-radius: 15px; padding: 5px 14px; font-size: 12px; cursor: pointer; font-family: 'Kanit'; }
        
        /* หน้าต่างแชต */
        .chat-card { background: white; border-radius: 24px; border: 1.5px solid #ffccd5; display: flex; flex-direction: column; flex: 1; box-shadow: 0 6px 20px rgba(0,0,0,0.02); overflow: hidden; }
        .chat-header-bar { padding: 16px 24px; background: #fff8f9; border-bottom: 1.5px solid #ffccd5; display: flex; justify-content: space-between; align-items: center; }
        .chat-user-profile { display: flex; align-items: center; gap: 14px; }
        .chat-avatar-wrap { position: relative; }
        .chat-avatar-wrap img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #ff758c; }
        .online-dot { position: absolute; bottom: 2px; right: 2px; width: 12px; height: 12px; background: #4caf50; border: 2px solid white; border-radius: 50%; }
        
        .chat-body-scroll { flex: 1; overflow-y: auto; padding: 25px; background: #fffafb; display: flex; flex-direction: column; gap: 16px; }
        .msg-row { display: flex; gap: 12px; align-items: flex-end; }
        .msg-row.me { flex-direction: row-reverse; }
        .msg-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        .msg-bubble { max-width: 65%; padding: 12px 18px; border-radius: 20px; font-size: 14px; line-height: 1.5; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .msg-row.other .msg-bubble { background: white; border: 1.5px solid #ffccd5; color: #333; border-bottom-left-radius: 4px; }
        .msg-row.me .msg-bubble { background: #ff6584; color: white; border-bottom-right-radius: 4px; }
        .msg-time { font-size: 11px; color: #999; margin: 0 6px; }
        
        /* แถบพิมพ์ข้อความด้านล่าง */
        .chat-footer-bar { padding: 16px 24px; background: white; border-top: 1.5px solid #ffccd5; }
        .input-form { display: flex; gap: 12px; align-items: center; }
        .chat-text-input { flex: 1; padding: 14px 22px; border-radius: 25px; border: 1.5px solid #ffccd5; background: #fff8f9; font-family: 'Kanit'; font-size: 14px; outline: none; }
        .btn-send-pink { background: #ff4081; color: white; border: none; border-radius: 25px; padding: 12px 28px; font-weight: 600; cursor: pointer; font-family: 'Kanit'; font-size: 14px; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
        .btn-send-pink:hover { background: #d81b60; }
        
        /* สติกเกอร์ข้อความด่วน */
        .quick-replies { display: flex; gap: 8px; margin-top: 10px; overflow-x: auto; padding-bottom: 4px; }
        .btn-quick-sticker { background: #fff0f3; border: 1px solid #ffccd5; color: #d81b60; padding: 5px 14px; border-radius: 15px; font-size: 12px; cursor: pointer; font-family: 'Kanit'; white-space: nowrap; }
    </style>
</head>
<body>

<div class="chat-app-layout">
    <!-- แถบเมนูด้านซ้าย (สไตล์ Meow Meow) -->
    <aside class="dash-sidebar">
        <div class="brand-header">
            <span class="paw-icon">🐾</span>
            <div class="brand-title">Find my<br>meow meow ♡</div>
        </div>
        <a href="index.php" class="dash-nav-item">
            <span class="icon">🏠</span> หน้าแรก (Home)
        </a>
        <a href="profile.php" class="dash-nav-item">
            <span class="icon">👤</span> ประวัติผู้ใช้งาน (Profile)
        </a>
        <a href="contact.php" class="dash-nav-item">
            <span class="icon">✉️</span> ติดต่อผู้ประกาศ/แอดมิน (Contact Us)
        </a>
        <a href="buy_cat.php" class="dash-nav-item">
            <span class="icon">🛒</span> ซื้อแมว (Buy Cat)
        </a>
        <a href="settings.php" class="dash-nav-item">
            <span class="icon">⚙️</span> การตั้งค่า (Settings)
        </a>
        <a href="adopter.php" class="dash-nav-item active">
            <span class="icon">🐾</span> ติดต่อผู้ขอรับเลี้ยง (Adopter)
        </a>
        <div style="margin-top: auto;">
            <a href="logout.php" class="dash-nav-item" style="background: #fff0f3; color: #d81b60; border: 1px solid #ffccd5;">
                <span class="icon">🚪</span> ออกจากระบบ (Logout)
            </a>
        </div>
    </aside>

    <!-- พื้นที่แชตหลัก -->
    <main class="chat-main-area">
        <!-- 1. แถบค้นหาด้านบนสุด (สำหรับค้นหาทุกหน้าตามเงื่อนไข) -->
        <header class="top-search-header">
            <form action="search_results.php" method="GET" class="search-form">
                <span style="position: absolute; left: 16px; font-size: 16px; color: #d81b60;">🔍</span>
                <input type="text" name="q" placeholder="ค้นหาชื่อคน, ชื่อโพสต์, แอดมิน..." required>
                <button type="submit" class="btn-search">ค้นหา</button>
            </form>
            <div style="display: flex; align-items: center; gap: 18px;">
                <span style="font-size: 20px; color: #ff758c; cursor: pointer;">🔔</span>
                <a href="profile.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
                    <img src="assets/images/<?= htmlspecialchars($current_avatar) ?>" onerror="this.src='assets/images/student.jpg';" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #ff758c;">
                    <strong style="font-size: 14px; color: #333;"><?= htmlspecialchars($current_name) ?></strong>
                </a>
            </div>
        </header>

        <!-- 2. หน้าต่างแชต -->
        <div class="chat-card">
            <!-- ส่วนหัวแชต -->
            <div class="chat-header-bar">
                <div class="chat-user-profile">
                    <div class="chat-avatar-wrap">
                        <img src="assets/images/cat.png" onerror="this.src='assets/images/student.jpg';">
                        <span class="online-dot"></span>
                    </div>
                    <div>
                        <strong style="font-size: 16px; color: #d81b60;">MeowLover</strong>
                        <div style="font-size: 12px; color: #4caf50;">● ออนไลน์ (Online)</div>
                    </div>
                </div>
                <div style="font-size: 13px; color: #888;">🐾 แชตหาบ้านให้น้องแมว</div>
            </div>

            <!-- กล่องแสดงข้อความที่แชตคุยกัน -->
            <div class="chat-body-scroll">
                <?php foreach ($_SESSION['chat_history'] as $c): ?>
                    <div class="msg-row <?= $c['sender'] === 'me' ? 'me' : 'other' ?>">
                        <img src="assets/images/<?= $c['sender'] === 'me' ? htmlspecialchars($current_avatar) : 'cat.png' ?>" class="msg-avatar" onerror="this.src='assets/images/student.jpg';">
                        <div class="msg-bubble"><?= htmlspecialchars($c['text']) ?></div>
                        <span class="msg-time"><?= htmlspecialchars($c['time']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- กล่องพิมพ์ส่งข้อความ (เพียว HTML Form POST + PHP) -->
            <div class="chat-footer-bar">
                <form method="POST" action="adopter.php" class="input-form">
                    <input type="text" name="chat_message" class="chat-text-input" placeholder="พิมพ์ข้อความ... / Type a message..." required autocomplete="off">
                    <button type="submit" class="btn-send-pink">ส่งข้อความ ✈</button>
                </form>

                <!-- ปุ่มส่งข้อความด่วน (กดแล้วส่งผ่าน Form ได้ทันที) -->
                <div class="quick-replies">
                    <form method="POST" action="adopter.php" style="display:inline;">
                        <input type="hidden" name="chat_message" value="ขอบคุณค่ะ 🥰">
                        <button type="submit" class="btn-quick-sticker">🥰 ขอบคุณค่ะ</button>
                    </form>
                    <form method="POST" action="adopter.php" style="display:inline;">
                        <input type="hidden" name="chat_message" value="สนใจรับเลี้ยงน้องแมวค่ะ 🐱💕">
                        <button type="submit" class="btn-quick-sticker">🐱 สนใจรับเลี้ยงน้องแมวค่ะ</button>
                    </form>
                    <form method="POST" action="adopter.php" style="display:inline;">
                        <input type="hidden" name="chat_message" value="สะดวกนัดรับแถวไหนคะ? 📍">
                        <button type="submit" class="btn-quick-sticker">📍 สะดวกนัดรับแถวไหนคะ?</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>