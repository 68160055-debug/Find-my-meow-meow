<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'config/db.php';
if (file_exists('config/lang.php')) require_once 'config/lang.php';
require_login();

$user_id = (int)$_SESSION['user_id'];
$post_err = '';

// ฟังก์ชันแปลงเวลาจริงแบบไทย
function time_ago_th($datetime_str) {
    $ts = strtotime($datetime_str);
    $diff = time() - $ts;
    if ($diff < 60) return 'เมื่อสักครู่';
    if ($diff < 3600) return floor($diff / 60) . ' นาทีที่แล้ว';
    if ($diff < 86400) return floor($diff / 3600) . ' ชั่วโมงที่ผ่านมา';
    if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) return 'เมื่อวาน';
    $th_months = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
    $d = (int)date('j', $ts);
    $m = $th_months[(int)date('n', $ts)];
    $y = ((int)date('Y', $ts) + 543) % 100;
    return "{$d} {$m} {$y}";
}

function resolve_av($file) {
    $b = basename((string)$file);
    return ($b !== '' && is_file(__DIR__ . '/assets/images/' . $b)) ? $b : 'student.jpg';
}

// ดักจับกรณีอัปโหลดไฟล์/วิดีโอขนาดใหญ่เกินค่า post_max_size ของเซิร์ฟเวอร์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $post_err = '❌ ไฟล์หรือวิดีโอมีขนาดใหญ่เกินกำหนดของเซิร์ฟเวอร์! จำกัดรูปภาพ/เอกสารไม่เกิน 5MB และวิดีโอไม่เกิน 15MB (หากไฟล์ใหญ่แนะนำให้แนบลิงก์ Google Drive)';
}

// =========================================================
// จัดการคำสั่ง POST (โพสต์, ลบโพสต์, ถูกใจ 4 แบบ, คอมเมนต์, ติดตาม)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    check_auth_token();

    // A. สร้างโพสต์ขายแมวสาธารณะ
    if (isset($_POST['create_sale_post'])) {
        $content = trim((string)($_POST['post_content'] ?? ''));
        $drive_link = trim((string)($_POST['drive_link'] ?? ''));

        if ($drive_link !== '' && !filter_var($drive_link, FILTER_VALIDATE_URL)) {
            $post_err = '❌ ลิงก์ Google Drive ไม่ถูกต้อง (ต้องขึ้นต้นด้วย https://)';
        } elseif ($content === '' && empty($_FILES['post_image']['name']) && empty($_FILES['post_video']['name']) && empty($_FILES['post_doc']['name']) && $drive_link === '') {
            $post_err = '⚠️ กรุณาพิมพ์ข้อความหรือแนบไฟล์/ลิงก์ก่อนโพสต์';
        } else {
            $upload_dir = __DIR__ . '/uploads/posts/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $img_file = null; $vid_file = null; $doc_file = null;

            // 1) รูปภาพ .jpg, .jpeg, .png (จำกัด <= 5MB)
            if (!empty($_FILES['post_image']['name'])) {
                if ($_FILES['post_image']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['post_image']['error'] === UPLOAD_ERR_FORM_SIZE) {
                    $post_err = '❌ รูปภาพมีขนาดใหญ่เกินกำหนด (จำกัดไม่เกิน 5MB)';
                } elseif ($_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['post_image']['name'], PATHINFO_EXTENSION));
                    $img_check = @getimagesize($_FILES['post_image']['tmp_name']);
                    if (in_array($ext, ['jpg', 'jpeg', 'png'], true) && $img_check !== false && $_FILES['post_image']['size'] <= 5 * 1024 * 1024) {
                        $img_file = time() . '_img_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['post_image']['name']));
                        move_uploaded_file($_FILES['post_image']['tmp_name'], $upload_dir . $img_file);
                    } else {
                        $post_err = '❌ รูปภาพอนุญาตเฉพาะไฟล์ .jpg, .jpeg, .png ขนาดไม่เกิน 5MB เท่านั้น';
                    }
                }
            }

            // 2) วิดีโอ .mp4, .webm (จำกัด <= 15MB)
            if (!$post_err && !empty($_FILES['post_video']['name'])) {
                if ($_FILES['post_video']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['post_video']['error'] === UPLOAD_ERR_FORM_SIZE) {
                    $post_err = '❌ วิดีโอมีขนาดใหญ่เกินกำหนด (จำกัดไม่เกิน 15MB หากใหญ่กว่านี้กรุณาใช้ลิงก์ Google Drive)';
                } elseif ($_FILES['post_video']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['post_video']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['mp4', 'webm'], true) && $_FILES['post_video']['size'] <= 15 * 1024 * 1024) {
                        $vid_file = time() . '_vid_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['post_video']['name']));
                        move_uploaded_file($_FILES['post_video']['tmp_name'], $upload_dir . $vid_file);
                    } else {
                        $post_err = '❌ วิดีโออนุญาตเฉพาะ .mp4 หรือ .webm ขนาดไม่เกิน 15MB เท่านั้น';
                    }
                }
            }

            // 3) เอกสาร .pdf, .doc, .docx, .ppt, .pptx (จำกัด <= 5MB)
            if (!$post_err && !empty($_FILES['post_doc']['name'])) {
                if ($_FILES['post_doc']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['post_doc']['error'] === UPLOAD_ERR_FORM_SIZE) {
                    $post_err = '❌ ไฟล์เอกสารมีขนาดใหญ่เกินกำหนด (จำกัดไม่เกิน 5MB)';
                } elseif ($_FILES['post_doc']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['post_doc']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['pdf', 'doc', 'docx', 'ppt', 'pptx'], true) && $_FILES['post_doc']['size'] <= 5 * 1024 * 1024) {
                        $doc_file = time() . '_doc_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['post_doc']['name']));
                        move_uploaded_file($_FILES['post_doc']['tmp_name'], $upload_dir . $doc_file);
                    } else {
                        $post_err = '❌ เอกสารอนุญาตเฉพาะ .pdf, .doc, .docx, .pptx ขนาดไม่เกิน 5MB เท่านั้น';
                    }
                }
            }

            if (!$post_err) {
                $st = $conn->prepare("INSERT INTO cat_sale_posts (user_id, content, image_file, video_file, doc_file, drive_link) VALUES (?, ?, ?, ?, ?, ?)");
                $st->bind_param("isssss", $user_id, $content, $img_file, $vid_file, $doc_file, $drive_link);
                $st->execute();
                $st->close();
                header("Location: buy_cat.php?posted=1");
                exit();
            }
        }
    }

    // B. ลบโพสต์ (เฉพาะเจ้าของโพสต์เท่านั้น)
    if (isset($_POST['delete_post_id'])) {
        $del_id = (int)$_POST['delete_post_id'];
        $st = $conn->prepare("DELETE FROM cat_sale_posts WHERE id = ? AND user_id = ?");
        $st->bind_param("ii", $del_id, $user_id);
        $st->execute();
        $st->close();
        header("Location: buy_cat.php");
        exit();
    }

    // C. กดถูกใจรูปแมว 4 อารมณ์ (กดซ้ำ = เอาออก)
    if (isset($_POST['react_post_id'], $_POST['reaction_type'])) {
        $pid = (int)$_POST['react_post_id'];
        $rtype = in_array($_POST['reaction_type'], ['happy', 'sad', 'love', 'angry'], true) ? $_POST['reaction_type'] : 'happy';
        $chk = $conn->prepare("SELECT reaction_type FROM cat_post_reactions WHERE post_id = ? AND user_id = ?");
        $chk->bind_param("ii", $pid, $user_id);
        $chk->execute();
        $old = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($old && $old['reaction_type'] === $rtype) {
            $del = $conn->prepare("DELETE FROM cat_post_reactions WHERE post_id = ? AND user_id = ?");
            $del->bind_param("ii", $pid, $user_id);
            $del->execute();
            $del->close();
        } elseif ($old) {
            $upd = $conn->prepare("UPDATE cat_post_reactions SET reaction_type = ? WHERE post_id = ? AND user_id = ?");
            $upd->bind_param("sii", $rtype, $pid, $user_id);
            $upd->execute();
            $upd->close();
        } else {
            $ins = $conn->prepare("INSERT INTO cat_post_reactions (post_id, user_id, reaction_type) VALUES (?, ?, ?)");
            $ins->bind_param("iis", $pid, $user_id, $rtype);
            $ins->execute();
            $ins->close();
        }
        header("Location: buy_cat.php#post-" . $pid);
        exit();
    }

    // D. เพิ่ม / แก้ไข / ลบ ความคิดเห็น
    if (isset($_POST['add_comment_post_id'])) {
        $pid = (int)$_POST['add_comment_post_id'];
        $ctext = trim((string)($_POST['comment_text'] ?? ''));
        if ($ctext !== '') {
            $ins = $conn->prepare("INSERT INTO cat_post_comments (post_id, user_id, comment_text) VALUES (?, ?, ?)");
            $ins->bind_param("iis", $pid, $user_id, $ctext);
            $ins->execute();
            $ins->close();
        }
        header("Location: buy_cat.php#post-" . $pid);
        exit();
    }
    if (isset($_POST['edit_comment_id'])) {
        $cid = (int)$_POST['edit_comment_id'];
        $ctext = trim((string)($_POST['new_comment_text'] ?? ''));
        if ($ctext !== '') {
            $upd = $conn->prepare("UPDATE cat_post_comments SET comment_text = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $upd->bind_param("sii", $ctext, $cid, $user_id);
            $upd->execute();
            $upd->close();
        }
        header("Location: buy_cat.php");
        exit();
    }
    if (isset($_POST['delete_comment_id'])) {
        $cid = (int)$_POST['delete_comment_id'];
        $del = $conn->prepare("DELETE FROM cat_post_comments WHERE id = ? AND user_id = ?");
        $del->bind_param("ii", $cid, $user_id);
        $del->execute();
        $del->close();
        header("Location: buy_cat.php");
        exit();
    }

    // E. กดติดตาม / ยืนยันเลิกติดตาม
    if (isset($_POST['follow_target_id'])) {
        $tid = (int)$_POST['follow_target_id'];
        if ($tid !== $user_id) {
            $ins = $conn->prepare("INSERT IGNORE INTO user_follows (follower_id, following_id) VALUES (?, ?)");
            $ins->bind_param("ii", $user_id, $tid);
            $ins->execute();
            $ins->close();
        }
        header("Location: buy_cat.php");
        exit();
    }
    if (isset($_POST['confirm_unfollow_id'])) {
        $tid = (int)$_POST['confirm_unfollow_id'];
        $del = $conn->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
        $del->bind_param("ii", $user_id, $tid);
        $del->execute();
        $del->close();
        header("Location: buy_cat.php");
        exit();
    }
}

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$stmt = $conn->prepare("SELECT full_name, email, avatar FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ดึงโพสต์ขายแมวสาธารณะทั้งหมด
$sale_posts = [];
$res_p = $conn->query("SELECT p.*, u.full_name, u.avatar FROM cat_sale_posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
if ($res_p) {
    while ($row = $res_p->fetch_assoc()) $sale_posts[] = $row;
}

// ตัวแปรปฏิทิน & เวลาโลก
$month = isset($_GET['month']) ? max(1, min(12, (int)$_GET['month'])) : (int)date('n');
$year  = isset($_GET['year'])  ? (int)$_GET['year'] : (int)date('Y');
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_day_of_week = (int)date('w', strtotime(sprintf('%04d-%02d-01', $year, $month)));
$tz_th = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('H:i');
$tz_jp = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('H:i');
$tz_us = (new DateTime('now', new DateTimeZone('America/New_York')))->format('H:i');
$tz_uk = (new DateTime('now', new DateTimeZone('Europe/London')))->format('H:i');

$edit_cid = isset($_GET['edit_comment']) ? (int)$_GET['edit_comment'] : 0;
$unfollow_uid = isset($_GET['ask_unfollow']) ? (int)$_GET['ask_unfollow'] : 0;
$share_pid = isset($_GET['share_post']) ? (int)$_GET['share_post'] : 0;

// รายการแมว 6 ตัว + ค้นหา/กรองสายพันธุ์
$cat_list = [
    ['id'=>1,'name'=>'Mochi','price'=>'12,000','breed'=>'บริติช ชอร์ตแฮร์','img'=>'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?w=600&auto=format&fit=crop&q=80'],
    ['id'=>2,'name'=>'Leo','price'=>'8,500','breed'=>'เปอร์เซีย','img'=>'https://images.unsplash.com/photo-1518791841217-8f162f1e1131?w=600&auto=format&fit=crop&q=80'],
    ['id'=>3,'name'=>'Luna','price'=>'8,500','breed'=>'สก็อตติช โฟลด์','img'=>'https://images.unsplash.com/photo-1573865526739-10659fec78a5?w=600&auto=format&fit=crop&q=80'],
    ['id'=>4,'name'=>'Lonera','price'=>'12,000','breed'=>'เปอร์เซีย','img'=>'https://images.unsplash.com/photo-1543852786-1cf6624b9987?w=600&auto=format&fit=crop&q=80'],
    ['id'=>5,'name'=>'Lunnavan','price'=>'12,000','breed'=>'เปอร์เซีย','img'=>'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=600&auto=format&fit=crop&q=80'],
    ['id'=>6,'name'=>'Lluna','price'=>'12,000','breed'=>'สก็อตติช โฟลด์','img'=>'https://images.unsplash.com/photo-1533738363-b7f9aef128ce?w=600&auto=format&fit=crop&q=80'],
];
$cat_q = trim((string)($_GET['cat_q'] ?? ''));
$breed_filter = trim((string)($_GET['breed'] ?? ''));
$filtered_cats = array_filter($cat_list, fn($c) => ($cat_q === '' || stripos($c['name'], $cat_q) !== false || stripos($c['breed'], $cat_q) !== false) && ($breed_filter === '' || $c['breed'] === $breed_filter));
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ซื้อแมว (Buy Cat) - Find my meow meow 💖</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff0f3; font-family: 'Kanit', sans-serif; color: #444; }
        .dashboard-layout { display: grid; grid-template-columns: 260px 1fr 310px; min-height: 100vh; }
        .dash-sidebar { background: #ffffff; border-right: 1px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 12px; position: sticky; top: 0; height: 100vh; }
        .dash-brand { font-weight: bold; font-size: 20px; color: #d81b60; margin-bottom: 15px; }
        .dash-nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 14px; text-decoration: none; color: #555; font-weight: 500; font-size: 15px; transition: all 0.2s; }
        .dash-nav-item.active, .dash-nav-item:hover { background: #ff758c; color: white; }
        .dash-main { padding: 25px 30px; overflow-y: auto; }
        .composer-card, .feed-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #ffccd5; margin-bottom: 20px; }
        .composer-input { width: 100%; padding: 12px 18px; border-radius: 18px; border: 1px solid #ffccd5; background: #fff8f9; font-family: 'Kanit', sans-serif; font-size: 14px; outline: none; }
        .composer-tools { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-top: 12px; padding-top: 12px; border-top: 1px dashed #ffccd5; }
        .tool-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #fff0f3; border: 1px solid #ffccd5; border-radius: 14px; font-size: 12.5px; color: #d81b60; cursor: pointer; }
        .limit-note { font-size: 11.5px; color: #888; margin-top: 8px; }
        .btn-post-send { background: #d81b60; color: white; border: none; padding: 8px 20px; border-radius: 16px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; }
        .react-details { position: relative; display: inline-block; }
        .react-details summary { list-style: none; background: #fff0f3; border: 1px solid #ffccd5; border-radius: 14px; padding: 7px 14px; font-size: 13px; color: #d81b60; cursor: pointer; }
        .react-menu { position: absolute; bottom: 110%; left: 0; background: white; border: 1px solid #ffccd5; border-radius: 14px; padding: 6px; display: flex; flex-wrap: wrap; gap: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 10; min-width: 310px; }
        .react-opt { background: #fff8f9; border: 1px solid #ffccd5; border-radius: 10px; padding: 5px 10px; cursor: pointer; font-size: 12.5px; font-family: 'Kanit', sans-serif; white-space: nowrap; }
        .act-btn { background: #fff0f3; border: 1px solid #ffccd5; border-radius: 14px; padding: 7px 14px; font-size: 13px; color: #d81b60; cursor: pointer; text-decoration: none; display: inline-block; font-family: 'Kanit', sans-serif; }
        .comment-box { background: #fff8f9; border-radius: 12px; padding: 10px 14px; margin-top: 8px; font-size: 13px; border: 1px solid #ffe4e8; }
        .modal-confirm { background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 14px; margin-bottom: 18px; font-size: 13.5px; color: #856404; }
        .filter-bar { display: flex; gap: 10px; align-items: center; margin-top: 25px; }
        .filter-bar input { flex: 1; padding: 12px 20px; border-radius: 20px; border: 1px solid #ffccd5; background: white; font-family: 'Kanit', sans-serif; outline: none; }
        .filter-bar select { padding: 12px 16px; border-radius: 20px; border: 1px solid #ffccd5; background: white; font-family: 'Kanit', sans-serif; outline: none; }
        .filter-bar button { background: #d81b60; color: white; border: none; padding: 12px 22px; border-radius: 20px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; }
        .catalog-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; }
        .cat-card { background: white; border-radius: 18px; padding: 15px; border: 1px solid #ffccd5; text-align: center; }
        .cat-card-img-box { width: 100%; height: 170px; border-radius: 14px; overflow: hidden; margin-bottom: 10px; }
        .cat-card-img-box img { width: 100%; height: 100%; object-fit: cover; }
        .btn-buy { background: #ff758c; color: white; padding: 8px 18px; border-radius: 15px; font-weight: 600; text-decoration: none; display: inline-block; font-size: 13px; }
        .dash-right { background: #ffffff; border-left: 1px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 20px; position: sticky; top: 0; height: 100vh; }
        .widget-box { background: #fff8f9; border-radius: 18px; padding: 18px; border: 1px solid #ffccd5; text-align: center; }
        .calendar-table { width: 100%; font-size: 13px; border-collapse: collapse; text-align: center; margin-top: 10px; }
        .cal-today { background: #d81b60; color: white; border-radius: 50%; display: inline-block; width: 24px; height: 24px; line-height: 24px; }
        .world-time-list { list-style: none; padding: 0; margin: 0; text-align: left; font-size: 13px; }
        .world-time-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #ffccd5; }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <aside class="dash-sidebar">
        <div class="dash-brand">🐾 Find my meow meow</div>
        <a href="index.php" class="dash-nav-item"><?= $L['nav_home'] ?? '🏠 หน้าแรก' ?></a>
        <a href="profile.php" class="dash-nav-item"><?= $L['nav_profile'] ?? '👤 ประวัติผู้ใช้งาน' ?></a>
        <a href="contact.php" class="dash-nav-item"><?= $L['nav_contact'] ?? '✉️ ติดต่อผู้ประกาศ/แอดมิน' ?></a>
        <a href="buy_cat.php" class="dash-nav-item active"><?= $L['nav_buy'] ?? '🛒 ซื้อแมว' ?></a>
        <a href="adopter.php" class="dash-nav-item"><?= $L['nav_chat'] ?? '💬 ติดต่อผู้ขอรับเลี้ยง' ?></a>
        <a href="settings.php" class="dash-nav-item"><?= $L['nav_settings'] ?? '⚙️ การตั้งค่า' ?></a>
        <div style="margin-top: auto;">
            <form action="logout.php" method="POST" style="margin:0;">
                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                <button type="submit" class="dash-nav-item" style="width:100%; background:#fff0f3; color:#d81b60; border:1px solid #ffccd5; cursor:pointer;"><?= $L['nav_logout'] ?? '🚪 ออกจากระบบ' ?></button>
            </form>
        </div>
    </aside>

    <main class="dash-main">
        <h2 style="color: #d81b60; margin-top: 0;">🛒 ซื้อแมว & ตลาดประกาศขายแมว 🐾</h2>
        <?php if (file_exists('config/search.php')) include 'config/search.php'; ?>

        <?php if ($post_err): ?><div style="background:#ffebee; color:#b71c1c; padding:10px 15px; border-radius:12px; margin-bottom:15px;"><?= auth_escape($post_err) ?></div><?php endif; ?>

        <!-- กล่องยืนยันการเลิกติดตาม -->
        <?php if ($unfollow_uid > 0): ?>
            <div class="modal-confirm">
                <strong>⚠️ คุณแน่ใจหรือไม่ว่าจะเลิกติดตาม?</strong><br>
                หากคุณไม่ได้ติดตามเพจนี้แล้ว คุณจะไม่สามารถได้รับการแจ้งเตือนข่าวสาร ข้อมูลจากเพจนี้ได้อีก เว้นแต่ว่าคุณจะกดติดตามอีกครั้ง
                <div style="margin-top:10px; display:flex; gap:10px;">
                    <form method="POST" action="buy_cat.php">
                        <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                        <input type="hidden" name="confirm_unfollow_id" value="<?= $unfollow_uid ?>">
                        <button type="submit" style="background:#d81b60; color:white; border:none; padding:6px 14px; border-radius:10px; cursor:pointer;">ใช่ ฉันจะเลิกติดตามเพจนี้</button>
                    </form>
                    <a href="buy_cat.php" style="background:#fff; color:#555; border:1px solid #ccc; padding:6px 14px; border-radius:10px; text-decoration:none;">ยกเลิก</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- แถบยาวสำหรับโพสต์ข้อความขายแมวสาธารณะ -->
        <form method="POST" action="buy_cat.php" enctype="multipart/form-data" class="composer-card">
            <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
            <div style="display:flex; gap:12px; align-items:center;">
                <img src="assets/images/<?= htmlspecialchars(resolve_av($current_user['avatar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:42px; height:42px; border-radius:50%; object-fit:cover;">
                <input type="text" name="post_content" class="composer-input" placeholder="ลงประกาศขายน้องแมว (ระบุสายพันธุ์ ราคา นิสัย)...">
            </div>
            <div class="composer-tools">
                <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                    <label class="tool-pill" title="จำกัด 1 ไฟล์ (.pdf, .doc, .docx, .pptx) ไม่เกิน 5MB">
                        📎 ไฟล์เอกสาร <input type="file" name="post_doc" accept=".pdf,.doc,.docx,.ppt,.pptx" style="display:none;">
                    </label>
                    <label class="tool-pill" title="จำกัด 1 รูปภาพ (.jpg, .jpeg, .png) ไม่เกิน 5MB">
                        🖼️ รูปภาพ <input type="file" name="post_image" accept=".jpg,.jpeg,.png" style="display:none;">
                    </label>
                    <label class="tool-pill" title="จำกัด 1 วิดีโอ (.mp4, .webm) ไม่เกิน 15MB">
                        🎞️ วิดีโอ <input type="file" name="post_video" accept=".mp4,.webm" style="display:none;">
                    </label>
                    <input type="url" name="drive_link" placeholder="🔗 ลิงก์ Google Drive (ถ้ามี)" style="padding:6px 12px; border:1px solid #ffccd5; border-radius:14px; font-size:12px; background:#fff8f9;">
                </div>
                <button type="submit" name="create_sale_post" class="btn-post-send">📤 โพสต์ขาย</button>
            </div>
            <div class="limit-note">
                * จำกัดต่อ 1 โพสต์: รูปภาพ (.jpg, .jpeg, .png) ≤ 5MB | เอกสาร (.pdf, .doc, .pptx) ≤ 5MB | วิดีโอ (.mp4, .webm) ≤ 15MB (ไฟล์ใหญ่ให้แนบลิงก์ Google Drive)
            </div>
        </form>

        <!-- ฟีดโพสต์ขายแมวสาธารณะ -->
        <?php foreach ($sale_posts as $sp):
            $pid = (int)$sp['id'];
            $author_id = (int)$sp['user_id'];
            $is_owner = ($author_id === $user_id);

            $r_counts = ['happy'=>0, 'sad'=>0, 'love'=>0, 'angry'=>0];
            $my_react = '';
            $rq = $conn->prepare("SELECT user_id, reaction_type FROM cat_post_reactions WHERE post_id = ?");
            $rq->bind_param("i", $pid);
            $rq->execute();
            $r_res = $rq->get_result();
            while ($rr = $r_res->fetch_assoc()) {
                if (isset($r_counts[$rr['reaction_type']])) $r_counts[$rr['reaction_type']]++;
                if ((int)$rr['user_id'] === $user_id) $my_react = $rr['reaction_type'];
            }
            $rq->close();
            $total_reacts = array_sum($r_counts);

            $is_following = false;
            if (!$is_owner) {
                $fq = $conn->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND following_id = ?");
                $fq->bind_param("ii", $user_id, $author_id);
                $fq->execute();
                $is_following = (bool)$fq->get_result()->fetch_row();
                $fq->close();
            }

            $comments = [];
            $cq = $conn->prepare("SELECT c.*, u.full_name FROM cat_post_comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? ORDER BY c.created_at ASC");
            $cq->bind_param("i", $pid);
            $cq->execute();
            $cres = $cq->get_result();
            while ($cr = $cres->fetch_assoc()) $comments[] = $cr;
            $cq->close();

            $my_emoji = ['happy'=>'😺', 'sad'=>'😿', 'love'=>'😻', 'angry'=>'😾'][$my_react] ?? '';
        ?>
        <div class="feed-card" id="post-<?= $pid ?>">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <a href="user_profile.php?id=<?= $author_id ?>" style="display:flex; gap:10px; align-items:center; text-decoration:none; color:#333;">
                    <img src="assets/images/<?= htmlspecialchars(resolve_av($sp['avatar']), ENT_QUOTES, 'UTF-8') ?>" style="width:42px; height:42px; border-radius:50%; object-fit:cover;">
                    <div>
                        <strong><?= htmlspecialchars($sp['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <div style="font-size:12px; color:#888;"><?= time_ago_th($sp['created_at']) ?></div>
                    </div>
                </a>
                <div style="display:flex; gap:8px; align-items:center;">
                    <?php if (!$is_owner): ?>
                        <?php if ($is_following): ?>
                            <a href="buy_cat.php?ask_unfollow=<?= $author_id ?>" class="act-btn" style="background:#e8f5e9; color:#2e7d32;">✔ กำลังติดตาม</a>
                        <?php else: ?>
                            <form method="POST" action="buy_cat.php" style="margin:0;">
                                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                                <input type="hidden" name="follow_target_id" value="<?= $author_id ?>">
                                <button type="submit" class="act-btn" style="background:#ff758c; color:white;">+ ติดตาม</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($is_owner): ?>
                        <form method="POST" action="buy_cat.php" style="margin:0;">
                            <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                            <input type="hidden" name="delete_post_id" value="<?= $pid ?>">
                            <button type="submit" class="act-btn" style="background:#ffebee; color:#c62828;">🗑️ ลบโพสต์</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <p style="font-size:14px; line-height:1.6;"><?= nl2br(htmlspecialchars($sp['content'], ENT_QUOTES, 'UTF-8')) ?></p>

            <?php if (!empty($sp['image_file'])): ?>
                <img src="uploads/posts/<?= htmlspecialchars($sp['image_file'], ENT_QUOTES, 'UTF-8') ?>" style="width:100%; max-height:360px; object-fit:cover; border-radius:14px; margin-top:8px;">
            <?php endif; ?>
            <?php if (!empty($sp['video_file'])): ?>
                <video controls style="width:100%; max-height:360px; border-radius:14px; margin-top:8px;">
                    <source src="uploads/posts/<?= htmlspecialchars($sp['video_file'], ENT_QUOTES, 'UTF-8') ?>">
                </video>
            <?php endif; ?>
            <?php if (!empty($sp['doc_file'])): ?>
                <div style="margin-top:8px;"><a href="uploads/posts/<?= htmlspecialchars($sp['doc_file'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="act-btn">📎 ดาวน์โหลดเอกสารแนบ</a></div>
            <?php endif; ?>
            <?php if (!empty($sp['drive_link'])): ?>
                <div style="margin-top:8px;"><a href="<?= htmlspecialchars($sp['drive_link'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="act-btn">🔗 เปิดไฟล์ Google Drive</a></div>
            <?php endif; ?>

            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; padding-top:12px; border-top:1px solid #fff0f3; align-items:center;">
                <details class="react-details">
                    <summary>🐱 ถูกใจ (<?= $total_reacts ?>) <?= $my_emoji ?></summary>
                    <div class="react-menu">
                        <form method="POST" action="buy_cat.php" style="margin:0; display:flex; flex-wrap:wrap; gap:5px;">
                            <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                            <input type="hidden" name="react_post_id" value="<?= $pid ?>">
                            <button type="submit" name="reaction_type" value="happy" class="react-opt">😺 ดีใจ (<?= $r_counts['happy'] ?>)</button>
                            <button type="submit" name="reaction_type" value="love" class="react-opt">😻 รัก (<?= $r_counts['love'] ?>)</button>
                            <button type="submit" name="reaction_type" value="sad" class="react-opt">😿 ซาบซึ้ง (<?= $r_counts['sad'] ?>)</button>
                            <button type="submit" name="reaction_type" value="angry" class="react-opt">😾 โกรธ (<?= $r_counts['angry'] ?>)</button>
                        </form>
                    </div>
                </details>
                <span class="act-btn">💬 ความคิดเห็น (<?= count($comments) ?>)</span>
                <a href="buy_cat.php?share_post=<?= $pid ?>#post-<?= $pid ?>" class="act-btn">🔗 แชร์โพสต์</a>
            </div>

            <?php if ($share_pid === $pid): ?>
                <div style="margin-top:10px; padding:10px; background:#f3e5f5; border-radius:12px; font-size:12.5px;">
                    <strong>📋 คัดลอกข้อความและรูปภาพโพสต์นี้:</strong>
                    <textarea readonly style="width:100%; margin-top:6px; padding:8px; border-radius:8px; border:1px solid #ce93d8; font-family:'Kanit';"><?= htmlspecialchars($sp['content'] . (!empty($sp['image_file']) ? "\nรูปภาพ: uploads/posts/" . $sp['image_file'] : ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            <?php endif; ?>

            <?php foreach ($comments as $cm): $cid = (int)$cm['id']; ?>
                <div class="comment-box">
                    <strong><?= htmlspecialchars($cm['full_name'], ENT_QUOTES, 'UTF-8') ?>:</strong>
                    <?php if ($edit_cid === $cid && (int)$cm['user_id'] === $user_id): ?>
                        <form method="POST" action="buy_cat.php" style="display:flex; gap:6px; margin-top:6px;">
                            <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                            <input type="hidden" name="edit_comment_id" value="<?= $cid ?>">
                            <input type="text" name="new_comment_text" value="<?= htmlspecialchars($cm['comment_text'], ENT_QUOTES, 'UTF-8') ?>" required style="flex:1; padding:5px 10px; border-radius:8px; border:1px solid #ffccd5;">
                            <button type="submit" class="btn-post-send" style="padding:4px 12px; font-size:12px;">บันทึก</button>
                        </form>
                    <?php else: ?>
                        <span><?= htmlspecialchars($cm['comment_text'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ((int)$cm['user_id'] === $user_id): ?>
                            <span style="float:right; display:flex; gap:6px;">
                                <a href="buy_cat.php?edit_comment=<?= $cid ?>#post-<?= $pid ?>" style="color:#1565c0; text-decoration:none; font-size:12px;">✏️ แก้ไข</a>
                                <form method="POST" action="buy_cat.php" style="margin:0;">
                                    <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                                    <input type="hidden" name="delete_comment_id" value="<?= $cid ?>">
                                    <button type="submit" style="background:none; border:none; color:#c62828; cursor:pointer; font-size:12px; padding:0;">🗑️ ลบ</button>
                                </form>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <form method="POST" action="buy_cat.php" style="display:flex; gap:8px; margin-top:10px;">
                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                <input type="hidden" name="add_comment_post_id" value="<?= $pid ?>">
                <input type="text" name="comment_text" placeholder="เขียนความคิดเห็น..." required style="flex:1; padding:8px 14px; border-radius:14px; border:1px solid #ffccd5; background:#fff8f9; font-family:'Kanit';">
                <button type="submit" class="btn-post-send" style="padding:6px 16px;">ส่ง</button>
            </form>
        </div>
        <?php endforeach; ?>

        <!-- แถบค้นหาและกรองสายพันธุ์แมว (1 ชุด) -->
        <form method="GET" action="buy_cat.php" class="filter-bar">
            <input type="text" name="cat_q" value="<?= htmlspecialchars($cat_q, ENT_QUOTES, 'UTF-8') ?>" placeholder="🔍 ค้นหาแมวที่คุณรัก (ชื่อ หรือ สายพันธุ์)...">
            <select name="breed">
                <option value="">ทุกสายพันธุ์</option>
                <option value="บริติช ชอร์ตแฮร์" <?= $breed_filter === 'บริติช ชอร์ตแฮร์' ? 'selected' : '' ?>>บริติช ชอร์ตแฮร์</option>
                <option value="เปอร์เซีย" <?= $breed_filter === 'เปอร์เซีย' ? 'selected' : '' ?>>เปอร์เซีย</option>
                <option value="สก็อตติช โฟลด์" <?= $breed_filter === 'สก็อตติช โฟลด์' ? 'selected' : '' ?>>สก็อตติช โฟลด์</option>
            </select>
            <button type="submit">ค้นหา</button>
        </form>

        <!-- แคตตาล็อกแมวแนะนำ 6 ตัว -->
        <div class="catalog-grid">
            <?php if (empty($filtered_cats)): ?>
                <p style="grid-column:1/-1; text-align:center; color:#888;">ไม่พบน้องแมวที่ตรงกับเงื่อนไขการค้นหา</p>
            <?php else: ?>
                <?php foreach ($filtered_cats as $cat): ?>
                <div class="cat-card">
                    <div class="cat-card-img-box"><img src="<?= htmlspecialchars($cat['img'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <h4>ชื่อ <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                    <div style="color:#d81b60; font-weight:bold;">ราคา <?= htmlspecialchars($cat['price'], ENT_QUOTES, 'UTF-8') ?> บาท</div>
                    <div style="color:#888; font-size:12px; margin-bottom:10px;">สายพันธุ์ <?= htmlspecialchars($cat['breed'], ENT_QUOTES, 'UTF-8') ?></div>
                    <a href="cat_detail.php?id=<?= (int)$cat['id'] ?>" class="btn-buy">🛒 สั่งซื้อ / ดูรายละเอียด</a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <aside class="dash-right">
        <div class="widget-box">
            <h4 style="margin:0 0 10px 0; color:#d81b60;">📅 <?= ($L['months'][$month] ?? date('F')) ?> <?= $year + 543 ?></h4>
            <table class="calendar-table">
                <tr><?php foreach (($L['days'] ?? ['อา','จ','อ','พ','พฤ','ศ','ส']) as $dh) echo "<th>{$dh}</th>"; ?></tr>
                <tr><?php
                $cd = 1; $td = (int)date('j');
                for ($i = 0; $i < 42; $i++) {
                    if ($i >= $first_day_of_week && $cd <= $days_in_month) {
                        echo ($cd === $td) ? "<td><span class='cal-today'>{$cd}</span></td>" : "<td>{$cd}</td>";
                        $cd++;
                    } else echo "<td></td>";
                    if ($i % 7 == 6 && $cd <= $days_in_month) echo "</tr><tr>";
                    elseif ($i % 7 == 6) break;
                }
                ?></tr>
            </table>
        </div>
        <div class="widget-box" style="text-align:left;">
            <h4 style="font-size:13px; color:#d81b60; margin:0 0 8px 0;">🕒 เวลาปัจจุบันตามประเทศ</h4>
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