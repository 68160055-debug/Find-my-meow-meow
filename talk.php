<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';  // เดิมเป็น auth.php
require_once 'config/db.php';
require_login();

$user_id = $_SESSION['user_id'];
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_day_of_week = date('w', strtotime("$year-$month-01"));
$month_names_th = [1=>'มกราคม', 2=>'กุมภาพันธ์', 3=>'มีนาคม', 4=>'เมษายน', 5=>'พฤษภาคม', 6=>'มิถุนายน', 7=>'กรกฎาคม', 8=>'สิงหาคม', 9=>'กันยายน', 10=>'ตุลาคม', 11=>'พฤศจิกายน', 12=>'ธันวาคม'];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ติดต่อผู้ขอรับเลี้ยง - Find my meow meow 💖</title>
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
        .chat-container { background: white; border-radius: 20px; border: 1px solid #ffccd5; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); display: flex; flex-direction: column; height: calc(100vh - 100px); }
        .chat-box { flex: 1; overflow-y: auto; padding: 15px; background: #fff8f9; border-radius: 16px; margin: 15px 0; border: 1px solid #ffccd5; }
        .chat-msg { margin-bottom: 12px; display: flex; gap: 10px; }
        .chat-msg.me { flex-direction: row-reverse; }
        .chat-bubble { background: white; padding: 10px 16px; border-radius: 16px; border: 1px solid #ffccd5; max-width: 60%; font-size: 14px; }
        .chat-msg.me .chat-bubble { background: #ff758c; color: white; border: none; }
        .chat-input-row { display: flex; gap: 10px; align-items: center; }
        .chat-input-row input { flex: 1; padding: 12px 20px; border-radius: 20px; border: 1px solid #ffccd5; outline: none; font-family: 'Kanit', sans-serif; }
        .btn-send { background: #d81b60; color: white; border: none; padding: 10px 24px; border-radius: 20px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; }
        .dash-right { background: #ffffff; border-left: 1px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 20px; position: sticky; top: 0; height: 100vh; }
        .widget-box { background: #fff8f9; border-radius: 18px; padding: 18px; border: 1px solid #ffccd5; text-align: center; }
        .calendar-table { width: 100%; font-size: 13px; border-collapse: collapse; text-align: center; margin-top: 10px; }
        .calendar-table td, .calendar-table th { padding: 6px 2px; }
        .cal-today { background: #d81b60; color: white; border-radius: 50%; display: inline-block; width: 24px; height: 24px; line-height: 24px; font-weight: 600; }
        .cal-past { color: #333; cursor: pointer; }
        .cal-future { color: #ccc; cursor: not-allowed; }
        .world-time-list { list-style: none; padding: 0; margin: 0; text-align: left; font-size: 13px; }
        .world-time-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #ffccd5; }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <aside class="dash-sidebar">
        <div class="dash-brand">🐾 Find my meow meow</div>
        <a href="index.php" class="dash-nav-item">🏠 หน้าแรก</a>
        <a href="profile.php" class="dash-nav-item">👤 ประวัติผู้ใช้งาน</a>
        <a href="contact.php" class="dash-nav-item">✉️ ติดต่อผู้ประกาศ/แอดมิน</a>
        <a href="buy_cat.php" class="dash-nav-item">🛒 ซื้อแมว</a>
        <a href="adopter.php" class="dash-nav-item active">💬 ติดต่อผู้ขอรับเลี้ยง</a>
        <a href="settings.php" class="dash-nav-item">⚙️ การตั้งค่า</a>
        <div style="margin-top: auto;">
            <a href="logout.php" class="dash-nav-item" style="background: #fff0f3; color: #d81b60; border: 1px solid #ffccd5;">🚪 ออกจากระบบ</a>
        </div>
    </aside>

    <main class="dash-main">
        <div class="chat-container">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: #d81b60; margin: 0;">💬 แชตพูดคุยกับผู้รับเลี้ยง (เจ้าของแมว)</h3>
                <span style="font-size: 13px; color: #888;">สถานะ: ออนไลน์ 🟢</span>
            </div>

            <div class="chat-box">
                <div class="chat-msg">
                    <img src="assets/images/cat.png" onerror="this.src='assets/images/student.jpg';" style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;">
                    <div class="chat-bubble">สวัสดีครับ สนใจรับเลี้ยงน้องเหมียวไหมครับ? 🐱</div>
                </div>
                <div class="chat-msg me">
                    <div class="chat-bubble">สนใจครับ! น้องกินอาหารเปียกได้หรือยังครับ?</div>
                </div>
            </div>

            <div class="chat-input-row">
                <input type="text" placeholder="พิมพ์ข้อความพูดคุยที่นี่...">
                <button type="button" class="btn-send">ส่งข้อความ</button>
            </div>
        </div>
    </main>

    <aside class="dash-right">
        <div class="widget-box">
            <?php
                $prev_month = $month - 1; $prev_year = $year;
                if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
                $next_month = $month + 1; $next_year = $year;
                if ($next_month > 12) { $next_month = 1; $next_year++; }
            ?>
            <div style="display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 14px; color: #d81b60;">
                <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>" style="text-decoration:none; color:#d81b60; font-size:18px;">‹</a>
                <span><?= $month_names_th[$month] ?> <?= $year + 543 ?></span>
                <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>" style="text-decoration:none; color:#d81b60; font-size:18px;">›</a>
            </div>
            <table class="calendar-table">
                <tr><th>อา</th><th>จ</th><th>อ</th><th>พ</th><th>พฤ</th><th>ศ</th><th>ส</th></tr>
                <tr>
                <?php
                $current_day = 1; $today_d = (int)date('j'); $today_m = (int)date('n'); $today_y = (int)date('Y');
                for ($i = 0; $i < 42; $i++) {
                    if ($i >= $first_day_of_week && $current_day <= $days_in_month) {
                        $is_today = ($current_day == $today_d && $month == $today_m && $year == $today_y);
                        $is_future = ($year > $today_y) || ($year == $today_y && $month > $today_m) || ($year == $today_y && $month == $today_m && $current_day > $today_d);
                        if ($is_today) echo "<td><span class='cal-today'>{$current_day}</span></td>";
                        elseif ($is_future) echo "<td><span class='cal-future'>{$current_day}</span></td>";
                        else echo "<td><span class='cal-past'>{$current_day}</span></td>";
                        $current_day++;
                    } else { echo "<td></td>"; }
                    if ($i % 7 == 6) echo "</tr><tr>";
                }
                ?>
                </tr>
            </table>
        </div>
        <div class="widget-box" style="text-align: left;">
            <h4 style="font-size: 13px; color: #d81b60; margin: 0 0 8px 0;">🕒 เวลาปัจจุบันตามประเทศ</h4>
            <ul class="world-time-list">
                <li class="world-time-item"><span>🇹🇭 ไทย (UTC+7)</span> <strong id="time-th">--:--</strong></li>
                <li class="world-time-item"><span>🇯🇵 ญี่ปุ่น (UTC+9)</span> <strong id="time-jp">--:--</strong></li>
                <li class="world-time-item"><span>🇺🇸 สหรัฐฯ (UTC-4)</span> <strong id="time-us">--:--</strong></li>
                <li class="world-time-item"><span>🇬🇧 สหราชอาณาจักร</span> <strong id="time-uk">--:--</strong></li>
            </ul>
        </div>
    </aside>
</div>

<script>
    function updateWorldTimes() {
        const now = new Date();
        document.getElementById('time-th').innerText = now.toLocaleTimeString('en-GB', { timeZone: 'Asia/Bangkok', hour: '2-digit', minute: '2-digit' });
        document.getElementById('time-jp').innerText = now.toLocaleTimeString('en-GB', { timeZone: 'Asia/Tokyo', hour: '2-digit', minute: '2-digit' });
        document.getElementById('time-us').innerText = now.toLocaleTimeString('en-GB', { timeZone: 'America/New_York', hour: '2-digit', minute: '2-digit' });
        document.getElementById('time-uk').innerText = now.toLocaleTimeString('en-GB', { timeZone: 'Europe/London', hour: '2-digit', minute: '2-digit' });
    }
    setInterval(updateWorldTimes, 1000); updateWorldTimes();
</script>
</body>
</html>