<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'config/db.php';
require_login();

<main class="dash-main">
    <!-- แถบค้นหา Google Search Header ด้านบนสุด -->
    <?php include 'config/search.php'; ?>

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_auth_token();
    if (isset($_POST['send_admin_msg'])) {
        $admin_msg = trim((string)($_POST['admin_msg'] ?? ''));
        if ($admin_msg !== '') {
            $message = 'ส่งข้อความถึงแอดมินเรียบร้อยแล้ว!';
        } else {
            $error = 'กรุณากรอกข้อความก่อนส่ง';
        }
    }
}

$stmt = $conn->prepare("SELECT full_name, email, avatar FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// คำนวณปฏิทิน
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
    <title>การตั้งค่า - Find my meow meow 💖</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff0f3; font-family: 'Kanit', sans-serif; color: #444; }
        .dashboard-layout { display: grid; grid-template-columns: 260px 1fr 310px; min-height: 100vh; }
        
        /* Left Sidebar */
        .dash-sidebar { background: #ffffff; border-right: 1px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 12px; position: sticky; top: 0; height: 100vh; }
        .dash-brand { font-weight: bold; font-size: 20px; color: #d81b60; margin-bottom: 15px; }
        .dash-nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 14px; text-decoration: none; color: #555; font-weight: 500; font-size: 15px; transition: all 0.2s; }
        .dash-nav-item.active, .dash-nav-item:hover { background: #ff758c; color: white; box-shadow: 0 4px 12px rgba(255, 117, 140, 0.3); }
        
        /* Main Content */
        .dash-main { padding: 25px 30px; overflow-y: auto; }
        .settings-card { background: white; border-radius: 20px; padding: 30px; border: 1px solid #ffccd5; box-shadow: 0 4px 15px rgba(0,0,0,0.02); margin-bottom: 20px; }
        .section-header { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 600; color: #d81b60; margin-bottom: 15px; border-bottom: 2px dashed #ffccd5; padding-bottom: 8px; }
        
        .setting-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #fff0f3; }
        .setting-info h5 { margin: 0 0 4px 0; font-size: 15px; color: #333; }
        .setting-info p { margin: 0; font-size: 12px; color: #777; }
        
        .lang-select { padding: 8px 15px; border-radius: 12px; border: 1px solid #ffccd5; font-family: 'Kanit', sans-serif; background: #fff8f9; outline: none; }
        .admin-textarea { width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #ffccd5; background: #fff8f9; font-family: 'Kanit', sans-serif; resize: vertical; height: 90px; outline: none; margin-top: 10px; }
        .btn-send-msg { background: #d81b60; color: white; border: none; padding: 10px 24px; border-radius: 14px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; margin-top: 10px; }
        .btn-logout-big { background: #ff758c; color: white; border: none; padding: 12px 30px; border-radius: 14px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; width: 100%; }
        
        /* Right Sidebar */
        .dash-right { background: #ffffff; border-left: 1px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 20px; position: sticky; top: 0; height: 100vh; }
        .widget-box { background: #fff8f9; border-radius: 18px; padding: 18px; border: 1px solid #ffccd5; text-align: center; }
        .calendar-table { width: 100%; font-size: 13px; border-collapse: collapse; text-align: center; margin-top: 10px; }
        .calendar-table td, .calendar-table th { padding: 6px 2px; }
        .cal-today { background: #d81b60; color: white; border-radius: 50%; display: inline-block; width: 24px; height: 24px; line-height: 24px; font-weight: 600; }
        .cal-past { color: #333; cursor: pointer; }
        .cal-future { color: #ccc; cursor: not-allowed; }
        .world-time-list { list-style: none; padding: 0; margin: 0; text-align: left; font-size: 13px; }
        .world-time-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #ffccd5; }

        /* Modal ยืนยันออกจากระบบ */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); z-index: 9999; justify-content: center; align-items: center; }
        .modal-box { background: white; border-radius: 20px; padding: 30px; width: 90%; max-width: 380px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        .modal-actions { display: flex; gap: 12px; justify-content: center; margin-top: 20px; }
        .btn-yes { background: #ff758c; color: white; border: none; padding: 10px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; }
        .btn-cancel { background: #fff; color: #d81b60; border: 1px solid #ff758c; padding: 10px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <!-- แถบซ้ายมือ -->
    <aside class="dash-sidebar">
        <div class="dash-brand">🐾 Find my meow meow</div>
        <a href="index.php" class="dash-nav-item">🏠 หน้าแรก</a>
        <a href="profile.php" class="dash-nav-item">👤 ประวัติผู้ใช้งาน</a>
        <a href="contact.php" class="dash-nav-item">✉️ ติดต่อผู้ประกาศ/แอดมิน</a>
        <a href="buy_cat.php" class="dash-nav-item">🛒 ซื้อแมว</a>
        <a href="adopter.php" class="dash-nav-item">💬 ติดต่อผู้ขอรับเลี้ยง</a>
        <a href="settings.php" class="dash-nav-item active">⚙️ การตั้งค่า</a>
        <div style="margin-top: auto;">
            <a href="logout.php" class="dash-nav-item" style="background: #fff0f3; color: #d81b60; border: 1px solid #ffccd5;">🚪 ออกจากระบบ</a>
        </div>
    </aside>

    <!-- ตรงกลาง -->
    <main class="dash-main">
        <h2 style="color: #d81b60; margin-top: 0; margin-bottom: 20px;">⚙️ การตั้งค่า (Settings)</h2>

        <?php if($message): ?><p style="color: green; font-size: 14px; background: #e8f5e9; padding: 10px; border-radius: 10px;"><?=auth_escape($message)?></p><?php endif; ?>
        <?php if($error): ?><p style="color: red; font-size: 14px; background: #ffebee; padding: 10px; border-radius: 10px;"><?=auth_escape($error)?></p><?php endif; ?>

        <!-- 1. ความเป็นส่วนตัว & ความปลอดภัย -->
        <div class="settings-card">
            <div class="section-header">🔒 ความเป็นส่วนตัว & ความปลอดภัย</div>
            <div class="setting-row">
                <div class="setting-info">
                    <h5>การตั้งค่าความเป็นส่วนตัว</h5>
                    <p>ข้อมูลส่วนบุคคล เช่น อีเมลและรหัสผ่าน จะมีแค่คุณและแอดมินเท่านั้นที่เห็น</p>
                </div>
                <span>🔒</span>
            </div>
            <div class="setting-row">
                <div class="setting-info">
                    <h5>การมองเห็นข้อมูลบัญชี</h5>
                    <p>จัดการการแสดงผลโปรไฟล์ต่อสาธารณะ</p>
                </div>
                <span>👁️</span>
            </div>
        </div>

        <!-- 2. การตั้งค่าภาษา -->
        <div class="settings-card">
            <div class="section-header">🌐 การตั้งค่าภาษา</div>
            <div class="setting-row">
                <div class="setting-info">
                    <h5>เปลี่ยนภาษา (Select Language)</h5>
                    <p>เลือกภาษาที่คุณต้องการใช้งานในระบบ</p>
                </div>
                <select class="lang-select">
                    <option value="th">🇹🇭 ไทย (TH)</option>
                    <option value="en">🇬🇧 English (EN)</option>
                </select>
            </div>
        </div>

        <!-- 3. ติดต่อแอดมิน -->
        <div class="settings-card">
            <div class="section-header">📢 ติดต่อแอดมิน / ผู้ดูแลระบบ</div>
            <p style="font-size: 13px; color: #666; margin-top: 0;">ส่งข้อความส่วนตัวถึงแอดมินเพื่อสอบถามปัญหาหรือขอความช่วยเหลือ</p>
            <form method="post">
                <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
                <textarea name="admin_msg" class="admin-textarea" placeholder="พิมพ์ข้อความที่ต้องการส่งถึงแอดมินที่นี่..."></textarea>
                <div style="text-align: right;">
                    <button type="submit" name="send_admin_msg" class="btn-send-msg">📨 ส่งข้อความ</button>
                </div>
            </form>
        </div>

        <!-- 4. บัญชีและการออกจากระบบ -->
        <div class="settings-card">
            <div class="section-header">⚠️ บัญชี & การตั้งค่า</div>
            <p style="font-size: 14px; color: #666;">กำลังจะออกจากระบบหรือไม่?</p>
            <button type="button" class="btn-logout-big" onclick="showLogoutModal()">🚪 ออกจากระบบ</button>
        </div>
    </main>

    <!-- แถบขวามือ -->
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

<!-- Modal ยืนยันออกจากระบบ -->
<div id="logoutModal" class="modal-overlay">
    <div class="modal-box">
        <h3 style="color: #d81b60; margin-bottom: 10px;">คุณแน่ใจหรือไม่อ่ะ?</h3>
        <p style="color: #666; font-size: 14px; margin-bottom: 20px;">ต้องการออกจากระบบบัญชีนี้?<br><small>Are you sure? you want to Sign out?</small></p>
        <div class="modal-actions">
            <form action="logout.php" method="post">
                <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
                <button type="submit" class="btn-yes">ใช่ (Yes)</button>
            </form>
            <button type="button" class="btn-cancel" onclick="hideLogoutModal()">ยกเลิก (Cancel)</button>
        </div>
    </div>
</div>

<script>
    function showLogoutModal() { document.getElementById('logoutModal').style.display = 'flex'; }
    function hideLogoutModal() { document.getElementById('logoutModal').style.display = 'none'; }

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
