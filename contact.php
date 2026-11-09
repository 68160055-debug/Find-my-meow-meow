<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';  // เดิมเป็น auth.php
require_once 'config/db.php';
require_login();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_auth_token();
    $message = 'ส่งข้อมูลเรียบร้อยแล้ว! ทีมงานจะรีบติดต่อกลับโดยเร็วที่สุด';
}

$stmt = $conn->prepare("SELECT full_name, email, avatar FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

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
    <title>ติดต่อผู้ประกาศ/แอดมิน - Find my meow meow 💖</title>
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
        .contact-card { background: white; border-radius: 20px; padding: 30px; border: 1px solid #ffccd5; box-shadow: 0 4px 15px rgba(0,0,0,0.02); margin-bottom: 25px; }
        .form-section-title { font-size: 16px; font-weight: 600; color: #d81b60; margin: 20px 0 12px; border-bottom: 2px dashed #ffccd5; padding-bottom: 6px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 12px; }
        .form-group.full { grid-column: span 2; }
        .form-group label { display: block; font-weight: 600; font-size: 14px; color: #555; margin-bottom: 6px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 11px 14px; border: 1px solid #ffccd5; border-radius: 12px; font-size: 14px; background: #fff8f9; font-family: 'Kanit', sans-serif; outline: none; }
        .form-group textarea { resize: vertical; height: 90px; }
        .btn-submit-contact { background: #d81b60; color: white; border: none; padding: 12px 30px; border-radius: 20px; font-weight: 600; font-size: 15px; cursor: pointer; width: 100%; margin-top: 15px; font-family: 'Kanit', sans-serif; }
        .btn-submit-contact:hover { background: #ad1457; }
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
        <a href="contact.php" class="dash-nav-item active">✉️ ติดต่อผู้ประกาศ/แอดมิน</a>
        <a href="buy_cat.php" class="dash-nav-item">🛒 ซื้อแมว</a>
        <a href="adopter.php" class="dash-nav-item">💬 ติดต่อผู้ขอรับเลี้ยง</a>
        <a href="settings.php" class="dash-nav-item">⚙️ การตั้งค่า</a>
        <div style="margin-top: auto;">
            <a href="logout.php" class="dash-nav-item" style="background: #fff0f3; color: #d81b60; border: 1px solid #ffccd5;">🚪 ออกจากระบบ</a>
        </div>
    </aside>

    <main class="dash-main">
        <div class="contact-card">
            <h2 style="color: #d81b60; margin-top: 0;">✉️ ติดต่อผู้ประกาศ/แอดมิน (Contact Us)</h2>
            <p style="color: #777; font-size: 14px;">แบบฟอร์ม Find my meow meow สำหรับกรอกข้อมูลส่วนบุคคลของผู้ใช้งานและข้อมูลน้องแมว 💖</p>
            
            <?php if($message): ?><p style="color: green; font-size: 14px; background: #e8f5e9; padding: 10px; border-radius: 10px;"><?=auth_escape($message)?></p><?php endif; ?>

            <form method="post">
                <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
                
                <div class="form-section-title">👤 ข้อมูลผู้ใช้งาน (User Information)</div>
                <div class="form-grid">
                    <div class="form-group full">
                        <label>1. ชื่อ-นามสกุล *</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($current_user['full_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>2. เพศ *</label>
                        <select name="gender">
                            <option value="female">หญิง (Female)</option>
                            <option value="male">ชาย (Male)</option>
                            <option value="other">อื่นๆ</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>3. สถานะ/อาชีพ *</label>
                        <input type="text" name="status" placeholder="เช่น นักเรียน/นักศึกษา">
                    </div>
                </div>

                <div class="form-section-title">🐾 ข้อมูลเกี่ยวกับแมว (Data Cat)</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>4. สายพันธุ์ของแมว *</label>
                        <select name="breed">
                            <option value="เปอร์เซีย">เปอร์เซีย (Persian)</option>
                            <option value="บริติช ชอร์ตแฮร์">บริติช ชอร์ตแฮร์ (British Shorthair)</option>
                            <option value="สก็อตติช โฟลด์">สก็อตติช โฟลด์ (Scottish Fold)</option>
                            <option value="แมวไทย/ผสม">แมวไทย / แมวผสม</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>5. สถานะของแมว *</label>
                        <select name="cat_status">
                            <option value="ยังหาบ้านอยู่">ยังหาบ้านอยู่</option>
                            <option value="ได้บ้านแล้ว">ได้บ้านแล้ว</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>6. รายละเอียดพฤติกรรมของแมว *</label>
                        <textarea name="behavior" placeholder="เช่น นิสัย ชอบเล่น ขี้กลัว เป็นต้น"></textarea>
                    </div>
                </div>

                <button type="submit" class="btn-submit-contact">🚀 ส่งข้อมูล / Submit</button>
            </form>
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