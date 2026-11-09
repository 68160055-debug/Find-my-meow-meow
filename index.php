
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Location: Index.php");  // เปลี่ยนจาก index.php เป็น Index.php
require_once 'Auth.php';  // เดิมเป็น auth.php
require_once 'config/db.php';
require_login();

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้งานปัจจุบัน
$stmt = $conn->prepare("SELECT full_name, email, avatar, status, nickname FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// คำนวณปฏิทิน
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

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
    <title>หน้าแรก - Find my meow meow 💖</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff0f3; font-family: 'Kanit', sans-serif; color: #444; }
        
        .dashboard-layout {
            display: grid;
            grid-template-columns: 260px 1fr 310px;
            min-height: 100vh;
        }

        /* 1. Left Sidebar */
        .dash-sidebar {
            background: #ffffff;
            border-right: 1px solid #ffccd5;
            padding: 25px 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            position: sticky;
            top: 0;
            height: 100vh;
        }
        .dash-brand {
            font-weight: bold;
            font-size: 20px;
            color: #d81b60;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dash-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 14px;
            text-decoration: none;
            color: #555;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.2s;
        }
        .dash-nav-item.active, .dash-nav-item:hover {
            background: #ff758c;
            color: white;
            box-shadow: 0 4px 12px rgba(255, 117, 140, 0.3);
        }

        /* 2. Main Content Center */
        .dash-main {
            padding: 25px 30px;
            overflow-y: auto;
        }
        .top-dash-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: white;
            padding: 14px 25px;
            border-radius: 20px;
            border: 1px solid #ffccd5;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        .dash-search {
            position: relative;
        }
        .dash-search input {
            padding: 10px 15px 10px 40px;
            border-radius: 20px;
            border: 1px solid #ffccd5;
            width: 320px;
            background: #fff8f9;
            font-family: 'Kanit', sans-serif;
            outline: none;
        }
        .notice-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .notice-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            border: 1px solid #ffccd5;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        /* Post Box & Feed Cards */
        .post-box {
            background: white;
            border-radius: 20px;
            padding: 20px;
            border: 1px solid #ffccd5;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }
        .post-input-row {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .post-input-row input {
            flex: 1;
            padding: 12px 20px;
            border-radius: 25px;
            border: 1px solid #ffccd5;
            background: #fff8f9;
            font-family: 'Kanit', sans-serif;
            outline: none;
        }
        .btn-post {
            background: #d81b60;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Kanit', sans-serif;
            transition: background 0.2s;
        }
        .btn-post:hover { background: #ad1457; }

        .feed-card {
            background: white;
            border-radius: 20px;
            padding: 22px;
            border: 1px solid #ffccd5;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }
        .feed-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .feed-user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .feed-user-info img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ffb6c1;
        }
        .btn-follow {
            background: #fff0f3;
            color: #d81b60;
            border: 1px solid #ffccd5;
            padding: 6px 14px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-follow:hover { background: #ff758c; color: white; }
        .post-actions {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px solid #fff0f3;
        }
        .act-btn {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-family: 'Kanit', sans-serif;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .act-btn:hover { color: #d81b60; }

        /* 3. Right Sidebar */
        .dash-right {
            background: #ffffff;
            border-left: 1px solid #ffccd5;
            padding: 25px 20px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: sticky;
            top: 0;
            height: 100vh;
        }
        .widget-box {
            background: #fff8f9;
            border-radius: 18px;
            padding: 18px;
            border: 1px solid #ffccd5;
            text-align: center;
        }
        .calendar-table {
            width: 100%;
            font-size: 13px;
            border-collapse: collapse;
            text-align: center;
            margin-top: 10px;
        }
        .calendar-table td, .calendar-table th { padding: 6px 2px; }
        .cal-today {
            background: #d81b60;
            color: white;
            border-radius: 50%;
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            font-weight: 600;
        }
        .cal-past { color: #333; cursor: pointer; }
        .cal-future { color: #ccc; cursor: not-allowed; }
        .world-time-list { list-style: none; padding: 0; margin: 0; text-align: left; font-size: 13px; }
        .world-time-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #ffccd5;
        }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <!-- แถบซ้ายมือ (Sidebar) -->
    <aside class="dash-sidebar">
        <div class="dash-brand">🐾 Find my meow meow</div>
        <a href="index.php" class="dash-nav-item active">🏠 หน้าแรก</a>
        <a href="profile.php" class="dash-nav-item">👤 ประวัติผู้ใช้งาน</a>
        <a href="contact.php" class="dash-nav-item">✉️ ติดต่อผู้ประกาศ/แอดมิน</a>
        <a href="buy_cat.php" class="dash-nav-item">🛒 ซื้อแมว</a>
        <a href="adopter.php" class="dash-nav-item">💬 ติดต่อผู้ขอรับเลี้ยง</a>
        <a href="settings.php" class="dash-nav-item">⚙️ การตั้งค่า</a>
        
        <div style="margin-top: auto;">
            <a href="logout.php" class="dash-nav-item" style="background: #fff0f3; color: #d81b60; border: 1px solid #ffccd5;">🚪 ออกจากระบบ</a>
        </div>
    </aside>

    <!-- พื้นที่ตรงกลาง (Main Content) -->
    <main class="dash-main">
        <header class="top-dash-header">
            <div class="dash-search">
                <span style="position: absolute; left: 14px; top: 10px; color: #aaa;">🔍</span>
                <input type="text" placeholder="ค้นหาโพสต์, ชื่อผู้ใช้...">
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <span style="font-size: 18px; cursor: pointer;">🔔</span>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php $avatar_img = !empty($current_user['avatar']) ? $current_user['avatar'] : 'student.jpg'; ?>
                    <img src="assets/images/<?= htmlspecialchars($avatar_img) ?>" onerror="this.src='assets/images/student.jpg';" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #ff758c;">
                    <strong style="font-size: 14px; color: #333;"><?= htmlspecialchars($current_user['full_name']) ?></strong>
                </div>
            </div>
        </header>

        <!-- โพสต์สาธารณะ -->
        <div class="post-box">
            <div class="post-input-row">
                <img src="assets/images/<?= htmlspecialchars($avatar_img) ?>" onerror="this.src='assets/images/student.jpg';" style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover;">
                <input type="text" placeholder="คุณอยากแชร์เรื่องราวอะไรเกี่ยวกับน้องแมววันนี้...">
                <button type="button" class="btn-post">แชร์โพสต์</button>
            </div>
        </div>

        <!-- รายการโพสต์ตัวอย่าง -->
        <div class="feed-card">
            <div class="feed-header">
                <div class="feed-user-info">
                    <img src="assets/images/student.jpg" alt="User Avatar">
                    <div>
                        <strong style="font-size: 15px;">CatLover_Official 🐾</strong>
                        <div style="font-size: 12px; color: #888;">2 ชั่วโมงที่แล้ว</div>
                    </div>
                </div>
                <button class="btn-follow">+ ติดตาม</button>
            </div>
            <p style="font-size: 14px; line-height: 1.6; color: #333;">
                น้องเหมียวตัวน้อยหาบ้านใหม่ครับ! น้องอายุ 2 เดือน สุขภาพแข็งแรง ขี้อ้อนมาก กินเก่ง ขับถ่ายในกระบะทรายเป็นแล้วครับ ท่านใดสนใจรับเลี้ยงติดต่อได้เลยครับ 💖🐾
            </p>
            <img src="assets/images/cat.png" onerror="this.src='assets/images/student.jpg';" style="width: 100%; max-height: 350px; border-radius: 14px; object-fit: cover; margin-top: 8px;">
            <div class="post-actions">
                <button class="act-btn">💖 ถูกใจ (24)</button>
                <button class="act-btn">💬 คอมเมนต์ (5)</button>
                <button class="act-btn">🔗 แชร์ลิงก์</button>
            </div>
        </div>
    </main>

    <!-- แถบขวามือ (Right Sidebar) -->
    <aside class="dash-right">
        <!-- การ์ดโปรไฟล์ย่อ -->
        <div class="widget-box">
            <img src="assets/images/<?= htmlspecialchars($avatar_img) ?>" onerror="this.src='assets/images/student.jpg';" style="width: 75px; height: 75px; border-radius: 50%; object-fit: cover; border: 3px solid #ff758c; margin-bottom: 8px;">
            <h3 style="font-size: 16px; color: #333; margin: 0 0 4px 0;"><?= htmlspecialchars($current_user['full_name']) ?></h3>
            <p style="font-size: 12px; color: #d81b60; margin: 0 0 12px 0;"><?= htmlspecialchars($current_user['status'] ?? 'สมาชิกทั่วไป') ?></p>
            <a href="profile.php" style="display: inline-block; background: #ffccd5; color: #d81b60; padding: 6px 18px; border-radius: 14px; text-decoration: none; font-size: 13px; font-weight: 600;">ดูโปรไฟล์</a>
        </div>

        <!-- ปฏิทิน -->
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
                $current_day = 1;
                $today_d = (int)date('j');
                $today_m = (int)date('n');
                $today_y = (int)date('Y');

                for ($i = 0; $i < 42; $i++) {
                    if ($i >= $first_day_of_week && $current_day <= $days_in_month) {
                        $is_today = ($current_day == $today_d && $month == $today_m && $year == $today_y);
                        $is_future = ($year > $today_y) || ($year == $today_y && $month > $today_m) || ($year == $today_y && $month == $today_m && $current_day > $today_d);

                        if ($is_today) {
                            echo "<td><span class='cal-today'>{$current_day}</span></td>";
                        } elseif ($is_future) {
                            echo "<td><span class='cal-future'>{$current_day}</span></td>";
                        } else {
                            echo "<td><span class='cal-past'>{$current_day}</span></td>";
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

        <!-- เวลาปัจจุบันตามประเทศ -->
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
    setInterval(updateWorldTimes, 1000);
    updateWorldTimes();
</script>

</body>
</html>