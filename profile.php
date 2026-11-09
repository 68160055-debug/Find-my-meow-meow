<?php
require 'auth.php';
require 'config/db.php';
require_login();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// จัดการการบันทึกข้อมูลเมื่อผู้ใช้กดอัปเดตฟอร์มโปรไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_auth_token();

    // 1. อัปเดตข้อมูลส่วนตัว (ชื่อ, ชื่อเล่น, สถานะ, เบอร์โทร, คำอธิบาย)
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

    // 2. อัปเดตรูปภาพโปรไฟล์ (เฉพาะไฟล์ .jpg / .jpeg เท่านั้นตามเงื่อนไข)
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
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>โปรไฟล์ - Find my meow meow</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 750px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 24px;
            padding: 35px;
            box-shadow: 0 10px 30px rgba(216, 27, 96, 0.08);
            border: 1px solid #ffccd5;
        }
        .profile-header-flex {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .avatar-box {
            position: relative;
            cursor: pointer;
        }
        .profile-avatar-img {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ffb6c1;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
            min-width: 110px;
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
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            border: 1px solid #ffccd5;
            width: 100%;
            margin-bottom: 15px;
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
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 14px;
            background: #fafafa;
            font-family: 'Kanit', sans-serif;
        }
        .form-group textarea { resize: vertical; height: 90px; }
        .btn-save-profile {
            background: #d81b60;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            font-size: 15px;
            transition: background 0.2s;
        }
        .btn-save-profile:hover { background: #ad1457; }
        .btn-logout-trigger {
            background: #ff758c;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            width: 100%;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
        }
        /* Modal ยืนยันออกจากระบบ */
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
        }
        .btn-cancel {
            background: #fff;
            color: #d81b60;
            border: 1px solid #ff758c;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <!-- Navbar ตามโครงร่าง -->
    <header class="navbar">
        <div class="logo">🐾 Find my meow meow 💖</div>
        <nav class="nav-links">
            <a href="index.php">🏠 หน้าหลัก</a>
            <a href="#">📢 ประกาศ</a>
            <a href="#">ℹ️ เกี่ยวกับเรา</a>
            <a href="profile.php" class="btn-reg" style="background: #d81b60; color: white;">🐾 โปรไฟล์</a>
        </nav>
    </header>

    <div class="profile-container">
        <?php if(isset($_GET['success'])): ?><p style="color: green; font-size: 13px; margin-bottom: 10px;">อัปเดตรูปโปรไฟล์สำเร็จ!</p><?php endif; ?>
        <?php if($message): ?><p style="color: green; font-size: 13px; margin-bottom: 10px;"><?=auth_escape($message)?></p><?php endif; ?>
        <?php if($error): ?><p style="color: red; font-size: 13px; margin-bottom: 10px;"><?=auth_escape($error)?></p><?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
            
            <div class="profile-header-flex">
                <!-- อัปโหลดรูปภาพ (เฉพาะ .jpg เท่านั้น) -->
                <div class="avatar-box" title="คลิกเพื่อเปลี่ยนรูปภาพ (.jpg เท่านั้น)" onclick="document.getElementById('avatarInput').click();">
                    <?php 
                        $avatar_file = !empty($user['avatar']) ? $user['avatar'] : 'student.jpg';
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

            <!-- อีเมล (ล็อกไว้ แก้ไขไม่ได้ตามเงื่อนไข) -->
            <div class="email-pill">
                <span>📧</span>
                <span><?= htmlspecialchars($user['email']) ?> <small>(อีเมลถูกล็อก ไม่สามารถแก้ไขได้)</small></span>
            </div>

            <div class="form-grid">
                <!-- ชื่อ-นามสกุล -->
                <div class="form-group">
                    <label>👤 ชื่อ-นามสกุล</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>

                <!-- ชื่อเล่น -->
                <div class="form-group">
                    <label>🐱 ชื่อเล่น</label>
                    <input type="text" name="nickname" value="<?= htmlspecialchars($user['nickname'] ?? '') ?>" placeholder="ระบุชื่อเล่น...">
                </div>

                <!-- เพศ -->
                <div class="form-group">
                    <label>⚧ เพศ (Gender)</label>
                    <select name="gender">
                        <option value="female" <?= (isset($user['gender']) && $user['gender'] === 'female') ? 'selected' : '' ?>>หญิง (Female)</option>
                        <option value="male" <?= (isset($user['gender']) && $user['gender'] === 'male') ? 'selected' : '' ?>>ชาย (Male)</option>
                        <option value="other" <?= (isset($user['gender']) && $user['gender'] === 'other') ? 'selected' : '' ?>>อื่นๆ / ไม่ระบุ</option>
                    </select>
                </div>

                <!-- สถานะ/อาชีพ -->
                <div class="form-group">
                    <label>💼 สถานะ/อาชีพ (Status)</label>
                    <select name="status">
                        <option value="ข้าราชการ/พนักงานบริษัท" <?= (isset($user['status']) && $user['status'] === 'ข้าราชการ/พนักงานบริษัท') ? 'selected' : '' ?>>ข้าราชการ/พนักงานบริษัท</option>
                        <option value="นักเรียน/นักศึกษา" <?= (isset($user['status']) && $user['status'] === 'นักเรียน/นักศึกษา') ? 'selected' : '' ?>>นักเรียน/นักศึกษา</option>
                        <option value="เจ้าของธุรกิจ/ผู้ประกอบการ" <?= (isset($user['status']) && $user['status'] === 'เจ้าของธุรกิจ/ผู้ประกอบการ') ? 'selected' : '' ?>>เจ้าของธุรกิจ/ผู้ประกอบการ</option>
                        <option value="ว่างงาน/อยู่ระหว่างหางาน" <?= (isset($user['status']) && $user['status'] === 'ว่างงาน/อยู่ระหว่างหางาน') ? 'selected' : '' ?>>ว่างงาน/อยู่ระหว่างหางาน</option>
                        <option value="อื่นๆ" <?= (isset($user['status']) && $user['status'] === 'อื่นๆ') ? 'selected' : '' ?>>อื่นๆ</option>
                    </select>
                </div>

                <!-- เบอร์โทรศัพท์ -->
                <div class="form-group full">
                    <label>📞 เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="089-123-4567">
                </div>

                <!-- คำอธิบายโปรไฟล์ -->
                <div class="form-group full">
                    <label>✏️ คำอธิบายโปรไฟล์</label>
                    <textarea name="bio" placeholder="กรุณาใส่คำอธิบายเกี่ยวกับตัวคุณ..." maxlength="500"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                <button type="submit" name="update_profile" class="btn-save-profile">💾 บันทึกการเปลี่ยนแปลง</button>
            </div>
        </form>

        <!-- ปุ่มออกจากระบบ -->
        <button type="button" class="btn-logout-trigger" onclick="showLogoutModal()">🚪 ออกจากระบบ</button>
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
    </script>
</body>
</html>