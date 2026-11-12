<?php
// adopter.php - Full Complete File
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'config/db.php';
require_once 'config/lang.php'; // เพิ่มบรรทัดนี้
require_login();

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$stmt = $conn->prepare("SELECT full_name, email, avatar, nickname FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$current_avatar = !empty($current_user['avatar']) ? $current_user['avatar'] : 'student.jpg';
$current_name = !empty($current_user['full_name']) ? $current_user['full_name'] : 'ผู้ใช้งาน';

// รับค่าเมื่อมีการส่งข้อความแชตใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_message'])) {
    $msg = trim((string)$_POST['chat_message']);
    if ($msg !== '') {
        if (!isset($_SESSION['chat_history_raw'])) {
            $_SESSION['chat_history_raw'] = [
                ['sender_id' => 2, 'receiver_id' => $user_id, 'content' => 'สวัสดีค่ะ ยินดีที่ได้คุยกันนะคะ 🌸💕', 'type' => 'text', 'file_url' => '', 'time' => '15:42'],
                ['sender_id' => $user_id, 'receiver_id' => 2, 'content' => 'ขอบคุณสำหรับคำแนะนำค่ะ 😊', 'type' => 'text', 'file_url' => '', 'time' => '15:42'],
                ['sender_id' => 2, 'receiver_id' => $user_id, 'content' => 'ยินดีค่ะ มีอะไรสอบถามได้เลยนะคะ', 'type' => 'text', 'file_url' => '', 'time' => '15:43']
            ];
        }
        $_SESSION['chat_history_raw'][] = [
            'sender_id' => $user_id,
            'receiver_id' => 2,
            'content' => $msg,
            'type' => 'text',
            'file_url' => '',
            'time' => date('H:i')
        ];
        header("Location: adopter.php");
        exit();
    }
}

// 1. ดึงรายชื่อเพื่อนจากฐานข้อมูล MySQL (ถ้าไม่มีเพื่อนจะเป็น [])
$friends_query = $conn->query("SELECT id, full_name as name, avatar FROM users WHERE id != $user_id LIMIT 3");
$friends_data = [];
if ($friends_query) {
    while ($row = $friends_query->fetch_assoc()) {
        $friends_data[] = $row;
    }
}

// 2. ดึงประวัติข้อความจาก MySQL หรือ Session
$messages_data = $_SESSION['chat_history_raw'] ?? [
    ['sender_id' => 2, 'receiver_id' => $user_id, 'content' => 'สวัสดีค่ะ ยินดีที่ได้คุยกันนะคะ 🌸💕', 'type' => 'text', 'file_url' => '', 'time' => '15:42'],
    ['sender_id' => $user_id, 'receiver_id' => 2, 'content' => 'ขอบคุณสำหรับคำแนะนำค่ะ 😊', 'type' => 'text', 'file_url' => '', 'time' => '15:42'],
    ['sender_id' => 2, 'receiver_id' => $user_id, 'content' => 'ยินดีค่ะ มีอะไรสอบถามได้เลยนะคะ', 'type' => 'text', 'file_url' => '', 'time' => '15:43']
];

// 3. แปลงเป็น JSON และเรียกใช้ Python Engine (พร้อมระบบสำรองหากไม่ได้รัน Python)
$arg_friends = escapeshellarg(json_encode($friends_data));
$arg_messages = escapeshellarg(json_encode($messages_data));

$cmd = "python3 chat_logic.py $user_id $arg_friends $arg_messages";
$python_output = @shell_exec($cmd);
$app_state = json_decode((string)$python_output, true);

// กำหนดกรณี 1 (ยังไม่มีเพื่อน) หรือ 2 (มีเพื่อนแล้ว) จากผลลัพธ์ของ Python OOP
if (isset($app_state['case'])) {
    $case = (int)$app_state['case'];
    $friends_list = $app_state['friends_list'] ?? [];
    $chat_history = $app_state['chat_history'] ?? [];
} else {
    // โหมดสำรอง: ถ้ายังไม่ได้สร้างไฟล์ chat_logic.py จะตัดสินใจจากจำนวนเพื่อนอัตโนมัติ
    $case = (count($friends_data) > 0) ? 2 : 1;
    $friends_list = $friends_data;
    $chat_history = $messages_data;
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
        .dashboard-layout { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; }
        
        /* แถบเมนูด้านซ้าย */
        .dash-sidebar { background: #ffffff; border-right: 1.5px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 10px; position: sticky; top: 0; height: 100vh; }
        .brand-header { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .brand-header .paw-icon { font-size: 28px; color: #ff4081; }
        .brand-title { font-weight: bold; font-size: 19px; color: #d81b60; line-height: 1.2; }
        
        .dash-nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 18px; border-radius: 16px; text-decoration: none; color: #555; font-weight: 500; font-size: 14px; transition: all 0.2s; }
        .dash-nav-item.active, .dash-nav-item:hover { background: #ff758c; color: white; box-shadow: 0 4px 12px rgba(255, 117, 140, 0.3); }
        .dash-nav-item .icon { font-size: 18px; }
        
        /* พื้นที่แสดงผลตรงกลาง */
        .dash-main { padding: 20px 30px; display: flex; flex-direction: column; height: 100vh; overflow-y: auto; }
        
        /* แถบค้นหาด้านบนสุด */
        .top-search-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 25px; background: #ffffff; border-radius: 20px; border: 1.5px solid #ffccd5; margin-bottom: 18px; box-shadow: 0 4px 15px rgba(255, 117, 140, 0.05); }
        .search-form { display: flex; align-items: center; flex: 1; max-width: 550px; position: relative; }
        .search-form input { width: 100%; padding: 10px 45px 10px 42px; border-radius: 25px; border: 1.5px solid #ffccd5; background: #fff8f9; font-family: 'Kanit'; font-size: 14px; outline: none; }
        .search-form .btn-search { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: #d81b60; color: white; border: none; border-radius: 15px; padding: 5px 14px; font-size: 12px; cursor: pointer; font-family: 'Kanit'; }
        
        /* กล่องสไตล์สำหรับกรณีที่ 1: ยังไม่มีเพื่อน */
        .empty-friends-card { background: white; border-radius: 24px; border: 1.5px solid #ffccd5; padding: 60px 20px; text-align: center; box-shadow: 0 6px 20px rgba(0,0,0,0.02); }
        .btn-add-friend-main { background: #ff4081; color: white; border: none; padding: 14px 35px; border-radius: 25px; font-family: 'Kanit'; font-size: 15px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(255, 64, 129, 0.3); }
        
        /* กล่องสไตล์สำหรับกรณีที่ 2: มีเพื่อนและแชต */
        .chat-active-card { background: white; border-radius: 24px; border: 1.5px solid #ffccd5; display: grid; grid-template-columns: 280px 1fr; flex: 1; min-height: 550px; overflow: hidden; box-shadow: 0 6px 20px rgba(0,0,0,0.02); }
        .friend-sidebar { border-right: 1.5px solid #ffccd5; background: #fffafb; padding: 15px; display: flex; flex-direction: column; gap: 8px; }
        .friend-row { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: 16px; background: white; border: 1px solid #ffccd5; cursor: pointer; }
        .friend-row.active { background: #ffe4e8; border-color: #ff758c; }
        
        .chat-view-area { display: flex; flex-direction: column; height: 100%; }
        .chat-header-bar { padding: 15px 22px; background: #fff8f9; border-bottom: 1.5px solid #ffccd5; display: flex; justify-content: space-between; align-items: center; }
        .chat-messages-box { flex: 1; overflow-y: auto; padding: 20px; background: #fffdfd; display: flex; flex-direction: column; gap: 14px; }
        .msg-bubble { max-width: 65%; padding: 10px 16px; border-radius: 18px; font-size: 14px; line-height: 1.5; }
        .msg-row-me { display: flex; justify-content: flex-end; }
        .msg-row-me .msg-bubble { background: #ff6584; color: white; border-bottom-right-radius: 4px; }
        .msg-row-other { display: flex; justify-content: flex-start; }
        .msg-row-other .msg-bubble { background: white; border: 1.5px solid #ffccd5; color: #333; border-bottom-left-radius: 4px; }
        
        .chat-input-bar { padding: 14px 20px; background: white; border-top: 1.5px solid #ffccd5; display: flex; gap: 10px; align-items: center; }
        .chat-text-input { flex: 1; padding: 12px 18px; border-radius: 20px; border: 1.5px solid #ffccd5; background: #fff8f9; font-family: 'Kanit'; font-size: 14px; outline: none; }
        .btn-send-pink { background: #ff4081; color: white; border: none; border-radius: 20px; padding: 11px 24px; font-weight: 600; cursor: pointer; font-family: 'Kanit'; font-size: 14px; }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <!-- แถบเมนูด้านซ้าย -->
    <aside class="dash-sidebar">
        <div class="brand-header">
            <span class="paw-icon">🐾</span>
            <div class="brand-title">Find my<br>meow meow ♡</div>
        </div>
        <a href="index.php" class="dash-nav-item"><span class="icon">🏠</span> หน้าแรก (Home)</a>
        <a href="profile.php" class="dash-nav-item"><span class="icon">👤</span> ประวัติผู้ใช้งาน (Profile)</a>
        <a href="contact.php" class="dash-nav-item"><span class="icon">✉️</span> ติดต่อผู้ประกาศ/แอดมิน</a>
        <a href="buy_cat.php" class="dash-nav-item"><span class="icon">🛒</span> ซื้อแมว (Buy Cat)</a>
        <a href="settings.php" class="dash-nav-item"><span class="icon">⚙️</span> การตั้งค่า (Settings)</a>
        <a href="adopter.php" class="dash-nav-item active"><span class="icon">🐾</span> ติดต่อผู้ขอรับเลี้ยง (Adopter)</a>
        <div style="margin-top: auto;">
            <a href="logout.php" class="dash-nav-item" style="background: #fff0f3; color: #d81b60; border: 1px solid #ffccd5;"><span class="icon">🚪</span> ออกจากระบบ</a>
        </div>
    </aside>

    <!-- พื้นที่แสดงผลตรงกลาง -->
    <main class="dash-main">
        <!-- แถบค้นหาด้านบนสุด (มีทุกหน้าตามเงื่อนไข) -->
        <header class="top-search-header">
            <form action="search_results.php" method="GET" class="search-form">
                <span style="position: absolute; left: 16px; font-size: 16px; color: #d81b60;">🔍</span>
                <input type="text" name="q" placeholder="ค้นหาเพื่อน... / Search friends..." required>
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

        <!-- ตรวจสอบเงื่อนไขจาก Python OOP -->
        <?php if ($case === 1): ?>
            <!-- ========================================== -->
            <!-- กรณีที่ 1: ยังไม่ได้เพิ่มเพื่อน (หน้าจอฝั่งซ้ายในรูป) -->
            <!-- ========================================== -->
            <div class="empty-friends-card">
                <div style="font-size: 55px; margin-bottom: 10px;">🐱💖</div>
                <h2 style="color: #d81b60; margin: 0; font-size: 24px;">ยังไม่มีเพื่อนในรายการ</h2>
                <p style="color: #888; font-size: 14px; margin: 4px 0 16px 0;">No friends yet</p>
                <p style="color: #666; font-size: 15px; line-height: 1.6; margin-bottom: 25px;">
                    ค้นหาเพื่อน หรือ เพิ่มเพื่อนใหม่ เพื่อเริ่มการแชทกันเลย!<br>
                    <small style="color: #999;">Search for friends or add new friends to start chatting!</small>
                </p>
                <form action="search_results.php" method="GET">
                    <button type="submit" class="btn-add-friend-main">👤+ เพิ่มเพื่อน / Add Friend</button>
                </form>
            </div>

        <?php else: ?>
            <!-- ========================================== -->
            <!-- กรณีที่ 2: เพิ่มเพื่อนแล้วและเข้าสู่แชต (หน้าจอฝั่งขวาในรูป) -->
            <!-- ========================================== -->
            <div class="chat-active-card">
                <!-- ฝั่งซ้าย: รายชื่อเพื่อน -->
                <div class="friend-sidebar">
                    <h4 style="margin: 0 0 10px 0; color: #d81b60; font-size: 15px;">🐾 รายชื่อเพื่อน</h4>
                    <?php foreach ($friends_list as $f): ?>
                        <div class="friend-row active">
                            <img src="assets/images/cat.png" style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover;">
                            <div style="flex: 1;">
                                <strong style="font-size: 14px; color: #333; display: block;"><?= htmlspecialchars($f['name']) ?></strong>
                                <small style="color: #888;">ขอบคุณค่ะ 💕</small>
                            </div>
                            <span style="font-size: 11px; color: #4caf50;">● ออนไลน์</span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ฝั่งขวา: ห้องแชต -->
                <div class="chat-view-area">
                    <div class="chat-header-bar">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <img src="assets/images/cat.png" style="width: 40px; height: 40px; border-radius: 50%;">
                            <div>
                                <strong style="color: #d81b60; font-size: 15px;">MeowLover</strong>
                                <div style="font-size: 11px; color: #4caf50;">● ออนไลน์ (Online)</div>
                            </div>
                        </div>
                    </div>

                    <!-- กล่องแสดงข้อความสนทนา -->
                    <div class="chat-messages-box">
                        <div style="text-align: center; margin-bottom: 10px;">
                            <span style="background: #fff0f3; padding: 4px 14px; border-radius: 12px; font-size: 12px; color: #888;">วันนี้ <?= date('H:i') ?></span>
                        </div>

                        <?php foreach ($chat_history as $msg): ?>
                            <?php $is_me = ($msg['sender_id'] == $user_id || ($msg['sender'] ?? '') === 'me'); ?>
                            <div class="<?= $is_me ? 'msg-row-me' : 'msg-row-other' ?>">
                                <div class="msg-bubble">
                                    <?= htmlspecialchars($msg['content'] ?? ($msg['text'] ?? '')) ?>
                                    <div style="font-size: 10px; opacity: 0.8; text-align: right; margin-top: 4px;">
                                        <?= htmlspecialchars($msg['time'] ?? date('H:i')) ?> <?= $is_me ? '✓✓' : '' ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ช่องพิมพ์ข้อความส่งผ่าน Form POST -->
                    <form method="POST" action="adopter.php" class="chat-input-bar">
                        <input type="text" name="chat_message" class="chat-text-input" placeholder="พิมพ์ข้อความ... / Type a message..." required autocomplete="off">
                        <button type="submit" class="btn-send-pink">ส่ง ✈</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>
</body>
</html>