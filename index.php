<?php
require 'auth.php';
require 'config/db.php';
require_login();

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT full_name, email, avatar, status, country FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// กำหนดค่าเดือนและปีปัจจุบัน (รองรับการกดเปลี่ยนดูเดือนในอดีต)
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// คำนวณปฏิทิน
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_day_of_week = date('w', strtotime("$year-$month-01"));
$month_names_th = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>หน้าหลัก Dashboard - Find my meow meow</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        .dashboard-layout {
            display: grid;
            grid-template-columns: 260px 1fr 310px;
            min-height: 100vh;
            background: #fff0f3;
        }
        .dash-sidebar {
            background: #ffffff;
            border-right: 1px solid #ffccd5;
            padding: 25px 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .dash-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            color: #555;
            font-weight: 500;
            transition: background 0.2s, color 0.2s;
        }
        .dash-nav-item.active, .dash-nav-item:hover {
            background: #ff758c;
            color: white;
        }
        .dash-main {
            padding: 20px 30px;
            overflow-y: auto;
        }
        .top-dash-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            padding: 12px 25px;
            border-radius: 16px;
            border: 1px solid #ffccd5;
        }
        .dash-search input {
            padding: 8px 15px 8px 35px;
            border-radius: 20px;
            border: 1px solid #ddd;
            width: 320px;
            background: #fafafa;
        }
        .notice-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .notice-card {
            background: white;
            border-radius: 14px;
            padding: 15px;
            border: 1px solid #ffccd5;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .post-box {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #ffccd5;
            margin-bottom: 20px;
        }
        .post-input-row {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .post-input-row input {
            flex: 1;
            padding: 10px 15px;
            border-radius: 20px;
            border: 1px solid #ddd;
            background: #fafafa;
        }
        .feed-post-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #ffccd5;
            margin-bottom: 20px;
        }
        .dash-right {
            background: #ffffff;
            border-left: 1px solid #ffccd5;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .widget-box {
            background: #fff8f9;
            border-radius: 16px;
            padding: 15px;
            border: 1px solid #ffccd5;
            text-align: center;
        }
        .calendar-table {
            width: 100%;
            font-size: 13px;
            border-collapse: collapse;
            text-align: center;
        }
        .calendar-table td, .calendar-table th {
            padding: 6px;
        }
        .cal-today {
            background: #d81b60;
            color: white;
            border-radius: 50%;
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
        }
        .cal-past {
            color: #333;
            cursor: pointer;
        }
        .cal-future {
            color: #ccc;
            cursor: not-allowed;
        }
        .world-time-list {
            list-style: none;
            padding: 0;
            margin: 0;
            text-align: left;
            font-size: 13px;
        }
        .world-time-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed #ffccd5;
        }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <!-- Sidebar ด้านซ้าย -->
    <aside class="dash-sidebar">
        <div style="font-weight: bold; font-size: 18px; color: #d81b60; margin-bottom: 10px;">🐾 MyCommunity</div>
        <a href="index.php" class="dash-nav-item active">🏠 หน้าแรก</a>
        <a href="profile.php" class="dash-nav-item">👤 ประวัติผู้ใช้งาน</a>
        <a href="#" class="dash-nav-item">📞 ติดต่อผู้ประกาศ</a>
        <a href="#" class="dash-nav-item">⚙️ การตั้งค่า</a>
        <div style="margin-top: auto;">
            <a href="logout.php" class="dash-nav-item" style="background: #ff758c; color: white;">🚪 ออกจากระบบ</a>
        </div>
    </aside>

    <!-- Main Content ตรงกลาง -->
    <main class="dash-main">
        <header class="top-dash-header">
            <div class="dash-search" style="position: relative;">
                <span style="position: absolute; left: 12px; top: 9px;">🔍</span>
                <input type="text" placeholder="ค้นหาโพสต์, ผู้ใช้...">
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <span>🔔 1</span>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <?php $avatar = !empty($current_user['avatar']) ? $current_user['avatar'] : 'student.jpg'; ?>
                    <img src="assets/images/<?= htmlspecialchars($avatar) ?>" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover;">
                    <strong style="font-size: 14px; color: #333;"><?= htmlspecialchars($current_user['full_name']) ?></strong>
                </div>
            </div>
        </header>

        <div class="notice-grid">
            <div class="notice-card"><span>📢</span><div><strong style="font-size: 13px; color: #d81b60;">ข่าวสาร</strong><p style="font-size: 12px; color: #666; margin:0;">อัปเดตล่าสุด</p></div></div>
            <div class="notice-card"><span>📋</span><div><strong style="font-size: 13px; color: #d81b60;">กฎกติกา</strong><p style="font-size: 12px; color: #666; margin:0;">อ่านก่อนใช้งาน</p></div></div>
            <div class="notice-card"><span>💡</span><div><strong style="font-size: 13px; color: #d81b60;">การเข้าเว็บ</strong><p style="font-size: 12px; color: #666; margin:0;">คู่มือการใช้งาน</p></div></div>
        </div>

        <div class="post-box">
            <div class="post-input-row">
                <img src="assets/images/<?= htmlspecialchars($avatar) ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                <input type="text" placeholder="คุณอยากโพสต์อะไรวันนี้...">
                <button type="button" style="background: #d81b60; color: white; border: none; padding: 10px 20px; border-radius: 20px; font-weight: 600; cursor: pointer;">แชร์</button>
            </div>
        </div>

        <div class="feed-post-card">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                <img src="assets/images/student.jpg" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                <div><strong>CatLover</strong><div style="font-size: 11px; color: #888;">2 ชม. ที่แล้ว</div></div>
            </div>
            <p style="font-size: 14px; color: #444;">น้องเหมียวตัวน้อยกับของเล่นชิ้นโปรด เล่นเพลินจนเหนื่อยเลย~ 💖🐾</p>
        </div>
    </main>

    <!-- Right Sidebar ด้านขวา -->
    <aside class="dash-right">
        <div class="widget-box">
            <img src="assets/images/<?= htmlspecialchars($avatar) ?>" style="width: 75px; height: 75px; border-radius: 50%; object-fit: cover; border: 3px solid #ff758c; margin-bottom: 8px;">
            <h3 style="font-size: 16px; color: #333; margin-bottom: 2px;"><?= htmlspecialchars($current_user['full_name']) ?></h3>
            <p style="font-size: 12px; color: #d81b60; margin-bottom: 12px;"><?= htmlspecialchars($current_user['status'] ?? 'สมาชิกทั่วไป') ?></p>
            <a href="profile.php" style="display: inline-block; background: #ffccd5; color: #d81b60; padding: 6px 16px; border-radius: 12px; text-decoration: none; font-size: 13px; font-weight: 600;">ดูโปรไฟล์</a>
        </div>

        <!-- ปฏิทินไดนามิก (ย้อนดูอดีตได้ / ล็อกอนาคต) -->
        <div class="widget-box">
            <?php
                // คำนวณปุ่มเลื่อนเดือนย้อนกลับ / ไปข้างหน้า
                $prev_month = $month - 1; $prev_year = $year;
                if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
                $next_month = $month + 1; $next_year = $year;
                if ($next_month > 12) { $next_month = 1; $next_year++; }
            ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-weight: 600; font-size: 14px; color: #d81b60;">
                <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>" style="text-decoration:none; color:#d81b60;">‹</a>
                <span><?= $month_names_th[$month] ?> <?= $year + 543 ?></span>
                <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>" style="text-decoration:none; color:#d81b60;">›</a>
            </div>
            <table class="calendar-table">
                <tr><th>อา</th><th>จ</th><th>อ</th><th>พ</th><th>พฤ</th><th>ศ</th><th>ส</th></tr>
                <tr>
                <?php
                $current_day = 1;
                $today_d = (int)date('j');
                $today_m = (int)date('n');
                $today_y = (int)date('Y');

                for ($i = 0; $i < 42; $i++) {
                    if ($i >= $first_day_of_week && $current_day <= $days_in_month) {
                        // เช็คว่าเป็นวันปัจจุบัน, อดีต หรืออนาคต
                        $is_today = ($current_day == $today_d && $month == $today_m && $year == $today_y);
                        $is_future = ($year > $today_y) || ($year == $today_y && $month > $today_m) || ($year == $today_y && $month == $today_m && $current_day > $today_d);

                        if ($is_today) {
                            echo "<td><span class='cal-today'>{$current_day}</span></td>";
                        } elseif ($is_future) {
                            echo "<td><span class='cal-future'>{$current_day}</span></td>"; // วันอนาคต (กดไม่ได้)
                        } else {
                            echo "<td><span class='cal-past' title='เลือกดูวันที่นี้'>{$current_day}</span></td>"; // วันในอดีต (กดดูได้)
                        }
                        $current_day++;
                    } else {
                        echo "<td></td>";
                    }
                    if ($i % 7 == 6) echo "</tr><tr>";
                }
                ?>
                </tr>
            </table>
        </div>

        <!-- เวลาปัจจุบันตามโซนเวลาสากลจริง (รองรับปรับเปลี่ยนตามประเทศที่ผู้ใช้เลือก) -->
        <div class="widget-box" style="text-align: left;">
            <h4 style="font-size: 13px; color: #d81b60; margin-bottom: 8px;">🕒 เวลาปัจจุบันตามประเทศ</h4>
            <ul class="world-time-list">
                <li class="world-time-item"><span>🇹🇭 ไทย (UTC+7)</span> <strong id="time-th">--:--</strong></li>
                <li class="world-time-item"><span>🇯🇵 ญี่ปุ่น (UTC+9)</span> <strong id="time-jp">--:--</strong></li>
                <li class="world-time-item"><span>🇺🇸 สหรัฐ (UTC-4)</span> <strong id="time-us">--:--</strong></li>
                <li class="world-time-item"><span>🇬🇧 สหราชอาณาจักร</span> <strong id="time-uk">--:--</strong></li>
            </ul>
        </div>
    </aside>
</div>

<script>
    // Script อัปเดตเวลาสากลแบบเรียลไทม์
    function updateWorldTimes() {
        const now = new Date();
        document.getElementById('time-th').innerText = now.toLocaleTimeString('en-GB', { timeZone: 'Asia/Bangkok', hour: '2-digit', minute: '2-digit' });
        document.getElementById('time-jp').innerText = now.toLocaleTimeString('en-GB', { timeZone: 'Asia/Tokyo', hour: '2-digit', minute: '2-digit' });
        document.getElementById('time-us').innerText = now.toLocaleTimeString('en-GB', { timeZone: 'America/New_York', hour: '2-digit', minute: '2-digit' });
        document.getElementById('time-uk').innerText = now.toLocaleTimeString('en-GB', { timeZone: 'Europe/London', hour: '2-digit', minute: '2-digit' });
    }
    setInterval(updateWorldTimes, 1000);
    updateWorldTimes();
</script>

</body>
</html>