
<?php
require 'auth.php';
require 'config/db.php';
require_login();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// จัดการเมื่อมีการส่งฟอร์มแก้ไขชื่อหรือรูปภาพ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_auth_token();

    // 1. แก้ไขชื่อ-นามสกุล
    if (isset($_POST['update_name'])) {
        $new_name = trim((string)($_POST['full_name'] ?? ''));
        if ($new_name !== '') {
            $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
            $stmt->bind_param("si", $new_name, $user_id);
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $new_name;
                $message = 'เปลี่ยนชื่อเรียบร้อยแล้ว!';
            } else {
                $error = 'เกิดข้อผิดพลาดในการเปลี่ยนชื่อ';
            }
            $stmt->close();
        } else {
            $error = 'กรุณากรอกชื่อ-นามสกุล';
        }
    }

    // 4. อัปเดตรูปภาพโปรไฟล์
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['avatar']['tmp_name'];
        $original_name = basename($_FILES['avatar']['name']);
        $file_name = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", $original_name);
        $upload_dir = __DIR__ . '/assets/images/';
        
        // สร้างโฟลเดอร์อัตโนมัติหากยังไม่มี
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($ext, $allowed)) {
            if (move_uploaded_file($file_tmp, $upload_dir . $file_name)) {
                $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->bind_param("si", $file_name, $user_id);
                $stmt->execute();
                $stmt->close();
                
                // รีเฟรชหน้าเพื่อแสดงรูปใหม่ทันที
                header("Location: profile.php?success=avatar");
                exit();
            } else {
                $error = 'ไม่สามารถบันทึกไฟล์รูปภาพได้ (ตรวจสอบสิทธิ์โฟลเดอร์ assets/images)';
            }
        } else {
            $error = 'อนุญาตเฉพาะไฟล์รูปภาพ (jpg, jpeg, png, gif, webp) เท่านั้น';
        }
    }
}

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$stmt = $conn->prepare("SELECT full_name, email, avatar FROM users WHERE id = ? LIMIT 1");
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
    <title>โปรไฟล์ของฉัน - Find my meow meow</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .profile-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 30px;
            max-width: 480px;
            margin: 40px auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            font-family: 'Kanit', sans-serif;
        }
        .profile-title {
            font-size: 22px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .avatar-wrapper {
            text-align: center;
            margin-bottom: 20px;
        }
        .avatar-label {
            cursor: pointer;
            display: inline-block;
            position: relative;
        }
        .avatar-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ffb6c1;
            transition: transform 0.2s, filter 0.2s;
        }
        .avatar-label:hover .avatar-img {
            transform: scale(1.03);
            filter: brightness(0.9);
        }
        .avatar-hint {
            font-size: 12px;
            color: #888;
            margin-top: 6px;
        }
        .info-group {
            margin-bottom: 15px;
        }
        .info-group label {
            display: block;
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .email-display {
            background: #f8f9fa;
            padding: 9px 12px;
            border-radius: 8px;
            color: #495057;
            font-size: 14px;
            border: 1px solid #e9ecef;
        }
        .input-flex {
            display: flex;
            gap: 8px;
        }
        .input-flex input[type="text"] {
            flex: 1;
            padding: 9px 12px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 14px;
        }
        .btn-edit {
            background: #ff758c;
            color: white;
            border: none;
            padding: 9px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-logout-main {
            width: 100%;
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 11px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
        }
        .btn-logout-main:hover {
            background-color: #c82333;
        }
        
        /* Modal Popup จัดกึ่งกลางจออย่างสมบูรณ์ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            width: 90%;
            max-width: 340px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
        .btn-confirm-logout {
            background: #dc3545;
            color: white;
            border: none;
            padding: 9px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-cancel {
            background: #e0e0e0;
            color: #333;
            border: none;
            padding: 9px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
    </style>
</head>
<body>

<main class="container">
    <div class="profile-card">
        <div class="profile-title">
            <span>🐾</span>
            <span>โปรไฟล์ของฉัน</span>
        </div>

        <?php if(isset($_GET['success'])): ?><p style="color: green; font-size: 13px; margin-bottom: 10px;">อัปเดตสำเร็จ!</p><?php endif; ?>
        <?php if($message): ?><p style="color: green; font-size: 13px; margin-bottom: 10px;"><?=auth_escape($message)?></p><?php endif; ?>
        <?php if($error): ?><p style="color: red; font-size: 13px; margin-bottom: 10px;"><?=auth_escape($error)?></p><?php endif; ?>

        <!-- อัปโหลดรูปภาพ -->
        <div class="avatar-wrapper">
            <form method="post" enctype="multipart/form-data" id="avatarForm">
                <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
                <label class="avatar-label" title="คลิกเพื่อเปลี่ยนรูปโปรไฟล์">
                    <?php 
                        $avatar_file = !empty($user['avatar']) ? $user['avatar'] : 'student.jpg';
                        $avatar_path = 'assets/images/' . $avatar_file;
                    ?>
                    <img src="<?= htmlspecialchars($avatar_path) ?>" 
                         onerror="this.src='assets/images/student.jpg';" 
                         class="avatar-img" 
                         alt="Profile Picture">
                    <input type="file" name="avatar" accept="image/*" style="display:none;" onchange="document.getElementById('avatarForm').submit();">
                </label>
                <div class="avatar-hint">คลิกที่รูปภาพเพื่อเลือกรูปจากเครื่อง / Drive</div>
            </form>
        </div>

        <!-- แสดงอีเมล -->
        <div class="info-group">
            <label>อีเมลผู้ใช้งาน (Your Email)</label>
            <div class="email-display"><?= htmlspecialchars($user['email']) ?></div>
        </div>

        <!-- แก้ไขชื่อ -->
        <div class="info-group">
            <label>ชื่อ-นามสกุล (Name-Surname)</label>
            <form method="post">
                <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
                <div class="input-flex">
                    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    <button type="submit" name="update_name" class="btn-edit">แก้ไขชื่อ</button>
                </div>
            </form>
        </div>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

        <!-- แก้ไขชื่อเล่น -->
         <label>ชื่อเล่น (Nickname)</label>
            <form method="post">
            <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
                <div class="input-flex">
                    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    <button type="submit" name="update_name" class="btn-edit">แก้ไขชื่อ</button>
                </div>
            </form>
        </div>

      // แก้ไขเพศ
      <?php
    if (isset($_POST['update_gender'])) {
        $gender = trim((string)($_POST['gender'] ?? ''));
        $allowed_genders = ['female', 'male', 'other'];
        if (in_array($gender, $allowed_genders)) {
            $stmt = $conn->prepare("UPDATE users SET gender = ? WHERE id = ?");
            $stmt->bind_param("si", $gender, $user_id);
            if ($stmt->execute()) {
                $message = 'อัปเดตเพศเรียบร้อยแล้ว!';
            } else {
                $error = 'เกิดข้อผิดพลาดในการอัปเดตเพศ';
            }
            $stmt->close();
        } else {
            $error = 'กรุณาเลือกเพศที่ถูกต้อง';
        }
    }
      
    // ดึงข้อมูลผู้ใช้ปัจจุบัน
$stmt = $conn->prepare("SELECT full_name, email, avatar, gender FROM users WHERE id = ? LIMIT 1");
?>
        <!-- แก้ไขเพศ -->
        <div class="info-group">
            <label>เพศ (Gender)</label>
            <form method="post">
                <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
                <div class="input-flex">
                    <select name="gender" style="flex: 1; padding: 9px 12px; border: 1px solid #ced4da; border-radius: 8px; font-size: 14px; background: white; font-family: 'Kanit', sans-serif;">
                        <option value="female" <?= (isset($user['gender']) && $user['gender'] === 'female') ? 'selected' : '' ?>>หญิง (Female)</option>
                        <option value="male" <?= (isset($user['gender']) && $user['gender'] === 'male') ? 'selected' : '' ?>>ชาย (Male)</option>
                        <option value="other" <?= (isset($user['gender']) && $user['gender'] === 'other') ? 'selected' : '' ?>>อื่นๆ / ไม่ระบุ</option>
                    </select>
                    <button type="submit" name="update_gender" class="btn-edit">บันทึก</button>
                </div>
            </form>
        </div>

        <!-- แก้ไขอายุ -->
        <div class="info-group">
            <label>อายุ</lable>
            <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
                <div class="input-flex">
                    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    <button type="submit" name="update_name" class="btn-edit">แก้ไขอายุ</button>
                </div>
               </form>
            </div>

        <!-- แก้ไขสถานะ/อาชีพ -->
        <div class="info-group">
            <label>สถานะ/อาชีพ</lable>
            
            
        <!-- ปุ่มออกจากระบบ (สีแดงอยู่ล่างสุด) -->
        <button type="button" class="btn-logout-main" onclick="showLogoutModal()">ออกจากระบบ</button>
    </div>
</main>

<!-- Modal ยืนยันการออกจากระบบ -->
<div id="logoutModal" class="modal-overlay">
    <div class="modal-box">
        <h3 style="margin-top:0; color:#333;">คุณแน่ใจหรือไม่?</h3>
        <p style="color: #666; font-size: 14px; margin: 10px 0 20px 0;">คุณแน่ใจหรือไม่ว่าต้องการจะออกจากระบบ</p>
        <div class="modal-actions">
            <form action="logout.php" method="post">
                <input type="hidden" name="auth_csrf" value="<?=auth_escape(auth_token())?>">
                <button type="submit" class="btn-confirm-logout">ออกจากระบบ</button>
            </form>
            <button type="button" class="btn-cancel" onclick="hideLogoutModal()">ยกเลิก</button>
        </div>
    </div>
</div>

<script>
    function showLogoutModal() {
        document.getElementById('logoutModal').style.display = 'flex';
    }
    function hideLogoutModal() {
        document.getElementById('logoutModal').style.display = 'none';
    }
</script>

</body>
</html>