<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'config/db.php';
require_login();

$user_id = $_SESSION['user_id'] ?? 0;

// ดึงข้อมูลผู้ใช้งานปัจจุบัน
$stmt = $conn->prepare("SELECT full_name, email, avatar, nickname FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$q = trim((string)($_GET['q'] ?? ''));

// -------------------------------------------------------------
// ระบบถอดรหัสคำค้นหาตามหลัก Google Search (Query Parser)
// -------------------------------------------------------------
$exact_phrases = [];
$exclude_terms = [];
$or_terms = [];
$and_terms = [];

if ($q !== '') {
    // 1. ตรวจหาเครื่องหมายคำพูด ("...") สำหรับวลีเป๊ะๆ
    preg_match_all('/"([^"]+)"/', $q, $matches);
    $exact_phrases = $matches[1];
    $clean_q = preg_replace('/"[^"]+"/', ' ', $q);

    // 2. ตรวจหาเครื่องหมายลบ (-คำ) สำหรับตัดคำที่ไม่ต้องการออก
    preg_match_all('/(?:\s|^)-(\S+)/', $clean_q, $matches);
    $exclude_terms = $matches[1];
    $clean_q = preg_replace('/(?:\s|^)-\S+/', ' ', $clean_q);

    // 3. ตรวจหาคำสั่ง OR
    if (preg_match('/\bOR\b/', $clean_q)) {
        $parts = preg_split('/\bOR\b/', $clean_q);
        foreach ($parts as $p) {
            $t = trim($p);
            if ($t !== '') $or_terms[] = $t;
        }
    } else {
        // 4. คำค้นหาทั่วไปที่ต้องมีครบทุกคำ (AND)
        $raw_words = explode(' ', trim($clean_q));
        foreach ($raw_words as $w) {
            $t = trim($w);
            if ($t !== '') $and_terms[] = $t;
        }
    }
}

// ฟังก์ชันตรวจสอบข้อความว่าตรงตามเงื่อนไขของ Google หรือไม่
function match_google_query($text, $exact_phrases, $exclude_terms, $and_terms, $or_terms) {
    if ($text === '') return false;
    $lower = mb_strtolower($text, 'UTF-8');

    // กฎที่ 1: ต้องไม่มีคำใน exclude_terms (-)
    foreach ($exclude_terms as $ex) {
        if ($ex !== '' && mb_strpos($lower, mb_strtolower($ex, 'UTF-8')) !== false) {
            return false;
        }
    }

    // กฎที่ 2: ต้องมีทุกวลีใน exact_phrases ("...")
    foreach ($exact_phrases as $phrase) {
        if ($phrase !== '' && mb_strpos($lower, mb_strtolower($phrase, 'UTF-8')) === false) {
            return false;
        }
    }

    // กฎที่ 3: ถ้าใช้ OR ต้องมีอย่างน้อย 1 คำ
    if (!empty($or_terms)) {
        $or_matched = false;
        foreach ($or_terms as $ot) {
            if (mb_strpos($lower, mb_strtolower($ot, 'UTF-8')) !== false) {
                $or_matched = true;
                break;
            }
        }
        if (!$or_matched) return false;
    }

    // กฎที่ 4: คำทั่วไปต้องพบทั้งหมด (AND)
    foreach ($and_terms as $at) {
        if ($at !== '' && mb_strpos($lower, mb_strtolower($at, 'UTF-8')) === false) {
            return false;
        }
    }

    return true;
}

// -------------------------------------------------------------
// รวบรวมข้อมูลเพื่อทำการค้นหา (ชื่อคน, ชื่อโพสต์, แอดมิน)
// -------------------------------------------------------------
$found_users = [];
$found_admins = [];
$found_posts = [];

if ($q !== '') {
    // 1. ค้นหาในฐานข้อมูล Users (ชื่อคน และ แอดมิน)
    $res = $conn->query("SELECT id, full_name, nickname, email, avatar, status, bio FROM users");
    if ($res) {
        while ($u = $res->fetch_assoc()) {
            $search_blob = $u['full_name'] . ' ' . $u['nickname'] . ' ' . $u['email'] . ' ' . $u['status'] . ' ' . $u['bio'];
            if (match_google_query($search_blob, $exact_phrases, $exclude_terms, $and_terms, $or_terms)) {
                // แยกแอดมินหรือผู้ใช้ทั่วไป
                if (stripos($u['status'], 'admin') !== false || stripos($u['email'], 'admin') !== false || stripos($u['full_name'], 'admin') !== false) {
                    $found_admins[] = $u;
                } else {
                    $found_users[] = $u;
                }
            }
        }
    }

    // 2. ข้อมูลแอดมินเพจทางการ (Official Admin)
    $official_admins = [
        [
            'full_name' => 'CatLover_Official 🐾',
            'nickname' => 'แอดมินเพจหลัก',
            'email' => 'admin@findmymeow.com',
            'status' => 'ผู้ดูแลระบบสูงสุด (Super Admin)',
            'avatar' => 'student.jpg',
            'bio' => 'ผู้ดูแลระบบและตรวจสอบประกาศหาบ้านให้น้องแมวประจำเว็บไซต์'
        ]
    ];
    foreach ($official_admins as $adm) {
        $search_blob = $adm['full_name'] . ' ' . $adm['nickname'] . ' ' . $adm['email'] . ' ' . $adm['status'] . ' ' . $adm['bio'];
        if (match_google_query($search_blob, $exact_phrases, $exclude_terms, $and_terms, $or_terms)) {
            $found_admins[] = $adm;
        }
    }

    // 3. ค้นหาชื่อโพสต์ / รายการประกาศหาบ้านและแมว
    $catalog_posts = [
        ['title' => 'น้องเหมียวตัวน้อยหาบ้านใหม่ สุขภาพแข็งแรง ขี้อ้อนมาก', 'author' => 'CatLover_Official', 'cat_name' => 'ส้มจิ๋ว', 'breed' => 'ไทยแท้', 'price' => 'ฟรี (หาบ้าน)', 'img' => 'cat.png', 'desc' => 'น้องอายุ 2 เดือน กินเก่ง เข้ากระบะทรายเป็นแล้ว'],
        ['title' => 'เปิดรับจองลูกแมว Mochi สก็อตติชโฟลด์แท้', 'author' => 'ฟาร์มแมวเหมียวใจดี', 'cat_name' => 'Mochi', 'breed' => 'บริติช ชอร์ตแฮร์', 'price' => '12,000 บาท', 'img' => 'cat.png', 'desc' => 'น่ารัก ตาโต สุขภาพแข็งแรง ฉีดวัคซีนแล้ว'],
        ['title' => 'น้อง Leo แมวเปอร์เซียขนฟู สีส้มสดใส', 'author' => 'Neko House', 'cat_name' => 'Leo', 'breed' => 'เปอร์เซีย', 'price' => '8,500 บาท', 'img' => 'cat.png', 'desc' => 'ขนยาว นุ่มสลวย ขี้เล่น อารมณ์ดี'],
        ['title' => 'น้อง Luna แมวน้อยสีขาวแต้มส้ม น่ารักเรียบร้อย', 'author' => 'Cat Society', 'cat_name' => 'Luna', 'breed' => 'สก็อตติช โฟลด์', 'price' => '8,500 บาท', 'img' => 'cat.png', 'desc' => 'เรียบร้อย ไม่ดื้อ เลี้ยงในระบบปิด'],
        ['title' => 'น้อง Lonera แมวพันธุ์แท้ โครงสร้างดี', 'author' => 'Baan Meow', 'cat_name' => 'Lonera', 'breed' => 'เปอร์เซีย', 'price' => '12,000 บาท', 'img' => 'cat.png', 'desc' => 'โครงสร้างใหญ่ สุขภาพสมบูรณ์'],
        ['title' => 'น้อง Lunnavan เปอร์เซียสีขาวนวล น่ากอด', 'author' => 'Baan Meow', 'cat_name' => 'Lunnavan', 'breed' => 'เปอร์เซีย', 'price' => '12,000 บาท', 'img' => 'cat.png', 'desc' => 'กินอาหารเม็ดและอาหารเปียกเก่งมาก']
    ];

    foreach ($catalog_posts as $post) {
        $search_blob = $post['title'] . ' ' . $post['cat_name'] . ' ' . $post['breed'] . ' ' . $post['author'] . ' ' . $post['desc'];
        if (match_google_query($search_blob, $exact_phrases, $exclude_terms, $and_terms, $or_terms)) {
            $found_posts[] = $post;
        }
    }
}

$total_results = count($found_users) + count($found_admins) + count($found_posts);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ผลการค้นหา: <?= htmlspecialchars($q) ?> - Find my meow meow 💖</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff0f3; font-family: 'Kanit', sans-serif; color: #444; }
        .dashboard-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .dash-sidebar { background: #ffffff; border-right: 1px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 12px; position: sticky; top: 0; height: 100vh; }
        .dash-brand { font-weight: bold; font-size: 20px; color: #d81b60; margin-bottom: 15px; }
        .dash-nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 14px; text-decoration: none; color: #555; font-weight: 500; font-size: 15px; transition: all 0.2s; }
        .dash-nav-item:hover { background: #ff758c; color: white; }
        .dash-main { padding: 25px 35px; overflow-y: auto; }
        .result-card { background: white; border-radius: 16px; border: 1px solid #ffccd5; padding: 18px 22px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); transition: transform 0.2s; }
        .result-card:hover { transform: translateY(-2px); border-color: #ff758c; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-admin { background: #e3f2fd; color: #1976d2; }
        .badge-user { background: #fce4ec; color: #d81b60; }
        .badge-post { background: #e8f5e9; color: #2e7d32; }
        .filter-tip { background: #ffffff; border: 1px dashed #ff758c; border-radius: 14px; padding: 14px 18px; margin-bottom: 22px; font-size: 13px; line-height: 1.6; color: #666; }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <aside class="dash-sidebar">
        <div class="dash-brand">🐾 Find my meow meow</div>
        <a href="index.php" class="dash-nav-item">🏠 หน้าแรก</a>
        <a href="profile.php" class="dash-nav-item">👤 ประวัติผู้ใช้งาน</a>
        <a href="contact.php" class="dash-nav-item">✉️ ติดต่อผู้ประกาศ/แอดมิน</a>
        <a href="buy_cat.php" class="dash-nav-item">🛒 ซื้อแมว</a>
        <a href="adopter.php" class="dash-nav-item">💬 ติดต่อผู้ขอรับเลี้ยง</a>
        <a href="settings.php" class="dash-nav-item">⚙️ การตั้งค่า</a>
        <div style="margin-top: auto;">
            <a href="logout.php" class="dash-nav-item" style="background: #fff0f3; color: #d81b60; border: 1px solid #ffccd5;">🚪 ออกจากระบบ</a>
        </div>
    </aside>

    <main class="dash-main">
        <!-- แถบค้นหาด้านบน -->
        <?php include 'config/search.php'; ?>

        <div class="filter-tip">
            💡 <strong>เทคนิคการค้นหาขั้นสูงแบบ Google:</strong>
            ใส่วลีใน <code>"..."</code> เพื่อค้นหาคำเรียงติดกันเป๊ะๆ | ใส่เครื่องหมายลบ <code>-คำที่ไม่ต้องการ</code> เพื่อตัดผลลัพธ์ทิ้ง | ใช้ <code>คำA OR คำB</code> เพื่อค้นหาอย่างใดอย่างหนึ่ง
        </div>

        <h2 style="color: #d81b60; margin-top: 0;">
            🔍 ผลลัพธ์การค้นหา: "<?= htmlspecialchars($q) ?>"
            <span style="font-size: 15px; color: #888; font-weight: normal;">(พบทั้งหมด <?= $total_results ?> รายการ)</span>
        </h2>

        <?php if ($total_results === 0): ?>
            <div style="text-align: center; padding: 50px 20px; background: white; border-radius: 20px; border: 1px solid #ffccd5;">
                <p style="font-size: 45px; margin: 0;">🐱❓</p>
                <h3 style="color: #d81b60;">ไม่พบข้อมูลที่ตรงกับคำค้นหาของคุณ</h3>
                <p style="color: #777; font-size: 14px;">ลองเปลี่ยนคำค้นหาเป็นคำที่สั้นลง หรือใช้คำที่มีความหมายใกล้เคียงกันดูนะครับ</p>
            </div>
        <?php else: ?>

            <!-- 1. หมวดหมู่แอดมิน (Admin) -->
            <?php if (!empty($found_admins)): ?>
                <h3 style="color: #1976d2; margin-top: 25px;">🛡️ ผู้ดูแลระบบ (Admins)</h3>
                <?php foreach ($found_admins as $adm): ?>
                    <div class="result-card">
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <img src="assets/images/<?= htmlspecialchars($adm['avatar'] ?? 'student.jpg') ?>" onerror="this.src='assets/images/student.jpg';" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #1976d2;">
                            <div>
                                <span class="badge badge-admin">แอดมิน</span>
                                <h4 style="margin: 4px 0; color: #333; font-size: 16px;"><?= htmlspecialchars($adm['full_name']) ?> <small style="color: #888;">(<?= htmlspecialchars($adm['email']) ?>)</small></h4>
                                <p style="margin: 0; font-size: 13px; color: #666;"><?= htmlspecialchars($adm['bio'] ?? $adm['status'] ?? '') ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- 2. หมวดหมู่ชื่อคน / สมาชิก (Users) -->
            <?php if (!empty($found_users)): ?>
                <h3 style="color: #d81b60; margin-top: 25px;">👤 สมาชิกและผู้ขอรับเลี้ยง (Users)</h3>
                <?php foreach ($found_users as $usr): ?>
                    <div class="result-card">
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <img src="assets/images/<?= htmlspecialchars($usr['avatar'] ?? 'student.jpg') ?>" onerror="this.src='assets/images/student.jpg';" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #ff758c;">
                            <div>
                                <span class="badge badge-user">สมาชิก</span>
                                <h4 style="margin: 4px 0; color: #333; font-size: 16px;"><?= htmlspecialchars($usr['full_name']) ?> <?php if(!empty($usr['nickname'])): ?><span style="color: #888; font-weight: normal;">(<?= htmlspecialchars($usr['nickname']) ?>)</span><?php endif; ?></h4>
                                <p style="margin: 0; font-size: 13px; color: #666;">อีเมล: <?= htmlspecialchars($usr['email']) ?> | สถานะ: <?= htmlspecialchars($usr['status'] ?? '-') ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- 3. หมวดหมู่ชื่อโพสต์ / ประกาศแมว (Posts) -->
            <?php if (!empty($found_posts)): ?>
                <h3 style="color: #2e7d32; margin-top: 25px;">🐱 ชื่อโพสต์และประกาศแมว (Posts & Cats)</h3>
                <?php foreach ($found_posts as $p): ?>
                    <div class="result-card">
                        <div style="display: flex; gap: 15px;">
                            <img src="assets/images/<?= htmlspecialchars($p['img']) ?>" onerror="this.src='assets/images/cat.png';" style="width: 80px; height: 80px; border-radius: 12px; object-fit: cover;">
                            <div style="flex: 1;">
                                <span class="badge badge-post">ประกาศ</span>
                                <h4 style="margin: 4px 0; color: #333; font-size: 16px;"><?= htmlspecialchars($p['title']) ?></h4>
                                <p style="margin: 2px 0 6px 0; font-size: 13px; color: #555;"><?= htmlspecialchars($p['desc']) ?></p>
                                <small style="color: #888;">ชื่อน้อง: <strong><?= htmlspecialchars($p['cat_name']) ?></strong> | สายพันธุ์: <?= htmlspecialchars($p['breed']) ?> | ผู้โพสต์: <?= htmlspecialchars($p['author']) ?></small>
                            </div>
                            <div style="text-align: right; align-self: center;">
                                <strong style="color: #d81b60; font-size: 15px;"><?= htmlspecialchars($p['price']) ?></strong><br>
                                <a href="buy_cat.php" style="display: inline-block; margin-top: 6px; padding: 6px 14px; background: #ff758c; color: white; text-decoration: none; border-radius: 12px; font-size: 12px;">ดูรายละเอียด</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php endif; ?>
    </main>
</div>
</body>
</html>