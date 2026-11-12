<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'config/db.php';
if (file_exists('config/lang.php')) {
    require_once 'config/lang.php';
}
require_login();

$user_id = (int)$_SESSION['user_id'];
$is_en = (($_SESSION['site_lang'] ?? 'th') === 'en');
$message = '';
$error = '';

// ฟังก์ชันจัดรูปแบบตัวเลขแบบย่อ (เช่น 0, 1, 20, 100, 1K, 500K, 2.5M, 600B, 6T, 12T)
function format_compact_number($num) {
    $n = max(0, (float)$num);
    if ($n < 1000) {
        return (string)(int)$n;
    }
    $units = [
        1000000000000 => 'T',
        1000000000    => 'B',
        1000000       => 'M',
        1000          => 'K',
    ];
    foreach ($units as $divisor => $suffix) {
        if ($n >= $divisor) {
            $val = round($n / $divisor, 1);
            $formatted = (fmod($val, 1.0) == 0.0) ? (string)(int)$val : (string)$val;
            return $formatted . $suffix;
        }
    }
    return (string)(int)$n;
}

// กรณีอัปโหลดไฟล์ใหญ่เกินขนาดที่เซิร์ฟเวอร์กำหนด (เช่น ไฟล์วิดีโอ .mp4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $error = $is_en
        ? '❌ File is too large or invalid! Only .jpg or .jpeg image files are allowed.'
        : '❌ ไฟล์มีขนาดใหญ่เกินไปหรือประเภทไฟล์ไม่ถูกต้อง! อนุญาตเฉพาะรูปภาพนามสกุล .jpg หรือ .jpeg เท่านั้น';
}

// จัดการการบันทึกข้อมูลเมื่อผู้ใช้กดอัปเดตฟอร์มโปรไฟล์ หรืออัปโหลดรูปภาพ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    check_auth_token();

    // 1. อัปเดตข้อมูลส่วนตัว
    if (isset($_POST['update_profile'])) {
        $full_name = trim((string)($_POST['full_name'] ?? ''));
        $nickname  = trim((string)($_POST['nickname'] ?? ''));
        $gender    = trim((string)($_POST['gender'] ?? 'female'));
        $status    = trim((string)($_POST['status'] ?? ''));
        $phone     = trim((string)($_POST['phone'] ?? ''));
        $bio       = trim((string)($_POST['bio'] ?? ''));

        if ($full_name !== '') {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, nickname = ?, gender = ?, status = ?, phone = ?, bio = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $full_name, $nickname, $gender, $status, $phone, $bio, $user_id);
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $message = $is_en ? 'Profile updated successfully!' : 'บันทึกข้อมูลโปรไฟล์เรียบร้อยแล้ว!';
            } else {
                $error = $is_en ? 'Error saving profile data.' : 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
            }
            $stmt->close();
        } else {
            $error = $is_en ? 'Please enter your full name.' : 'กรุณากรอกชื่อ-นามสกุล';
        }
    }

    // 2. อัปเดตรูปภาพโปรไฟล์ (ตรวจสอบเฉพาะไฟล์รูปภาพ .jpg / .jpeg เท่านั้น)
    if (isset($_POST['upload_avatar']) || (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE)) {
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
            $error = $is_en
                ? '⚠️ Please select a .jpg or .jpeg image file before uploading.'
                : '⚠️ กรุณาเลือกไฟล์รูปภาพนามสกุล .jpg หรือ .jpeg ก่อนกดบันทึกรูปภาพ';
        } elseif ($_FILES['avatar']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['avatar']['error'] === UPLOAD_ERR_FORM_SIZE) {
            $error = $is_en
                ? '❌ File is too large! Only .jpg or .jpeg image files are allowed.'
                : '❌ ไฟล์มีขนาดใหญ่เกินกำหนด! อนุญาตเฉพาะรูปภาพนามสกุล .jpg หรือ .jpeg เท่านั้น';
        } elseif ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $original_name = basename($_FILES['avatar']['name']);
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $img_info = @getimagesize($file_tmp);
            $is_valid_jpeg = ($ext === 'jpg' || $ext === 'jpeg') && ($img_info !== false) && ($img_info[2] === IMAGETYPE_JPEG);

            if ($is_valid_jpeg) {
                $file_name = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", $original_name);
                $upload_dir = __DIR__ . '/assets/images/';

                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }

                if (move_uploaded_file($file_tmp, $upload_dir . $file_name)) {
                    @chmod($upload_dir . $file_name, 0644);
                    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->bind_param("si", $file_name, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    $_SESSION['avatar'] = $file_name;

                    header("Location: profile.php?success=avatar");
                    exit();
                } else {
                    $error = $is_en ? 'Unable to upload image file.' : 'ไม่สามารถอัปโหลดไฟล์รูปภาพได้';
                }
            } else {
                $error = $is_en
                    ? '❌ Invalid file type! Only .jpg or .jpeg image files are allowed.'
                    : '❌ ไม่อนุญาตไฟล์นี้! อนุญาตเฉพาะรูปภาพที่มีนามสกุล .jpg หรือ .jpeg เท่านั้น';
            }
        } else {
            $error = $is_en
                ? '❌ Upload failed! Please select a .jpg or .jpeg image file.'
                : '❌ เกิดข้อผิดพลาดในการอัปโหลด กรุณาเลือกเฉพาะไฟล์รูปภาพ .jpg หรือ .jpeg เท่านั้น';
        }
    }
}

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$stmt = $conn->prepare("SELECT full_name, email, avatar, nickname, gender, status, phone, bio FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ดึงยอดผู้ติดตามจริงจากตาราง user_follows (เริ่มต้น 0 และเพิ่ม/ลดตามการกดติดตามจริง)
$follower_count = 0;
$chk_tbl_f = $conn->query("SHOW TABLES LIKE 'user_follows'");
if ($chk_tbl_f && $chk_tbl_f->num_rows > 0) {
    $fq = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_follows WHERE following_id = ?");
    if ($fq) {
        $fq->bind_param("i", $user_id);
        $fq->execute();
        $follower_count = (int)($fq->get_result()->fetch_assoc()['cnt'] ?? 0);
        $fq->close();
    }
}

// ดึงยอดคนถูกใจจริงรวมจากทุกโพสต์ของผู้ใช้นี้ (เริ่มต้น 0 และเพิ่ม/ลดตามการกดถูกใจจริง)
$like_count = 0;
$chk_tbl_r = $conn->query("SHOW TABLES LIKE 'cat_post_reactions'");
$chk_tbl_p = $conn->query("SHOW TABLES LIKE 'cat_sale_posts'");
if ($chk_tbl_r && $chk_tbl_r->num_rows > 0 && $chk_tbl_p && $chk_tbl_p->num_rows > 0) {
    $lq = $conn->prepare("SELECT COUNT(*) AS cnt FROM cat_post_reactions r INNER JOIN cat_sale_posts p ON r.post_id = p.id WHERE p.user_id = ?");
    if ($lq) {
        $lq->bind_param("i", $user_id);
        $lq->execute();
        $like_count = (int)($lq->get_result()->fetch_assoc()['cnt'] ?? 0);
        $lq->close();
    }
}

// ตรวจสอบไฟล์รูปโปรไฟล์ด้วย Pure PHP (พร้อม ?v=filemtime กันแคช)
$raw_avatar = !empty($current_user['avatar']) ? basename($current_user['avatar']) : 'student.jpg';
$full_av_path = __DIR__ . '/assets/images/' . $raw_avatar;
$avatar_path = is_file($full_av_path)
    ? ('assets/images/' . rawurlencode($raw_avatar) . '?v=' . @filemtime($full_av_path))
    : 'assets/images/student.jpg';

// คำนวณปฏิทิน
$month = isset($_GET['month']) ? max(1, min(12, (int)$_GET['month'])) : (int)date('n');
$year  = isset($_GET['year'])  ? (int)$_GET['year'] : (int)date('Y');
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_day_of_week = (int)date('w', strtotime(sprintf('%04d-%02d-01', $year, $month)));

// คำนวณเวลาปัจจุบัน 4 ประเทศด้วย Pure PHP
$tz_th = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('H:i');
$tz_jp = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('H:i');
$tz_us = (new DateTime('now', new DateTimeZone('America/New_York')))->format('H:i');
$tz_uk = (new DateTime('now', new DateTimeZone('Europe/London')))->format('H:i');
?>
<!doctype html>
<html lang="<?= $is_en ? 'en' : 'th' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $is_en ? 'My Profile - Find my meow meow 💖' : 'โปรไฟล์ของฉัน - Find my meow meow 💖' ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff0f3; font-family: 'Kanit', sans-serif; color: #444; }
        .dashboard-layout { display: grid; grid-template-columns: 260px 1fr 310px; min-height: 100vh; }
        .dash-sidebar { background: #ffffff; border-right: 1px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 12px; position: sticky; top: 0; height: 100vh; }
        .dash-brand { font-weight: bold; font-size: 20px; color: #d81b60; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .dash-nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 14px; text-decoration: none; color: #555; font-weight: 500; font-size: 15px; transition: all 0.2s; }
        .dash-nav-item.active, .dash-nav-item:hover { background: #ff758c; color: white; box-shadow: 0 4px 12px rgba(255, 117, 140, 0.3); }
        .dash-main { padding: 25px 30px; overflow-y: auto; }
        .profile-card { background: #ffffff; border-radius: 20px; padding: 30px; border: 1px solid #ffccd5; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .profile-header-flex { display: flex; align-items: center; gap: 25px; margin-bottom: 25px; flex-wrap: wrap; }
        .avatar-box { position: relative; display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .profile-avatar-img { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #ffb6c1; box-shadow: 0 4px 12px rgba(0,0,0,0.08); cursor: pointer; }
        .btn-upload-avatar { background: #ff758c; color: white; border: none; padding: 6px 16px; border-radius: 12px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; }
        .btn-upload-avatar:hover { background: #d81b60; }
        .upload-hint { font-size: 11.5px; color: #c62828; background: #fff5f5; border: 1px dashed #ef9a9a; padding: 4px 10px; border-radius: 8px; text-align: center; }
        .alert-success { color: #1b5e20; background: #e8f5e9; border: 1px solid #a5d6a7; padding: 10px 14px; border-radius: 12px; font-size: 13.5px; margin-bottom: 15px; }
        .alert-error { color: #b71c1c; background: #ffebee; border: 1px solid #ef9a9a; padding: 10px 14px; border-radius: 12px; font-size: 13.5px; font-weight: 500; margin-bottom: 15px; }
        .stats-badges { display: flex; gap: 15px; }
        .stat-card { background: #fff0f3; padding: 12px 20px; border-radius: 14px; text-align: center; border: 1px solid #ffccd5; min-width: 100px; }
        .stat-card strong { display: block; font-size: 18px; color: #d81b60; }
        .stat-card span { font-size: 12px; color: #666; }
        .email-pill { background: #fff0f3; color: #d81b60; padding: 10px 16px; border-radius: 16px; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; border: 1px solid #ffccd5; width: 100%; margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group.full { grid-column: span 2; }
        .form-group label { display: block; font-weight: 600; font-size: 14px; color: #555; margin-bottom: 6px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 11px 14px; border: 1px solid #ffccd5; border-radius: 12px; font-size: 14px; background: #fff8f9; font-family: 'Kanit', sans-serif; outline: none; }
        .form-group textarea { resize: vertical; height: 90px; }
        .btn-save-profile { background: #d81b60; color: white; border: none; padding: 12px 25px; border-radius: 14px; font-weight: 600; cursor: pointer; font-size: 15px; font-family: 'Kanit', sans-serif; transition: background 0.2s; }
        .btn-save-profile:hover { background: #ad1457; }
        .btn-logout-trigger { background: #ff758c; color: white; border: none; padding: 12px; border-radius: 14px; width: 100%; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 20px; font-family: 'Kanit', sans-serif; }
        .dash-right { background: #ffffff; border-left: 1px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 20px; position: sticky; top: 0; height: 100vh; }
        .widget-box { background: #fff8f9; border-radius: 18px; padding: 18px; border: 1px solid #ffccd5; text-align: center; }
        .calendar-table { width: 100%; font-size: 13px; border-collapse: collapse; text-align: center; margin-top: 10px; }
        .calendar-table td, .calendar-table th { padding: 6px 2px; }
        .cal-today { background: #d81b60; color: white; border-radius: 50%; display: inline-block; width: 24px; height: 24px; line-height: 24px; font-weight: 600; }
        .cal-past { color: #333; }
        .cal-future { color: #ccc; }
        .world-time-list { list-style: none; padding: 0; margin: 0; text-align: left; font-size: 13px; }
        .world-time-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #ffccd5; }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <aside class="dash-sidebar">
        <div class="dash-brand">🐾 Find my meow meow</div>
        <a href="index.php" class="dash-nav-item"><?= $L['nav_home'] ?? '🏠 หน้าแรก' ?></a>
        <a href="profile.php" class="dash-nav-item active"><?= $L['nav_profile'] ?? '👤 ประวัติผู้ใช้งาน' ?></a>
        <a href="contact.php" class="dash-nav-item"><?= $L['nav_contact'] ?? '✉️ ติดต่อผู้ประกาศ/แอดมิน' ?></a>
        <a href="buy_cat.php" class="dash-nav-item"><?= $L['nav_buy'] ?? '🛒 ซื้อแมว' ?></a>
        <a href="adopter.php" class="dash-nav-item"><?= $L['nav_chat'] ?? '💬 สมัครเป็นผู้รับเลี้ยงและติดต่อผ่านแชท' ?></a>
        <a href="settings.php" class="dash-nav-item"><?= $L['nav_settings'] ?? '⚙️ การตั้งค่า' ?></a>
        <div style="margin-top: auto;">
            <form action="logout.php" method="POST" style="margin: 0;">
                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                <button type="submit" class="dash-nav-item" style="width: 100%; background: #fff0f3; color: #d81b60; border: 1px solid #ffccd5; cursor: pointer; font-family: 'Kanit', sans-serif;"><?= $L['nav_logout'] ?? '🚪 ออกจากระบบ' ?></button>
            </form>
        </div>
    </aside>

    <main class="dash-main">
        <?php if (file_exists('config/search.php')) include 'config/search.php'; ?>

        <div class="profile-card">
            <h2 style="color: #d81b60; margin-top: 0; margin-bottom: 20px;"><?= $is_en ? '🐾 My Profile' : '🐾 โปรไฟล์ของฉัน / My Profile' ?></h2>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'avatar'): ?>
                <div class="alert-success">✅ <?= $is_en ? 'Profile photo updated successfully!' : 'อัปเดตรูปโปรไฟล์สำเร็จ!' ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="alert-success">✅ <?= auth_escape($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-error"><?= auth_escape($error) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">

                <div class="profile-header-flex">
                    <div class="avatar-box">
                        <label for="avatarInput" title="<?= $is_en ? 'Click to select photo (.jpg or .jpeg only)' : 'คลิกเพื่อเลือกรูปภาพ (.jpg หรือ .jpeg เท่านั้น)' ?>">
                            <img src="<?= htmlspecialchars($avatar_path, ENT_QUOTES, 'UTF-8') ?>" class="profile-avatar-img" alt="Avatar">
                        </label>
                        <input type="file" id="avatarInput" name="avatar" accept=".jpg,.jpeg,image/jpeg" style="font-size: 11px; max-width: 185px;">
                        <div class="upload-hint">
                            ⚠️ <?= $is_en ? 'Allowed: .jpg or .jpeg images only' : 'อนุญาตเฉพาะรูปภาพนามสกุล .jpg หรือ .jpeg เท่านั้น' ?>
                        </div>
                        <button type="submit" name="upload_avatar" class="btn-upload-avatar"><?= $is_en ? 'Upload Photo' : 'บันทึกรูปภาพ' ?></button>
                    </div>

                    <!-- สถิติผู้ติดตาม & คนถูกใจจริง (นับจากฐานข้อมูล + ย่อรูปแบบ 1K, 2.5M, 600B, 6T) -->
                    <div class="stats-badges">
                        <div class="stat-card" title="<?= number_format($follower_count) ?>">
                            <strong><?= format_compact_number($follower_count) ?></strong>
                            <span><?= $is_en ? 'Followers' : 'ผู้ติดตาม' ?></span>
                        </div>
                        <div class="stat-card" title="<?= number_format($like_count) ?>">
                            <strong><?= format_compact_number($like_count) ?></strong>
                            <span><?= $is_en ? 'Likes' : 'คนถูกใจ' ?></span>
                        </div>
                    </div>
                </div>

                <div class="email-pill">
                    <span>📧</span>
                    <span><?= htmlspecialchars($current_user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?> <small>(<?= $is_en ? 'Email is locked and cannot be edited' : 'อีเมลถูกล็อก ไม่สามารถแก้ไขได้' ?>)</small></span>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>👤 <?= $is_en ? 'Full Name' : 'ชื่อ-นามสกุล' ?></label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($current_user['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>🐱 <?= $is_en ? 'Nickname' : 'ชื่อเล่น' ?></label>
                        <input type="text" name="nickname" value="<?= htmlspecialchars($current_user['nickname'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= $is_en ? 'Enter nickname...' : 'ระบุชื่อเล่น...' ?>">
                    </div>

                    <div class="form-group">
                        <label>⚧ <?= $is_en ? 'Gender' : 'เพศ (Gender)' ?></label>
                        <select name="gender">
                            <option value="female" <?= (isset($current_user['gender']) && $current_user['gender'] === 'female') ? 'selected' : '' ?>>หญิง (Female)</option>
                            <option value="male" <?= (isset($current_user['gender']) && $current_user['gender'] === 'male') ? 'selected' : '' ?>>ชาย (Male)</option>
                            <option value="other" <?= (isset($current_user['gender']) && $current_user['gender'] === 'other') ? 'selected' : '' ?>>อื่นๆ / ไม่ระบุ</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>💼 <?= $is_en ? 'Status / Occupation' : 'สถานะ/อาชีพ (Status)' ?></label>
                        <select name="status">
                            <option value="นักเรียน/นักศึกษา" <?= (isset($current_user['status']) && $current_user['status'] === 'นักเรียน/นักศึกษา') ? 'selected' : '' ?>>นักเรียน/นักศึกษา</option>
                            <option value="ข้าราชการ/พนักงานบริษัท" <?= (isset($current_user['status']) && $current_user['status'] === 'ข้าราชการ/พนักงานบริษัท') ? 'selected' : '' ?>>ข้าราชการ/พนักงานบริษัท</option>
                            <option value="เจ้าของธุรกิจ/ผู้ประกอบการ" <?= (isset($current_user['status']) && $current_user['status'] === 'เจ้าของธุรกิจ/ผู้ประกอบการ') ? 'selected' : '' ?>>เจ้าของธุรกิจ/ผู้ประกอบการ</option>
                            <option value="ว่างงาน/อยู่ระหว่างหางาน" <?= (isset($current_user['status']) && $current_user['status'] === 'ว่างงาน/อยู่ระหว่างหางาน') ? 'selected' : '' ?>>ว่างงาน/อยู่ระหว่างหางาน</option>
                            <option value="อื่นๆ" <?= (isset($current_user['status']) && $current_user['status'] === 'อื่นๆ') ? 'selected' : '' ?>>อื่นๆ</option>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label>📞 <?= $is_en ? 'Phone Number' : 'เบอร์โทรศัพท์' ?></label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($current_user['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="089-123-4567">
                    </div>

                    <div class="form-group full">
                        <label>✏️ <?= $is_en ? 'Bio' : 'คำอธิบายโปรไฟล์' ?></label>
                        <textarea name="bio" placeholder="<?= $is_en ? 'Write a short bio about yourself...' : 'กรุณาใส่คำอธิบายเกี่ยวกับตัวคุณ...' ?>" maxlength="500"><?= htmlspecialchars($current_user['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                    <button type="submit" name="update_profile" class="btn-save-profile">💾 <?= $is_en ? 'Save Changes' : 'บันทึกการเปลี่ยนแปลง' ?></button>
                </div>
            </form>

            <form action="logout.php" method="POST" style="margin: 0;">
                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                <button type="submit" class="btn-logout-trigger"><?= $L['nav_logout'] ?? '🚪 ออกจากระบบ' ?></button>
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
                $month_name = $L['months'][$month] ?? date('F', mktime(0, 0, 0, $month, 1));
                $display_year = $is_en ? $year : ($year + 543);
                $days_header = $L['days'] ?? ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
            ?>
            <div style="display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 14px; color: #d81b60;">
                <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>" style="text-decoration:none; color:#d81b60; font-size:18px;">‹</a>
                <span><?= $month_name ?> <?= $display_year ?></span>
                <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>" style="text-decoration:none; color:#d81b60; font-size:18px;">›</a>
            </div>
            <table class="calendar-table">
                <tr>
                    <?php foreach ($days_header as $d): ?>
                        <th><?= $d ?></th>
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
                    if ($i % 7 == 6 && $current_day <= $days_in_month) echo "</tr><tr>";
                    elseif ($i % 7 == 6) break;
                }
                ?>
                </tr>
            </table>
        </div>

        <div class="widget-box" style="text-align: left;">
            <h4 style="font-size: 13px; color: #d81b60; margin: 0 0 8px 0;">🕒 <?= $is_en ? 'World Time' : 'เวลาปัจจุบันตามประเทศ' ?></h4>
            <ul class="world-time-list">
                <li class="world-time-item"><span>TH ไทย (UTC+7)</span> <strong><?= $tz_th ?></strong></li>
                <li class="world-time-item"><span>JP ญี่ปุ่น (UTC+9)</span> <strong><?= $tz_jp ?></strong></li>
                <li class="world-time-item"><span>US สหรัฐฯ (UTC-4/5)</span> <strong><?= $tz_us ?></strong></li>
                <li class="world-time-item"><span>GB สหราชอาณาจักร</span> <strong><?= $tz_uk ?></strong></li>
            </ul>
        </div>
    </aside>
</div>
</body>
</html>