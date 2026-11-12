<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

require_once 'auth.php';
require_once 'config/db.php';
require_once 'config/lang.php';
require_login();

$user_id = $_SESSION['user_id'];
$is_en = ($current_lang === 'en');

// ดึงข้อมูลผู้ใช้งานปัจจุบันสำหรับแสดงบนแถบค้นหา
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

// รายการแมวแยกตามสายพันธุ์และระบุไฟล์รูปภาพ
$cat_list = [
    [
        'id' => 1,
        'name' => 'Mochi',
        'price' => '12,000',
        'breed' => 'บริติช ชอร์ตแฮร์',
        'img' => 'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?w=600&auto=format&fit=crop&q=80'
    ],
    [
        'id' => 2,
        'name' => 'Leo',
        'price' => '8,500',
        'breed' => 'เปอร์เซีย',
        'img' => 'https://images.unsplash.com/photo-1518791841217-8f162f1e1131?w=600&auto=format&fit=crop&q=80'
    ],
    [
        'id' => 3,
        'name' => 'Luna',
        'price' => '8,500',
        'breed' => 'สก็อตติช โฟลด์',
        'img' => 'https://images.unsplash.com/photo-1573865526739-10659fec78a5?w=600&auto=format&fit=crop&q=80'
    ],
    [
        'id' => 4,
        'name' => 'Lonera',
        'price' => '12,000',
        'breed' => 'เปอร์เซีย',
        'img' => 'https://images.unsplash.com/photo-1543852786-1cf6624b9987?w=600&auto=format&fit=crop&q=80'
    ],
    [
        'id' => 5,
        'name' => 'Lunnavan',
        'price' => '12,000',
        'breed' => 'เปอร์เซีย',
        'img' => 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=600&auto=format&fit=crop&q=80'
    ],
    [
        'id' => 6,
        'name' => 'Lluna',
        'price' => '12,000',
        'breed' => 'สก็อตติช โฟลด์',
        'img' => 'https://images.unsplash.com/photo-1533738363-b7f9aef128ce?w=600&auto=format&fit=crop&q=80'
    ]
];

// ระบบค้นหาและกรองสายพันธุ์แมวด้วย Pure PHP (GET)
$cat_q = trim((string)($_GET['cat_q'] ?? ''));
$breed_filter = trim((string)($_GET['breed'] ?? ''));
$filtered_cats = array_filter($cat_list, function ($cat) use ($cat_q, $breed_filter) {
    $match_q = ($cat_q === '') || (stripos($cat['name'], $cat_q) !== false) || (stripos($cat['breed'], $cat_q) !== false);
    $match_b = ($breed_filter === '') || ($cat['breed'] === $breed_filter);
    return $match_q && $match_b;
});

// คำนวณเวลาปัจจุบัน 4 ประเทศด้วย Pure PHP (ไม่ใช้ JavaScript)
$tz_th = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('H:i');
$tz_jp = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('H:i');
$tz_us = (new DateTime('now', new DateTimeZone('America/New_York')))->format('H:i');
$tz_uk = (new DateTime('now', new DateTimeZone('Europe/London')))->format('H:i');
?>
<!doctype html>
<html lang="<?= $current_lang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $is_en ? 'Buy Cat - Find my meow meow 💖' : 'ซื้อแมว (Buy Cat) - Find my meow meow 💖' ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
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
        .filter-bar { display: flex; gap: 10px; align-items: center; }
        .filter-bar input { flex: 1; padding: 12px 20px; border-radius: 20px; border: 1px solid #ffccd5; background: white; font-family: 'Kanit', sans-serif; outline: none; }
        .filter-bar select { padding: 12px 16px; border-radius: 20px; border: 1px solid #ffccd5; background: white; font-family: 'Kanit', sans-serif; outline: none; }
        .filter-bar button { background: #d81b60; color: white; border: none; padding: 12px 22px; border-radius: 20px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; }
        .catalog-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; }
        .cat-card { background: white; border-radius: 18px; padding: 15px; border: 1px solid #ffccd5; box-shadow: 0 4px 15px rgba(0,0,0,0.02); text-align: center; }
        .cat-card-img-box { width: 100%; height: 170px; border-radius: 14px; overflow: hidden; margin-bottom: 10px; background: #ffe6ea; }
        .cat-card-img-box img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
        .cat-card:hover .cat-card-img-box img { transform: scale(1.05); }
        .cat-card h4 { margin: 5px 0; color: #333; font-size: 16px; }
        .cat-price { color: #d81b60; font-weight: bold; font-size: 14px; margin-bottom: 4px; }
        .cat-breed { color: #888; font-size: 12px; margin-bottom: 12px; }
        .btn-buy { background: #ff758c; color: white; border: none; padding: 8px 18px; border-radius: 15px; font-weight: 600; cursor: pointer; font-family: 'Kanit', sans-serif; text-decoration: none; display: inline-block; font-size: 13px; transition: all 0.2s; }
        .btn-buy:hover { background: #d81b60; }
        .dash-right { background: #ffffff; border-left: 1px solid #ffccd5; padding: 25px 20px; display: flex; flex-direction: column; gap: 20px; position: sticky; top: 0; height: 100vh; }
        .widget-box { background: #fff8f9; border-radius: 18px; padding: 18px; border: 1px solid #ffccd5; text-align: center; }
        .calendar-table { width: 100%; font-size: 13px; border-collapse: collapse; text-align: center; margin-top: 10px; }
        .calendar-table td, .calendar-table th { padding: 6px 2px; }
        .cal-today { background: #d81b60; color: white; border-radius: 50%; display: inline-block; width: 24px; height: 24px; line-height: 24px; font-weight: 600; }
        .world-time-list { list-style: none; padding: 0; margin: 0; text-align: left; font-size: 13px; }
        .world-time-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #ffccd5; }
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
        <a href="buy_cat.php" class="dash-nav-item active"><?= $L['nav_buy'] ?></a>
        <a href="adopter.php" class="dash-nav-item"><?= $L['nav_chat'] ?></a>
        <a href="settings.php" class="dash-nav-item"><?= $L['nav_settings'] ?></a>
        <div style="margin-top: auto;">
            <form action="logout.php" method="POST" style="margin: 0;">
                <input type="hidden" name="auth_csrf" value="<?= auth_escape(auth_token()) ?>">
                <button type="submit" class="dash-nav-item" style="width: 100%; background: #fff0f3; color: #d81b60; border: 1px solid #ffccd5; cursor: pointer; font-family: 'Kanit', sans-serif;"><?= $L['nav_logout'] ?></button>
            </form>
        </div>
    </aside>

    <!-- พื้นที่หลักตรงกลาง -->
    <main class="dash-main">
        <h2 style="color: #d81b60; margin-top: 0;"><?= $is_en ? '🛒 Buy Cat 🐾' : '🛒 ซื้อแมว (Buy Cat) 🐾' ?></h2>
        <?php if (file_exists('config/search.php')) include 'config/search.php'; ?>

        <!-- ฟอร์มค้นหาและกรองสายพันธุ์แมว (Pure PHP) -->
        <form method="GET" action="buy_cat.php" class="filter-bar">
            <input type="text" name="cat_q" value="<?= htmlspecialchars($cat_q, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= $is_en ? '🔍 Search your cat by name or breed...' : '🔍 ค้นหาแมวที่คุณรัก (ชื่อ หรือ สายพันธุ์)...' ?>">
            <select name="breed">
                <option value=""><?= $is_en ? 'All Breeds' : 'ทุกสายพันธุ์' ?></option>
                <option value="บริติช ชอร์ตแฮร์" <?= $breed_filter === 'บริติช ชอร์ตแฮร์' ? 'selected' : '' ?>>บริติช ชอร์ตแฮร์</option>
                <option value="เปอร์เซีย" <?= $breed_filter === 'เปอร์เซีย' ? 'selected' : '' ?>>เปอร์เซีย</option>
                <option value="สก็อตติช โฟลด์" <?= $breed_filter === 'สก็อตติช โฟลด์' ? 'selected' : '' ?>>สก็อตติช โฟลด์</option>
            </select>
            <button type="submit"><?= $L['search_btn'] ?? 'ค้นหา' ?></button>
        </form>

        <div class="catalog-grid">
            <?php if (empty($filtered_cats)): ?>
                <p style="grid-column: 1 / -1; text-align: center; color: #888; padding: 30px 0;">
                    <?= $is_en ? 'No cats matched your search.' : 'ไม่พบน้องแมวที่ตรงกับเงื่อนไขการค้นหา' ?>
                </p>
            <?php else: ?>
                <?php foreach ($filtered_cats as $cat): ?>
                <div class="cat-card">
                    <div class="cat-card-img-box">
                        <img src="<?= htmlspecialchars($cat['img'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <h4><?= $is_en ? 'Name: ' : 'ชื่อ ' ?><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                    <div class="cat-price"><?= $is_en ? 'Price: ' : 'ราคา ' ?><?= htmlspecialchars($cat['price'], ENT_QUOTES, 'UTF-8') ?><?= $is_en ? ' THB' : ' บาท' ?></div>
                    <div class="cat-breed"><?= $is_en ? 'Breed: ' : 'สายพันธุ์ ' ?><?= htmlspecialchars($cat['breed'], ENT_QUOTES, 'UTF-8') ?></div>
                    <a href="cat_detail.php?id=<?= (int)$cat['id'] ?>" class="btn-buy"><?= $is_en ? '🛒 Order / Details' : '🛒 สั่งซื้อ / ดูรายละเอียด' ?></a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- แถบปฏิทินและเวลาด้านขวา -->
    <aside class="dash-right">
        <div class="widget-box">
            <?php
            $month_name = $L['months'][$month];
            $display_year = $is_en ? $year : ($year + 543);
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