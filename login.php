<?php
require_once 'Auth.php';  // เดิมเป็น auth.php
require_once 'config/db.php';
require_login();
header("Location: Adopter.php?r=" . $result);  // เปลี่ยนเป็นตัว A ใหญ่
$user_id = $_SESSION['user_id'];
$message = ''; $error = '';

// จัดการโพสต์ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    $content = trim($_POST['content'] ?? '');
    $media_name = ''; $att_name = '';

    if ($content !== '' || !empty($_FILES['media']['name']) || !empty($_FILES['attachment']['name'])) {
        $upload_dir = 'assets/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        // อัปโหลดรูปภาพ (jpg, png)
        if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $media_name = time() . '_img_' . basename($_FILES['media']['name']);
                move_uploaded_file($_FILES['media']['tmp_name'], $upload_dir . $media_name);
            } else { $error = 'รูปภาพรองรับเฉพาะ .jpg และ .png เท่านั้น'; }
        }

        // อัปโหลดไฟล์แนบเฉพาะ pdf, doc
        if (!$error && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf', 'doc', 'docx'])) {
                $att_name = time() . '_att_' . basename($_FILES['attachment']['name']);
                move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $att_name);
            } else { $error = 'ไฟล์แนบรองรับเฉพาะ .pdf และ .doc/.docx เท่านั้น'; }
        }

        if (!$error) {
            $stmt = $conn->prepare("INSERT INTO posts (user_id, content, media_path, attachment_path) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $user_id, $content, $media_name, $att_name);
            $stmt->execute();
            $stmt->close();
            header("Location: index.php");
            exit;
        }
    } else { $error = 'กรุณากรอกข้อความหรือแนบไฟล์อย่างน้อย 1 อย่าง'; }
}

// จัดการลบโพสต์
if (isset($_GET['delete_post'])) {
    $post_id = (int)$_GET['delete_post'];
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: index.php");
    exit;
}

// จัดการไลก์
if (isset($_GET['like_post'])) {
    $post_id = (int)$_GET['like_post'];
    $conn->query("INSERT IGNORE INTO post_likes (post_id, user_id) VALUES ($post_id, $user_id)");
    
    // บันทึกแจ้งเตือนเจ้าของโพสต์
    $owner_res = $conn->query("SELECT user_id FROM posts WHERE id = $post_id");
    if ($owner_row = $owner_res->fetch_assoc()) {
        if ($owner_row['user_id'] != $user_id) {
            $conn->query("INSERT INTO notifications (user_id, message) VALUES ({$owner_row['user_id']}, 'มีผู้ใช้งานกดถูกใจโพสต์ของคุณ')");
        }
    }
    header("Location: index.php");
    exit;
}

// ดึงรายการโพสต์พร้อมระบบค้นหา
$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $s_param = "%$search%";
    $stmt = $conn->prepare("SELECT p.*, u.full_name, u.avatar FROM posts p JOIN users u ON p.user_id = u.id WHERE p.content LIKE ? OR u.full_name LIKE ? ORDER BY p.id DESC");
    $stmt->bind_param("ss", $s_param, $s_param);
} else {
    $stmt = $conn->prepare("SELECT p.*, u.full_name, u.avatar FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.id DESC");
}
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ดึงการแจ้งเตือนของ User
$notif_res = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id ORDER BY id DESC LIMIT 5");
$notifications = $notif_res->fetch_all(MYSQLI_ASSOC);
?>
<!-- HTML Layout ของ index.php (โครงสร้าง 3 คอลัมน์ตามดีไซน์เดิม พร้อมเพิ่มฟอร์มโพสต์และแสดงรายการ) -->