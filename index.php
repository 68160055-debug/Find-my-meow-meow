<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'config/db.php';
if (file_exists('config/lang.php')) require_once 'config/lang.php';
require_login();

$user_id = (int)$_SESSION['user_id'];
$is_en = (($_SESSION['site_lang'] ?? 'th') === 'en');
$post_err = '';

// ตรวจสอบและเพิ่มคอลัมน์ post_type อัตโนมัติหากยังไม่มี
$chk_col = $conn->query("SHOW COLUMNS FROM cat_sale_posts LIKE 'post_type'");
if ($chk_col && $chk_col->num_rows === 0) {
    $conn->query("ALTER TABLE cat_sale_posts ADD COLUMN post_type ENUM('general','sale') NOT NULL DEFAULT 'sale' AFTER user_id");
}

function time_ago_th($datetime_str) {
    $ts = strtotime($datetime_str);
    $diff = time() - $ts;
    if ($diff < 60) return 'เมื่อสักครู่';
    if ($diff < 3600) return floor($diff / 60) . ' นาทีที่แล้ว';
    if ($diff < 86400) return floor($diff / 3600) . ' ชั่วโมงที่ผ่านมา';
    if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) return 'เมื่อวาน';
    $th_months = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
    return (int)date('j', $ts) . ' ' . $th_months[(int)date('n', $ts)] . ' ' . (((int)date('Y', $ts) + 543) % 100);
}

// ดึงรูปโปรไฟล์ปัจจุบัน + แนบเวอร์ชันไฟล์กันแคชรูปเก่า
function resolve_av_url($file) {
    $b = basename((string)$file);
    $full = __DIR__ . '/assets/images/' . $b;
    if ($b !== '' && is_file($full)) {
        return 'assets/images/' . rawurlencode($b) . '?v=' . @filemtime($full);
    }
    return 'assets/images/student.jpg';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $post_err = '❌ ไฟล์หรือวิดีโอมีขนาดใหญ่เกินกำหนดของเซิร์ฟเวอร์! จำกัดรูปภาพ/เอกสาร ≤ 5MB และวิดีโอ ≤ 15MB';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    check_auth_token();

    // A. สร้างโพสต์ทั่วไปสาธารณะ
    if (isset($_POST['create_general_post'])) {
        $content = trim((string)($_POST['post_content'] ?? ''));
        $drive_link = trim((string)($_POST['drive_link'] ?? ''));

        if ($drive_link !== '' && !filter_var($drive_link, FILTER_VALIDATE_URL)) {
            $post_err = '❌ ลิงก์ Google Drive ไม่ถูกต้อง';
        } elseif ($content === '' && empty($_FILES['post_image']['name']) && empty($_FILES['post_video']['name']) && empty($_FILES['post_doc']['name']) && $drive_link === '') {
            $post_err = '⚠️ กรุณาพิมพ์ข้อความหรือแนบไฟล์/ลิงก์ก่อนโพสต์';
        } else {
            $upload_dir = __DIR__ . '/uploads/posts/';
            if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
            @chmod(__DIR__ . '/uploads/', 0755);
            @chmod($upload_dir, 0755);

            $img_file = null; $vid_file = null; $doc_file = null;

            if (!empty($_FILES['post_image']['name'])) {
                if ($_FILES['post_image']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['post_image']['error'] === UPLOAD_ERR_FORM_SIZE) {
                    $post_err = '❌ รูปภาพมีขนาดใหญ่เกินกำหนด (จำกัด ≤ 5MB)';
                } elseif ($_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['post_image']['name'], PATHINFO_EXTENSION));
                    $img_check = @getimagesize($_FILES['post_image']['tmp_name']);
                    if (in_array($ext, ['jpg', 'jpeg', 'png'], true) && $img_check !== false && $_FILES['post_image']['size'] <= 5 * 1024 * 1024) {
                        $img_file = time() . '_img_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['post_image']['name']));
                        if (move_uploaded_file($_FILES['post_image']['tmp_name'], $upload_dir . $img_file)) {
                            @chmod($upload_dir . $img_file, 0644);
                        }
                    } else {
                        $post_err = '❌ รูปภาพอนุญาตเฉพาะ .jpg, .jpeg, .png ขนาดไม่เกิน 5MB';
                    }
                }
            }

            if (!$post_err && !empty($_FILES['post_video']['name'])) {
                if ($_FILES['post_video']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['post_video']['error'] === UPLOAD_ERR_FORM_SIZE) {
                    $post_err = '❌ วิดีโอมีขนาดใหญ่เกินกำหนด (จำกัด ≤ 15MB)';
                } elseif ($_FILES['post_video']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['post_video']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['mp4', 'webm'], true) && $_FILES['post_video']['size'] <= 15 * 1024 * 1024) {
                        $vid_file = time() . '_vid_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['post_video']['name']));
                        if (move_uploaded_file($_FILES['post_video']['tmp_name'], $upload_dir . $vid_file)) {
                            @chmod($upload_dir . $vid_file, 0644);
                        }
                    } else {
                        $post_err = '❌ วิดีโออนุญาตเฉพาะ .mp4 หรือ .webm ขนาดไม่เกิน 15MB';
                    }
                }
            }

            if (!$post_err && !empty($_FILES['post_doc']['name'])) {
                if ($_FILES['post_doc']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['post_doc']['error'] === UPLOAD_ERR_FORM_SIZE) {
                    $post_err = '❌ เอกสารมีขนาดใหญ่เกินกำหนด (จำกัด ≤ 5MB)';
                } elseif ($_FILES['post_doc']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['post_doc']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['pdf', 'doc', 'docx', 'ppt', 'pptx'], true) && $_FILES['post_doc']['size'] <= 5 * 1024 * 1024) {
                        $doc_file = time() . '_doc_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_FILES['post_doc']['name']));
                        if (move_uploaded_file($_FILES['post_doc']['tmp_name'], $upload_dir . $doc_file)) {
                            @chmod($upload_dir . $doc_file, 0644);
                        }
                    } else {
                        $post_err = '❌ เอกสารอนุญาตเฉพาะ .pdf, .doc, .docx, .pptx ขนาดไม่เกิน 5MB';
                    }
                }
            }

            if (!$post_err) {
                $ptype = 'general';
                $st = $conn->prepare("INSERT INTO cat_sale_posts (user_id, post_type, content, image_file, video_file, doc_file, drive_link) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $st->bind_param("issssss", $user_id, $ptype, $content, $img_file, $vid_file, $doc_file, $drive_link);
                $st->execute();
                $st->close();
                header("Location: index.php?posted=1");
                exit();
            }
        }
    }

    // B. ยืนยันลบโพสต์ (เฉพาะเจ้าของโพสต์เท่านั้น)
    if (isset($_POST['confirm_delete_post_id'])) {
        $del_id = (int)$_POST['confirm_delete_post_id'];
        $st = $conn->prepare("DELETE FROM cat_sale_posts WHERE id = ? AND user_id = ?");
        $st->bind_param("ii", $del_id, $user_id);
        $st->execute();
        $st->close();
        header("Location: index.php");
        exit();
    }

    // C. กดถูกใจรูปแมว 4 อารมณ์
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
        header("Location: index.php#post-" . $pid);
        exit();
    }

    // D. ความคิดเห็น (เพิ่ม / แก้ไข / ลบ)
    if (isset($_POST['add_comment_post_id'])) {
        $pid = (int)$_POST['add_comment_post_id'];
        $ctext = trim((string)($_POST['comment_text'] ?? ''));
        if ($ctext !== '') {
            $ins = $conn->prepare("INSERT INTO cat_post_comments (post_id, user_id, comment_text) VALUES (?, ?, ?)");
            $ins->bind_param("iis", $pid, $user_id, $ctext);
            $ins->execute();
            $ins->close();
        }
        header("Location: index.php#post-" . $pid);
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
        header("Location: index.php");
        exit();
    }
    if (isset($_POST['delete_comment_id'])) {
        $cid = (int)$_POST['delete_comment_id'];
        $del = $conn->prepare("DELETE FROM cat_post_comments WHERE id = ? AND user_id = ?");
        $del->bind_param("ii", $cid, $user_id);
        $del->execute();
        $del->close();
        header("Location: index.php");
        exit();
    }

    // E. ติดตาม / เลิกติดตาม
    if (isset($_POST['follow_target_id'])) {
        $tid = (int)$_POST['follow_target_id'];
        if ($tid !== $user_id) {
            $ins = $conn->prepare("INSERT IGNORE INTO user_follows (follower_id, following_id) VALUES (?, ?)");
            $ins->bind_param("ii", $user_id, $tid);
            $ins->execute();
            $ins->close();
        }
        header("Location: index.php");
        exit();
    }
    if (isset($_POST['confirm_unfollow_id'])) {
        $tid = (int)$_POST['confirm_unfollow_id'];
        $del = $conn->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
        $del->bind_param("ii", $user_id, $tid);
        $del->execute();
        $del->close();
        header("Location: index.php");
        exit();
    }
}

$stmt = $conn->prepare("SELECT full_name, email, avatar, status, nickname FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ดึงโพสต์พร้อมข้อมูลโปรไฟล์ล่าสุดของผู้โพสต์ (JOIN users)
$general_posts = [];
$res_p = $conn->query("SELECT p.*, u.full_name, u.avatar FROM cat_sale_posts p JOIN users u ON p.user_id = u.id WHERE p.post_type = 'general' ORDER BY p.created_at DESC");
if ($res_p) {
    while ($row = $res_p->fetch_assoc()) $general_posts[] = $row;
}

$edit_cid = isset($_GET['edit_comment']) ? (int)$_GET['edit_comment'] : 0;
$unfollow_uid = isset($_GET['ask_unfollow']) ? (int)$_GET['ask_unfollow'] : 0;
$ask_delete_pid = isset($_GET['ask_delete']) ? (int)$_GET['ask_delete'] : 0;
$share_pid = isset($_GET['share_post']) ? (int)$_GET['share_post'] : 0;
?>
<!doctype html>
<html lang="<?= $is_en ? 'en' : 'th' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $is_en ? 'Home - Find my meow meow 💖' : 'หน้าแรก - Find my meow meow 💖' ?></title>
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
        .delete-confirm-box { background: #ffebee; border: 1px solid #ef9a9a; padding: 14px 16px; border-radius: 14px; margin: 10px 0; font-size: 13px; color: #b71c1c; line-height: 1.6; }
        .share-box { margin-top: 10px; padding: 12px; background: #f3e5f5; border-radius: 12px; border: 1px solid #ce93d8; }
        .share-box-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
        .copy-icon-btn { display: inline-flex; align-items: center; gap: 4px; background: #8e24aa; color: #fff; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <aside class="dash-sidebar">
        <div class="dash-brand">🐾 Find my meow meow</div>
        <a href="index.php" class="dash-nav-item active"><?= $L['nav_home'] ?? '🏠 หน้าแรก' ?></a>
        <a href="profile.php" class="dash-nav-item"><?= $L['nav_profile'] ?? '👤 ประวัติผู้ใช้งาน' ?></a>
        <a href="contact.php" class="dash-nav-item"><?= $L['nav_contact'] ?? '✉️ ติดต่อผู้ประกาศ/แอดมิน' ?></a>
        <a href="buy_cat.php" class="dash-nav-item"><?= $L['nav_buy'] ?? '🛒 ซื้อแมว' ?></a>
        <a href="adopter.php" class="dash-nav-item"><?= $L['nav_chat'] ?? '💬 ติดต่อผู้ขอรับเลี้ยง' ?></a>
        <a href="settings.php" class="dash-nav-item"><?= $L['nav_settings'] ?? '⚙️ การตั้งค่า' ?></a>
        <div style="margin-top: auto;">
            <form action="logout.php" method="POST" style="margin:0;">
                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                <button type="submit" class="dash-nav-item" style="width:100%; background:#fff0f3; color:#d81b60; border:1px solid #ffccd5; cursor:pointer; font-family:'Kanit';"><?= $L['nav_logout'] ?? '🚪 ออกจากระบบ' ?></button>
            </form>
        </div>
    </aside>

    <main class="dash-main">
        <?php if (file_exists('config/search.php')): ?>
            <?php include 'config/search.php'; ?>
        <?php else: ?>
            <header class="top-dash-header" style="display:flex; justify-content:space-between; align-items:center; padding:15px 25px; background:#fff; border-radius:20px; border:1px solid #ffccd5; margin-bottom:25px;">
                <form action="search_results.php" method="GET" style="flex:1; max-width:550px; position:relative; margin:0;">
                    <span style="position:absolute; left:16px; top:50%; transform:translateY(-50%); font-size:16px; color:#d81b60;">🔍</span>
                    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= $L['search_ph'] ?? 'ค้นหาชื่อคน, ชื่อโพสต์, แอดมิน...' ?>" required style="width:100%; padding:11px 80px 11px 42px; border-radius:25px; border:1.5px solid #ffccd5; outline:none; font-family:'Kanit'; font-size:14px; background:#fff8f9;">
                    <button type="submit" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); background:#d81b60; color:white; border:none; border-radius:20px; padding:6px 16px; font-size:12px; cursor:pointer; font-family:'Kanit';"><?= $L['search_btn'] ?? 'ค้นหา' ?></button>
                </form>
                <a href="profile.php" style="display:flex; align-items:center; gap:10px; text-decoration:none;">
                    <img src="<?= htmlspecialchars(resolve_av_url($current_user['avatar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid #ff758c;">
                    <strong style="font-size:14px; color:#333;"><?= htmlspecialchars($current_user['full_name'] ?? 'ผู้ใช้งาน', ENT_QUOTES, 'UTF-8') ?></strong>
                </a>
            </header>
        <?php endif; ?>

        <?php if ($post_err): ?>
            <div style="background:#ffebee; color:#b71c1c; padding:10px 15px; border-radius:12px; margin-bottom:15px;"><?= auth_escape($post_err) ?></div>
        <?php endif; ?>

        <?php if ($unfollow_uid > 0): ?>
            <div class="modal-confirm">
                <strong>⚠️ คุณแน่ใจหรือไม่ว่าจะเลิกติดตาม?</strong><br>
                หากคุณไม่ได้ติดตามเพจนี้แล้ว คุณจะไม่สามารถได้รับการแจ้งเตือนข่าวสาร ข้อมูลจากเพจนี้ได้อีก เว้นแต่ว่าคุณจะกดติดตามอีกครั้ง
                <div style="margin-top:10px; display:flex; gap:10px;">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                        <input type="hidden" name="confirm_unfollow_id" value="<?= $unfollow_uid ?>">
                        <button type="submit" style="background:#d81b60; color:white; border:none; padding:6px 14px; border-radius:10px; cursor:pointer;">ใช่ ฉันจะเลิกติดตามเพจนี้</button>
                    </form>
                    <a href="index.php" style="background:#fff; color:#555; border:1px solid #ccc; padding:6px 14px; border-radius:10px; text-decoration:none;">ยกเลิก</a>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php" enctype="multipart/form-data" class="composer-card">
            <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
            <div style="display:flex; gap:12px; align-items:center;">
                <img src="<?= htmlspecialchars(resolve_av_url($current_user['avatar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:42px; height:42px; border-radius:50%; object-fit:cover;">
                <input type="text" name="post_content" class="composer-input" placeholder="<?= $is_en ? 'Share something about your cat today...' : 'คุณอยากแชร์เรื่องราวอะไรเกี่ยวกับน้องแมววันนี้...' ?>">
            </div>
            <div class="composer-tools">
                <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                    <label class="tool-pill" title="จำกัด 1 ไฟล์ (.pdf, .doc, .docx, .pptx) ≤ 5MB">
                        📎 ไฟล์เอกสาร <input type="file" name="post_doc" accept=".pdf,.doc,.docx,.ppt,.pptx" style="display:none;">
                    </label>
                    <label class="tool-pill" title="จำกัด 1 รูปภาพ (.jpg, .jpeg, .png) ≤ 5MB">
                        🖼️ รูปภาพ <input type="file" name="post_image" accept=".jpg,.jpeg,.png" style="display:none;">
                    </label>
                    <label class="tool-pill" title="จำกัด 1 วิดีโอ (.mp4, .webm) ≤ 15MB">
                        🎞️ วิดีโอ <input type="file" name="post_video" accept=".mp4,.webm" style="display:none;">
                    </label>
                    <input type="url" name="drive_link" placeholder="🔗 ลิงก์ Google Drive (ถ้ามี)" style="padding:6px 12px; border:1px solid #ffccd5; border-radius:14px; font-size:12px; background:#fff8f9;">
                </div>
                <button type="submit" name="create_general_post" class="btn-post-send">📤 <?= $is_en ? 'Post' : 'แชร์โพสต์' ?></button>
            </div>
            <div class="limit-note">
                * จำกัดต่อ 1 โพสต์: รูปภาพ (.jpg, .jpeg, .png) ≤ 5MB | เอกสาร (.pdf, .doc, .pptx) ≤ 5MB | วิดีโอ (.mp4, .webm) ≤ 15MB (ไฟล์ใหญ่ให้แนบลิงก์ Google Drive)
            </div>
        </form>

        <?php foreach ($general_posts as $gp):
            $pid = (int)$gp['id'];
            $author_id = (int)$gp['user_id'];
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
            $post_img_ok = !empty($gp['image_file']) && is_file(__DIR__ . '/uploads/posts/' . basename($gp['image_file']));
            $post_vid_ok = !empty($gp['video_file']) && is_file(__DIR__ . '/uploads/posts/' . basename($gp['video_file']));
            $post_doc_ok = !empty($gp['doc_file']) && is_file(__DIR__ . '/uploads/posts/' . basename($gp['doc_file']));
        ?>
        <div class="feed-card" id="post-<?= $pid ?>">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <a href="user_profile.php?id=<?= $author_id ?>" style="display:flex; gap:10px; align-items:center; text-decoration:none; color:#333;">
                    <img src="<?= htmlspecialchars(resolve_av_url($gp['avatar']), ENT_QUOTES, 'UTF-8') ?>" style="width:42px; height:42px; border-radius:50%; object-fit:cover;">
                    <div>
                        <strong><?= htmlspecialchars($gp['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <div style="font-size:12px; color:#888;"><?= time_ago_th($gp['created_at']) ?></div>
                    </div>
                </a>
                <div style="display:flex; gap:8px; align-items:center;">
                    <?php if (!$is_owner): ?>
                        <?php if ($is_following): ?>
                            <a href="index.php?ask_unfollow=<?= $author_id ?>" class="act-btn" style="background:#e8f5e9; color:#2e7d32;">✔ กำลังติดตาม</a>
                        <?php else: ?>
                            <form method="POST" action="index.php" style="margin:0;">
                                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                                <input type="hidden" name="follow_target_id" value="<?= $author_id ?>">
                                <button type="submit" class="act-btn" style="background:#ff758c; color:white;">+ ติดตาม</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($is_owner): ?>
                        <a href="index.php?ask_delete=<?= $pid ?>#post-<?= $pid ?>" class="act-btn" style="background:#ffebee; color:#c62828;">🗑️ ลบโพสต์</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- กล่องยืนยันการลบโพสต์ (ข้อ 2) -->
            <?php if ($ask_delete_pid === $pid && $is_owner): ?>
                <div class="delete-confirm-box">
                    <strong>⚠️ คุณแน่ใจหรือไม่ว่าจะลบโพสต์ ถ้าลบโพสต์ไปแล้ว จะไม่สามารถกู้คืนได้อีก เว้นแต่ต้องติดต่อผู้ดูแลระบบนี้เท่านั้น</strong><br>
                    Are you sure you want to delete this post? Once deleted, it cannot be recovered unless you contact this administrator.<br>
                    <small><strong>(ผู้ดูแลระบบ Find My Meow Meow)</strong></small>
                    <div style="margin-top:10px; display:flex; gap:10px;">
                        <form method="POST" action="index.php" style="margin:0;">
                            <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                            <input type="hidden" name="confirm_delete_post_id" value="<?= $pid ?>">
                            <button type="submit" style="background:#c62828; color:white; border:none; padding:6px 14px; border-radius:10px; cursor:pointer; font-family:'Kanit';">ใช่ แน่นอน (Yes Sure)</button>
                        </form>
                        <a href="index.php#post-<?= $pid ?>" style="background:#fff; color:#555; border:1px solid #ccc; padding:6px 14px; border-radius:10px; text-decoration:none;">ยกเลิก (Cancel)</a>
                    </div>
                </div>
            <?php endif; ?>

            <p style="font-size:14px; line-height:1.6;"><?= nl2br(htmlspecialchars($gp['content'], ENT_QUOTES, 'UTF-8')) ?></p>

            <?php if ($post_img_ok): ?>
                <img src="uploads/posts/<?= rawurlencode(basename($gp['image_file'])) ?>" style="width:100%; max-height:360px; object-fit:cover; border-radius:14px; margin-top:8px;">
            <?php endif; ?>
            <?php if ($post_vid_ok): ?>
                <video controls style="width:100%; max-height:360px; border-radius:14px; margin-top:8px;">
                    <source src="uploads/posts/<?= rawurlencode(basename($gp['video_file'])) ?>">
                </video>
            <?php endif; ?>
            <?php if ($post_doc_ok): ?>
                <div style="margin-top:8px;"><a href="uploads/posts/<?= rawurlencode(basename($gp['doc_file'])) ?>" target="_blank" class="act-btn">📎 ดาวน์โหลดเอกสารแนบ</a></div>
            <?php endif; ?>
            <?php if (!empty($gp['drive_link'])): ?>
                <div style="margin-top:8px;"><a href="<?= htmlspecialchars($gp['drive_link'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="act-btn">🔗 เปิดไฟล์ Google Drive</a></div>
            <?php endif; ?>

            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; padding-top:12px; border-top:1px solid #fff0f3; align-items:center;">
                <details class="react-details">
                    <summary>🐱 ถูกใจ (<?= $total_reacts ?>) <?= $my_emoji ?></summary>
                    <div class="react-menu">
                        <form method="POST" action="index.php" style="margin:0; display:flex; flex-wrap:wrap; gap:5px;">
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
                <a href="index.php?share_post=<?= $pid ?>#post-<?= $pid ?>" class="act-btn">🔗 แชร์โพสต์</a>
            </div>

            <!-- กล่องคัดลอกลิงก์พร้อมไอคอน Copy มุมซ้ายบน (ข้อ 3) -->
            <?php if ($share_pid === $pid):
                $share_url = 'https://angsila.cs.buu.ac.th/~68160183/login/index.php#post-' . $pid;
                $share_txt = $gp['content'] . "\nลิงก์โพสต์: " . $share_url . ($post_img_ok ? "\nรูปภาพ: https://angsila.cs.buu.ac.th/~68160183/login/uploads/posts/" . basename($gp['image_file']) : '');
            ?>
                <div class="share-box">
                    <div class="share-box-header">
                        <label for="copy-box-<?= $pid ?>" class="copy-icon-btn" title="คลิกเพื่อเลือกข้อความสำหรับคัดลอก">📋 Copy</label>
                        <strong style="font-size:12.5px; color:#6a1b9a;">คัดลอกลิงก์ ข้อความ และรูปภาพโพสต์นี้</strong>
                    </div>
                    <textarea id="copy-box-<?= $pid ?>" readonly rows="3" style="width:100%; padding:8px; border-radius:8px; border:1px solid #ce93d8; font-family:'Kanit'; font-size:12.5px;"><?= htmlspecialchars($share_txt, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            <?php endif; ?>

            <?php foreach ($comments as $cm): $cid = (int)$cm['id']; ?>
                <div class="comment-box">
                    <strong><?= htmlspecialchars($cm['full_name'], ENT_QUOTES, 'UTF-8') ?>:</strong>
                    <?php if ($edit_cid === $cid && (int)$cm['user_id'] === $user_id): ?>
                        <form method="POST" action="index.php" style="display:flex; gap:6px; margin-top:6px;">
                            <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                            <input type="hidden" name="edit_comment_id" value="<?= $cid ?>">
                            <input type="text" name="new_comment_text" value="<?= htmlspecialchars($cm['comment_text'], ENT_QUOTES, 'UTF-8') ?>" required style="flex:1; padding:5px 10px; border-radius:8px; border:1px solid #ffccd5;">
                            <button type="submit" class="btn-post-send" style="padding:4px 12px; font-size:12px;">บันทึก</button>
                        </form>
                    <?php else: ?>
                        <span><?= htmlspecialchars($cm['comment_text'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ((int)$cm['user_id'] === $user_id): ?>
                            <span style="float:right; display:flex; gap:6px;">
                                <a href="index.php?edit_comment=<?= $cid ?>#post-<?= $pid ?>" style="color:#1565c0; text-decoration:none; font-size:12px;">✏️ แก้ไข</a>
                                <form method="POST" action="index.php" style="margin:0;">
                                    <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                                    <input type="hidden" name="delete_comment_id" value="<?= $cid ?>">
                                    <button type="submit" style="background:none; border:none; color:#c62828; cursor:pointer; font-size:12px; padding:0;">🗑️ ลบ</button>
                                </form>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <form method="POST" action="index.php" style="display:flex; gap:8px; margin-top:10px;">
                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                <input type="hidden" name="add_comment_post_id" value="<?= $pid ?>">
                <input type="text" name="comment_text" placeholder="เขียนความคิดเห็น..." required style="flex:1; padding:8px 14px; border-radius:14px; border:1px solid #ffccd5; background:#fff8f9; font-family:'Kanit';">
                <button type="submit" class="btn-post-send" style="padding:6px 16px;">ส่ง</button>
            </form>
        </div>
        <?php endforeach; ?>

        <div class="feed-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <img src="assets/images/student.jpg" alt="User Avatar" style="width:42px; height:42px; border-radius:50%; object-fit:cover;">
                    <div>
                        <strong style="font-size: 15px; color: #333;">CatLover_Official 🐾</strong>
                        <div style="font-size: 12px; color: #888;">2 ชั่วโมงที่แล้ว</div>
                    </div>
                </div>
            </div>
            <p style="font-size: 14px; line-height: 1.6; color: #333;">
                น้องเหมียวตัวน้อยหาบ้านใหม่ครับ! น้องอายุ 2 เดือน สุขภาพแข็งแรง ขี้อ้อนมาก กินเก่ง ขับถ่ายในกระบะทรายเป็นแล้วครับ ท่านใดสนใจรับเลี้ยงติดต่อได้เลยครับ 💖🐾
            </p>
            <img src="assets/images/cat.png" style="width: 100%; max-height: 350px; border-radius: 14px; object-fit: cover; margin-top: 8px;">
        </div>
    </main>

    <?php if (file_exists('config/rightbar.php')) include 'config/rightbar.php'; ?>
</div>
</body>
</html>