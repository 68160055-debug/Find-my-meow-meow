<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'config/db.php';
require_once 'config/lang.php'; // เพิ่มบรรทัดนี้
require_login();

$user_id = $_SESSION['user_id'];

// รายการแมวแยกตามสายพันธุ์และระบุไฟล์รูปภาพ
$cat_list = [
    [
        'id' => 1,
        'name' => 'Mochi',
        'price' => '12,000',
        'breed' => 'บริติช ชอร์ตแฮร์',
        'img' => 'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?w=600&auto=format&fit=crop&q=80' // รูปบริติช ชอร์ตแฮร์
    ],
    [
        'id' => 2,
        'name' => 'Leo',
        'price' => '8,500',
        'breed' => 'เปอร์เซีย',
        'img' => 'https://images.unsplash.com/photo-1518791841217-8f162f1e1131?w=600&auto=format&fit=crop&q=80' // รูปเปอร์เซียสีส้ม
    ],
    [
        'id' => 3,
        'name' => 'Luna',
        'price' => '8,500',
        'breed' => 'สก็อตติช โฟลด์',
        'img' => 'https://images.unsplash.com/photo-1573865526739-10659fec78a5?w=600&auto=format&fit=crop&q=80' // รูปสก็อตติช โฟลด์
    ],
    [
        'id' => 4,
        'name' => 'Lonera',
        'price' => '12,000',
        'breed' => 'เปอร์เซีย',
        'img' => 'https://images.unsplash.com/photo-1543852786-1cf6624b9987?w=600&auto=format&fit=crop&q=80' // รูปเปอร์เซีย
    ],
    [
        'id' => 5,
        'name' => 'Lunnavan',
        'price' => '12,000',
        'breed' => 'เปอร์เซีย',
        'img' => 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=600&auto=format&fit=crop&q=80' // รูปเปอร์เซียขาว/ครีม
    ],
    [
        'id' => 6,
        'name' => 'Lluna',
        'price' => '12,000',
        'breed' => 'สก็อตติช โฟลด์',
        'img' => 'https://images.unsplash.com/photo-1533738363-b7f9aef128ce?w=600&auto=format&fit=crop&q=80' // รูปสก็อตติช โฟลด์
    ]
];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ซื้อแมว (Buy Cat) - Find my meow meow 💖</title>
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
        .catalog-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; }
        .cat-card { background: white; border-radius: 18px; padding: 15px; border: 1px solid #ffccd5; box-shadow: 0 4px 15px rgba(0,0,0,0.02); text-align: center; }
        
        /* สไตล์สำหรับรูปภาพในการ์ด */
        .cat-card-img-box {
            width: 100%;
            height: 170px;
            border-radius: 14px;
            overflow: hidden;
            margin-bottom: 10px;
            background: #ffe6ea;
        }
        .cat-card-img-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .cat-card:hover .cat-card-img-box img {
            transform: scale(1.05);
        }

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
        .cal-past { color: #333; cursor: pointer; }
        .cal-future { color: #ccc; cursor: not-allowed; }
        .world-time-list { list-style: none; padding: 0; margin: 0; text-align: left; font-size: 13px; }
        .world-time-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #ffccd5; }
    </style>
</head>
<body>

<div class="dashboard-layout">
    <aside class="dash-sidebar">
        <div class="dash-brand">🐾 Find my meow meow</div>
        <a href="index.php" class="dash-nav-item">🏠 หน้าแรก</a>
        <a href="profile.php" class="dash-nav-item">👤 ประวัติผู้ใช้งาน</a>
        <a href="contact.php" class="dash-nav-item">✉️ ติดต่อผู้ประกาศ/แอดมิน</a>
        <a href="buy_cat.php" class="dash-nav-item active">🛒 ซื้อแมว</a>
        <a href="adopter.php" class="dash-nav-item">💬 ติดต่อผู้ขอรับเลี้ยง</a>
        <a href="settings.php" class="dash-nav-item">⚙️ การตั้งค่า</a>
        <div style="margin-top: auto;">
            <a href="logout.php" class="dash-nav-item" style="background: #fff0f3; color: #d81b60; border: 1px solid #ffccd5;">🚪 ออกจากระบบ</a>
        </div>
    </aside>

    <main class="dash-main">
        <h2 style="color: #d81b60; margin-top: 0;">🛒 ซื้อแมว (Buy Cat) 🐾</h2>
        <?php include 'config/search.php'; ?>   
        <input type="text" placeholder="🔍 ค้นหาแมวที่คุณรัก (Search your cat)..." style="width: 100%; padding: 12px 20px; border-radius: 20px; border: 1px solid #ffccd5; background: white; font-family: 'Kanit', sans-serif;">

        <div class="catalog-grid">
            <?php foreach($cat_list as $cat): ?>
            <div class="cat-card">
                <div class="cat-card-img-box">
                    <img src="<?= htmlspecialchars($cat['img']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>">
                </div>
                <h4>ชื่อ <?= htmlspecialchars($cat['name']) ?></h4>
                <div class="cat-price">ราคา <?= htmlspecialchars($cat['price']) ?> บาท</div>
                <div class="cat-breed">สายพันธุ์ <?= htmlspecialchars($cat['breed']) ?></div>
                <a href="cat_detail.php?id=<?= $cat['id']; ?>" class="btn-buy">🛒 สั่งซื้อ / ดูรายละเอียด</a>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

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
        if ($i % 7 == 6) echo "</tr><tr>";
    }
    ?>
    </tr>
</table>
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

<script>
    function updateWorldTimes() {
        const now = new Date();
        document.getElementById('time-th').innerText = now.toLocaleTimeString('en-GB', { timeZone: 'Asia/Bangkok', hour: '2-digit', minute: '2-digit' });
        document.getElementById('time-jp').innerText = now.toLocaleTimeString('en-GB', { timeZone: 'Asia/Tokyo', hour: '2-digit', minute: '2-digit' });
        document.getElementById('time-us').innerText = now.toLocaleTimeString('en-GB', { timeZone: 'America/New_York', hour: '2-digit', minute: '2-digit' });
        document.getElementById('time-uk').innerText = now.toLocaleTimeString('en-GB', { timeZone: 'Europe/London', hour: '2-digit', minute: '2-digit' });
    }
    setInterval(updateWorldTimes, 1000); updateWorldTimes();
</script>
</body>
</html>