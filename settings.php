<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'config/db.php';
require_once 'config/lang.php';
require_login();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// 1. รับค่าเมื่อผู้ใช้กดเปลี่ยนภาษา (เพียว PHP)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_lang'])) {
    check_auth_token();
    $_SESSION['site_lang'] = (($_POST['lang'] ?? 'th') === 'en') ? 'en' : 'th';
    header("Location: settings.php?lang_updated=1");
    exit();
}

// 2. รับค่าเมื่อผู้ใช้ส่งข้อความถึงแอดมิน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_admin_msg'])) {
    check_auth_token();
    $admin_msg = trim((string)($_POST['admin_msg'] ?? ''));
    if ($admin_msg !== '') {
        $message = ($current_lang === 'en') ? 'Message sent to admin successfully!' : 'ส่งข้อความถึงแอดมินเรียบร้อยแล้ว!';
    } else {
        $error = ($current_lang === 'en') ? 'Please enter a message before sending.' : 'กรุณากรอกข้อความก่อนส่ง';
    }
}

// ดึงข้อมูลผู้ใช้งานปัจจุบัน
$stmt = $conn->prepare("SELECT full_name, email, avatar FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ตัวแปรสำหรับปฏิทินด้านขวา
$month = isset($_GET['month']) ? max(1, min(12, (int)$_GET['month'])) : (int)date('n');
$year  = isset($_GET['year'])  ? (int)$_GET['year'] : (int)date('Y');
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_day_of_week = (int)date('w', strtotime(sprintf('%04d-%02d-01', $year, $month)));
?>
<!doctype html>
<html lang="<?= $current_lang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $L['page_title'] ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
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
        .settings-card { background: white; border-radius: 20px; padding: 25px 30px; border: 1px solid #ffccd5; box-shadow: 0 4px 15px rgba(0,0,0,0.02); margin-bottom: 20px; }
        .section-header { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 600; color: #d81b60; margin-bottom: 15px; border-bottom: 2px dashed #ffccd5; padding-bottom: 8px; }
        .setting-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #fff0f3; }
        .setting-info h5 { margin: 0 0 4px 0; font-size: 15px; color: #333; }
        .setting-info p { margin: 0; font-size: 13px; color: #777; }
        .lang-select { padding: 8px 15px; border-radius: 12px; border: 1.5px solid #ffccd5; font-family: 'Kanit', sans-serif; background: #fff8f9; outline: none; }
        .admin-textarea { width: 100%; padding: 12px; border-radius: 12px; border: 1px solid #ffccd5; background: #fff8f9; font-family: 'Kanit', sans-serif; resize: vertical; height: 90px; outline: none; margin-top: 10px; }
        .btn-send-msg { background: #d81b60; color: white; border: none; padding: 10px 24px; border-radius: 14px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; margin-top: 10px; }
        .dash-right { background: #ffffff; border-left: 1px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 20px; position: sticky; top: 0; height: 100vh; }
        .widget-box { background: #fff8f9; border-radius: 18px; padding: 18px; border: 1px solid #ffccd5; text-align: center; }
        .calendar-table { width: 100%; font-size: 13px; border-collapse: collapse; text-align: center; margin-top: 10px; }
        .calendar-table td, .calendar-table th { padding: 6px 2px; }
        .cal-today { background: #d81b60; color: white; border-radius: 50%; display: inline-block; width: 24px; height: 24px; line-height: 24px; font-weight: 600; }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <!-- แถบเมนูด้านซ้าย -->
    <aside class="dash-sidebar">
        <div class="dash-brand">🐾 Find my meow meow</div>
        <a href="index.php" class="dash-nav-item"><?= $L['nav_home'] ?></a>
        <a href="profile.php" class="dash-nav-item"><?= $L['nav_profile'] ?></a>
        <a href="contact.php" class="dash-nav-item"><?= $L['nav_contact'] ?></a>
        <a href="buy_cat.php" class="dash-nav-item"><?= $L['nav_buy'] ?></a>
        <a href="adopter.php" class="dash-nav-item"><?= $L['nav_chat'] ?></a>
        <a href="settings.php" class="dash-nav-item active"><?= $L['nav_settings'] ?></a>
        <div style="margin-top: auto;">
            <form action="logout.php" method="POST" style="margin: 0;">
                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                <button type="submit" class="dash-nav-item" style="width: 100%; background: #fff0f3; color: #d81b60; border: 1px solid #ffccd5; cursor: pointer; font-family: 'Kanit', sans-serif;"><?= $L['nav_logout'] ?></button>
            </form>
        </div>
    </aside>

    <!-- พื้นที่หลักตรงกลาง -->
    <main class="dash-main">
        <?php if (file_exists('config/search.php')) include 'config/search.php'; ?>

        <h2 style="color: #d81b60; margin-top: 0; margin-bottom: 20px;"><?= $L['heading'] ?></h2>

        <?php if ($message): ?>
            <p style="color: green; font-size: 14px; background: #e8f5e9; padding: 10px; border-radius: 10px;"><?= auth_escape($message) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p style="color: red; font-size: 14px; background: #ffebee; padding: 10px; border-radius: 10px;"><?= auth_escape($error) ?></p>
        <?php endif; ?>

        <!-- 1. การตั้งค่าภาษา (HTML Form + PHP) -->
        <div class="settings-card">
            <div class="section-header"><?= $L['sec_lang'] ?></div>
            <form method="POST" action="settings.php" style="display: flex; justify-content: space-between; align-items: center; padding: 5px 0;">
                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                <div class="setting-info">
                    <h5><?= $L['lang_title'] ?></h5>
                    <p><?= $L['lang_desc'] ?></p>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <select name="lang" class="lang-select">
                        <option value="th" <?= $current_lang === 'th' ? 'selected' : '' ?>>🇹🇭 ไทย (TH)</option>
                        <option value="en" <?= $current_lang === 'en' ? 'selected' : '' ?>>🇬🇧 English (EN)</option>
                    </select>
                    <button type="submit" name="change_lang" style="background: #d81b60; color: white; border: none; padding: 9px 18px; border-radius: 12px; font-weight: 500; cursor: pointer; font-family: 'Kanit', sans-serif;">
                        <?= $L['btn_save_lang'] ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. ความเป็นส่วนตัว & ความปลอดภัย -->
        <div class="settings-card">
            <div class="section-header"><?= $L['sec_privacy'] ?></div>
            <div class="setting-row">
                <div class="setting-info">
                    <h5><?= $L['priv_title'] ?></h5>
                    <p><?= $L['priv_desc'] ?></p>
                </div>
                <span>🔒</span>
            </div>
        </div>

        <!-- 3. ติดต่อแอดมิน -->
        <div class="settings-card">
            <div class="section-header"><?= $L['sec_admin'] ?></div>
            <p style="font-size: 13px; color: #666; margin-top: 0;"><?= $L['admin_desc'] ?></p>
            <form method="POST" action="settings.php">
                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                <textarea name="admin_msg" class="admin-textarea" placeholder="<?= $L['admin_ph'] ?>" required></textarea>
                <div style="text-align: right;">
                    <button type="submit" name="send_admin_msg" class="btn-send-msg"><?= $L['btn_send'] ?></button>
                </div>
            </form>
        </div>

        <!-- 4. บัญชีและการออกจากระบบ -->
        <div class="settings-card">
            <div class="section-header"><?= $L['sec_account'] ?></div>
            <p style="font-size: 14px; color: #666;"><?= $L['logout_prompt'] ?></p>
            <form action="logout.php" method="POST" style="margin: 0;">
                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                <button type="submit" style="width: 100%; background: #ff758c; color: white; border: none; padding: 12px 30px; border-radius: 14px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif;"><?= $L['btn_logout'] ?></button>
            </form>
        </div>
    </main>

    <!-- แถบปฏิทินด้านขวา -->
    <aside class="dash-right">
        <div class="widget-box">
            <?php
            $month_name = $L['months'][$month];
            $display_year = ($current_lang === 'en') ? $year : ($year + 543);
            ?>
            <h4 style="margin: 0 0 10px 0; color: #d81b60;">📅 <?= $month_name ?> <?= $display_year ?></h4>
            <table class="calendar-table">
                <tr>
                    <?php foreach ($L['days'] as $day_name): ?>
                        <th><?= $day_name ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                <?php
                $current_day = 1;
                $today_d = (int)date('j');
                $today_m = (int)date('n');
                $today_y = (int)date('Y');
                for ($i = 0; $i < 42; $i++) {
                    if ($i >= $first_day_of_week && $current_day <= $days_in_month) {
                        $is_today = ($current_day == $today_d && $month == $today_m && $year == $today_y);
                        if ($is_today) echo "<td><span class='cal-today'>{$current_day}</span></td>";
                        else echo "<td style='color:#555;'>{$current_day}</td>";
                        $current_day++;
                    } else {
                        echo "<td></td>";
                    }
                    if ($i % 7 == 6 && $current_day <= $days_in_month) echo "</tr><tr>";
                    elseif ($i % 7 == 6) break;
                }
                ?>
                </tr>
            </table>
        </div>
    </aside>
</div>
</body>
</html>