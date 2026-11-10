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

// จัดการการบันทึกข้อมูลเมื่อผู้ใช้กดอัปเดตฟอร์มโปรไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_auth_token();

    // 1. อัปเดตข้อมูลส่วนตัว
    if (isset($_POST['update_profile'])) {
        $full_name = trim((string)($_POST['full_name'] ?? ''));
        $nickname = trim((string)($_POST['nickname'] ?? ''));
        $gender = trim((string)($_POST['gender'] ?? 'female'));
        $status = trim((string)($_POST['status'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $bio = trim((string)($_POST['bio'] ?? ''));

        if ($full_name !== '') {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, nickname = ?, gender = ?, status = ?, phone = ?, bio = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $full_name, $nickname, $gender, $status, $phone, $bio, $user_id);
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $message = 'บันทึกข้อมูลโปรไฟล์เรียบร้อยแล้ว!';
            } else {
                $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
            }
            $stmt->close();
        } else {
            $error = 'กรุณากรอกชื่อ-นามสกุล';
        }
    }

    // 2. อัปเดตรูปภาพโปรไฟล์ (เฉพาะไฟล์ .jpg / .jpeg เท่านั้น)
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['avatar']['tmp_name'];
        $original_name = basename($_FILES['avatar']['name']);
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        if ($ext === 'jpg' || $ext === 'jpeg') {
            $file_name = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", $original_name);
            $upload_dir = __DIR__ . '/assets/images/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (move_uploaded_file($file_tmp, $upload_dir . $file_name)) {
                $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->bind_param("si", $file_name, $user_id);
                $stmt->execute();
                $stmt->close();
                
                header("Location: profile.php?success=avatar");
                exit();
            } else {
                $error = 'ไม่สามารถอัปโหลดไฟล์รูปภาพได้';
            }
        } else {
            $error = 'อนุญาตให้อัปโหลดเฉพาะไฟล์รูปภาพนามสกุล .jpg หรือ .jpeg เท่านั้น!';
        }
    }
}

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$stmt = $conn->prepare("SELECT full_name, email, avatar, nickname, gender, status, phone, bio FROM users WHERE id = ? LIMIT 1");
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
    <title>โปรไฟล์ของผู้ฉัน - Find my meow meow 💖</title>
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

        /* Left Sidebar */
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

        /* Main Content Center */
        .dash-main {
            padding: 25px 30px;
            overflow-y: auto;
        }
        .profile-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 30px;
            border: 1px solid #ffccd5;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }
        .profile-header-flex {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .avatar-box {
            position: relative;
            cursor: pointer;
        }
        .profile-avatar-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ffb6c1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .stats-badges {
            display: flex;
            gap: 15px;
        }
        .stat-card {
            background: #fff0f3;
            padding: 12px 20px;
            border-radius: 14px;
            text-align: center;
            border: 1px solid #ffccd5;
            min-width: 100px;
        }
        .stat-card strong {
            display: block;
            font-size: 18px;
            color: #d81b60;
        }
        .stat-card span {
            font-size: 12px;
            color: #666;
        }
        .email-pill {
            background: #fff0f3;
            color: #d81b60;
            padding: 10px 16px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            border: 1px solid #ffccd5;
            width: 100%;
            margin-bottom: 20px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group.full {
            grid-column: span 2;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: #555;
            margin-bottom: 6px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ffccd5;
            border-radius: 12px;
            font-size: 14px;
            background: #fff8f9;
            font-family: 'Kanit', sans-serif;
            outline: none;
        }
        .form-group textarea { resize: vertical; height: 90px; }
        .btn-save-profile {
            background: #d81b60;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 14px;
            font-weight: 600;
            cursor: pointer;
            font-size: 15px;
            font-family: 'Kanit', sans-serif;
            transition: background 0.2s;
        }
        .btn-save-profile:hover { background: #ad1457; }
        .btn-logout-trigger {
            background: #ff758c;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 14px;
            width: 100%;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            font-family: 'Kanit', sans-serif;
        }

        /* Modal ยืนยันการออกจากระบบ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-box {
            background: white;
            border-radius: 20px;
            padding: 30px;
            width: 90%;
            max-width: 380px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }
        .btn-yes {
            background: #ff758c;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Kanit', sans-serif;
        }
        .btn-cancel {
            background: #fff;
            color: #d81b60;
            border: 1px solid #ff758c;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Kanit', sans-serif;
        }

        /* Right Sidebar */
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
        <a href="index.php" class="dash-nav-item">🏠 หน้าแรก</a>
        <a href="profile.php" class="dash-nav-item active">👤 ประวัติผู้ใช้งาน</a>
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
        <div class="profile-card">
            <h2 style="color: #d81b60; margin-top: 0; margin-bottom: 20px;">🐾 โปรไฟล์ของผู้ฉัน / My Profile</h2>

            <?php if(isset($_GET['success'])): ?><p style="color: green; font-size: 13px; margin-bottom: 10px;">อัปเดตรูปโปรไฟล์สำเร็จ!</p><?php endif; ?>
            <?php if($message): ?><p style="color: green; font-size: 13px; margin-bottom: 10px;"><?=auth_escape($message)?></p><?php endif; ?>
            <?php if($error): ?><p style="color: red; font-size: 13px; margin-bottom: 10px;"><?=auth_escape($error)?></p><?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
                
                <div class="profile-header-flex">
                    <!-- คลิกอัปโหลดรูปภาพ (เฉพาะ .jpg / .jpeg) -->
                    <div class="avatar-box" title="คลิกเพื่อเปลี่ยนรูปภาพ (.jpg เท่านั้น)" onclick="document.getElementById('avatarInput').click();">
                        <?php 
                            $avatar_file = !empty($current_user['avatar']) ? $current_user['avatar'] : 'student.jpg';
                            $avatar_path = 'assets/images/' . $avatar_file;
                        ?>
                        <img src="<?= htmlspecialchars($avatar_path) ?>" onerror="this.src='assets/images/student.jpg';" class="profile-avatar-img" alt="Avatar">
                        <input type="file" id="avatarInput" name="avatar" accept=".jpg, .jpeg" style="display:none;" onchange="this.form.submit();">
                    </div>

                    <div class="stats-badges">
                        <div class="stat-card">
                            <strong>1,256</strong>
                            <span>ผู้ติดตาม</span>
                        </div>
                        <div class="stat-card">
                            <strong>3,482</strong>
                            <span>คนถูกใจ</span>
                        </div>
                    </div>
                </div>

                <!-- อีเมล (ล็อกไว้ แก้ไขไม่ได้) -->
                <div class="email-pill">
                    <span>📧</span>
                    <span><?= htmlspecialchars($current_user['email']) ?> <small>(อีเมลถูกล็อก ไม่สามารถแก้ไขได้)</small></span>
                </div>

                <div class="form-grid">
                    <!-- ชื่อ-นามสกุล -->
                    <div class="form-group">
                        <label>👤 ชื่อ-นามสกุล</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($current_user['full_name']) ?>" required>
                    </div>

                    <!-- ชื่อเล่น -->
                    <div class="form-group">
                        <label>🐱 ชื่อเล่น</label>
                        <input type="text" name="nickname" value="<?= htmlspecialchars($current_user['nickname'] ?? '') ?>" placeholder="ระบุชื่อเล่น...">
                    </div>

                    <!-- เพศ -->
                    <div class="form-group">
                        <label>⚧ เพศ (Gender)</label>
                        <select name="gender">
                            <option value="female" <?= (isset($current_user['gender']) && $current_user['gender'] === 'female') ? 'selected' : '' ?>>หญิง (Female)</option>
                            <option value="male" <?= (isset($current_user['gender']) && $current_user['gender'] === 'male') ? 'selected' : '' ?>>ชาย (Male)</option>
                            <option value="other" <?= (isset($current_user['gender']) && $current_user['gender'] === 'other') ? 'selected' : '' ?>>อื่นๆ / ไม่ระบุ</option>
                        </select>
                    </div>

                    <!-- สถานะ/อาชีพ -->
                    <div class="form-group">
                        <label>💼 สถานะ/อาชีพ (Status)</label>
                        <select name="status">
                            <option value="นักเรียน/นักศึกษา" <?= (isset($current_user['status']) && $current_user['status'] === 'นักเรียน/นักศึกษา') ? 'selected' : '' ?>>นักเรียน/นักศึกษา</option>
                            <option value="ข้าราชการ/พนักงานบริษัท" <?= (isset($current_user['status']) && $current_user['status'] === 'ข้าราชการ/พนักงานบริษัท') ? 'selected' : '' ?>>ข้าราชการ/พนักงานบริษัท</option>
                            <option value="เจ้าของธุรกิจ/ผู้ประกอบการ" <?= (isset($current_user['status']) && $current_user['status'] === 'เจ้าของธุรกิจ/ผู้ประกอบการ') ? 'selected' : '' ?>>เจ้าของธุรกิจ/ผู้ประกอบการ</option>
                            <option value="ว่างงาน/อยู่ระหว่างหางาน" <?= (isset($current_user['status']) && $current_user['status'] === 'ว่างงาน/อยู่ระหว่างหางาน') ? 'selected' : '' ?>>ว่างงาน/อยู่ระหว่างหางาน</option>
                            <option value="อื่นๆ" <?= (isset($current_user['status']) && $current_user['status'] === 'อื่นๆ') ? 'selected' : '' ?>>อื่นๆ</option>
                        </select>
                    </div>

                    <!-- เบอร์โทรศัพท์ -->
                    <div class="form-group full">
                        <label>📞 เบอร์โทรศัพท์</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($current_user['phone'] ?? '') ?>" placeholder="089-123-4567">
                    </div>

                    <!-- คำอธิบายโปรไฟล์ -->
                    <div class="form-group full">
                        <label>✏️ คำอธิบายโปรไฟล์</label>
                        <textarea name="bio" placeholder="กรุณาใส่คำอธิบายเกี่ยวกับตัวคุณ..." maxlength="500"><?= htmlspecialchars($current_user['bio'] ?? '') ?></textarea>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                    <button type="submit" name="update_profile" class="btn-save-profile">💾 บันทึกการเปลี่ยนแปลง</button>
                </div>
            </form>

            <button type="button" class="btn-logout-trigger" onclick="showLogoutModal()">🚪 ออกจากระบบ</button>
        </div>
    </main>

    <!-- แถบขวามือ (Right Sidebar) -->
    <aside class="dash-right">
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
    setInterval(updateWorldTimes, 1000);
    updateWorldTimes();
</script>

</body>
</html>