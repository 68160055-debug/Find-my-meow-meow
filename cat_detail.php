<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'config/db.php';
require_once 'config/lang.php'; // เพิ่มบรรทัดนี้
require_login();

$user_id = $_SESSION['user_id'];
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_day_of_week = date('w', strtotime("$year-$month-01"));
$month_names_th = [1=>'มกราคม', 2=>'กุมภาพันธ์', 3=>'มีนาคม', 4=>'เมษายน', 5=>'พฤษภาคม', 6=>'มิถุนายน', 7=>'กรกฎาคม', 8=>'สิงหาคม', 9=>'กันยายน', 10=>'ตุลาคม', 11=>'พฤศจิกายน', 12=>'ธันวาคม'];

// ฐานข้อมูลแมวตัวอย่างตามสายพันธุ์
$cat_list = [
    1 => [
        'name' => 'แมวบริติช ชอร์ตแฮร์ หาบ้านใหม่',
        'normal_price' => 12000,
        'sale_price' => 10000,
        'breed' => 'บริติช ชอร์ตแฮร์',
        'img' => 'https://images.unsplash.com/photo-1592194996308-7b43878e84a6?w=800&auto=format&fit=crop&q=80',
        'desc' => 'น้องแมวสุขภาพดี ร่าเริง เลี้ยงดูอย่างดี พร้อมย้ายบ้าน สนใจสามารถติดต่อสอบถามได้ค่ะ'
    ],
    2 => [
        'name' => 'น้อง Leo เปอร์เซียแท้ขนฟู',
        'normal_price' => 10000,
        'sale_price' => 8500,
        'breed' => 'เปอร์เซีย',
        'img' => 'https://images.unsplash.com/photo-1518791841217-8f162f1e1131?w=800&auto=format&fit=crop&q=80',
        'desc' => 'เปอร์เซียแท้ ขนแน่น นุ่ม สุขภาพแข็งแรง ขี้อ้อนมาก'
    ],
    3 => [
        'name' => 'น้อง Luna สก็อตติช โฟลด์ หูพับ',
        'normal_price' => 9900,
        'sale_price' => 8500,
        'breed' => 'สก็อตติช โฟลด์',
        'img' => 'https://images.unsplash.com/photo-1573865526739-10659fec78a5?w=800&auto=format&fit=crop&q=80',
        'desc' => 'สก็อตติช โฟลด์ หูพับ น่ารัก ทานเก่ง ขับถ่ายในกระบะทรายเป็นแล้ว'
    ],
    4 => [
        'name' => 'Lonera สาวน้อยเปอร์เซียสีส้ม',
        'normal_price' => 13500,
        'sale_price' => 12000,
        'breed' => 'เปอร์เซีย',
        'img' => 'https://images.unsplash.com/photo-1543852786-1cf6624b9987?w=800&auto=format&fit=crop&q=80',
        'desc' => 'เพศเมีย สุขภาพดี ได้รับวัคซีนเข็มแรกแล้ว'
    ],
    5 => [
        'name' => 'Lunnavan หนุ่มน้อยจอมซน',
        'normal_price' => 14000,
        'sale_price' => 12000,
        'breed' => 'เปอร์เซีย',
        'img' => 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=800&auto=format&fit=crop&q=80',
        'desc' => 'ขี้เล่น ซน น่ารักมาก เข้ากับคนง่าย'
    ],
    6 => [
        'name' => 'Lluna สก็อตติช โฟลด์ ตาแป๋ว',
        'normal_price' => 12000,
        'sale_price' => 12000,
        'breed' => 'สก็อตติช โฟลด์',
        'img' => 'https://images.unsplash.com/photo-1533738363-b7f9aef128ce?w=800&auto=format&fit=crop&q=80',
        'desc' => 'ตาแป๋ว ขนฟู แข็งแรง พร้อมย้ายบ้านทันที'
    ]
];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 1;
$cat = $cat_list[$id] ?? $cat_list[1];

// คำนวณส่วนลด
$discount_amount = max(0, $cat['normal_price'] - $cat['sale_price']);
$discount_percent = $cat['normal_price'] > 0 ? round(($discount_amount / $cat['normal_price']) * 100, 2) : 0;
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายละเอียดโพสต์ขายแมว - Find my meow meow 💖</title>
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
        
        .post-card-container {
            background: #ffffff;
            border-radius: 20px;
            padding: 30px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            max-width: 650px;
            margin: 0 auto;
        }
        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .post-header h3 { margin: 0; font-size: 18px; color: #333; font-weight: 600; }
        .badge-preview {
            background: #e8f5e9;
            color: #2e7d32;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 500;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; color: #777; margin-bottom: 6px; }
        .form-input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #dcdcdc;
            border-radius: 10px;
            font-family: 'Kanit', sans-serif;
            font-size: 14px;
            color: #333;
            background: #fff;
        }
        .image-preview-box {
            position: relative;
            width: 100%;
            height: 330px;
            background: #fdf2f4;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 8px;
            border: 1px solid #ffccd5;
        }
        .image-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-helper {
            font-size: 12px;
            color: #888;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 18px;
        }
        .price-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .discount-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .discount-badge {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 12px;
            margin-top: 4px;
            display: inline-block;
        }
        .discount-val {
            font-weight: bold;
            font-size: 16px;
            color: #222;
        }
        .visibility-note {
            font-size: 13px;
            color: #555;
            margin: 15px 0 20px 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .visibility-note span.title { font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .visibility-note span.desc { color: #888; font-size: 12px; }
        .btn-submit-post {
            width: 100%;
            background: #ff5277;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            font-family: 'Kanit', sans-serif;
            transition: 0.2s;
        }
        .btn-submit-post:hover { background: #e03b60; }
        .footer-note {
            text-align: center;
            font-size: 11px;
            color: #999;
            margin-top: 15px;
        }
        .btn-back {
            display: inline-block;
            margin-bottom: 15px;
            color: #d81b60;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }

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
        <a href="buy_cat.php" class="btn-back">‹ ย้อนกลับไปหน้าซื้อแมว</a>

        <div class="post-card-container">
            <div class="post-header">
                <h3>ตัวอย่างหน้าสร้างโพสต์ขายแมว</h3>
                <span class="badge-preview">ตัวอย่างการออกแบบ</span>
            </div>

            <div class="form-group">
                <label>หัวข้อโพสต์</label>
                <input type="text" class="form-input" value="<?= htmlspecialchars($cat['name']) ?>">
            </div>

            <div class="form-group">
                <label>รายละเอียดโพสต์</label>
                <textarea class="form-input" rows="2" style="resize:none;"><?= htmlspecialchars($cat['desc']) ?></textarea>
            </div>

            <div class="form-group">
                <label>รูปภาพแมว</label>
                <!-- กล่องแสดงรูปแมวของสายพันธุ์นั้นๆ -->
                <div class="image-preview-box">
                    <img src="<?= htmlspecialchars($cat['img']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>">
                </div>
                <div class="image-helper">
                    <span>🖼️ เพิ่มรูปภาพหลายรูปได้</span>
                </div>
            </div>

            <div class="price-row">
                <div class="form-group">
                    <label>ราคาปกติ (บาท)</label>
                    <input type="text" class="form-input" value="<?= number_format($cat['normal_price']) ?>">
                </div>
                <div class="form-group">
                    <label>ราคาขาย (บาท)</label>
                    <input type="text" class="form-input" value="<?= number_format($cat['sale_price']) ?>">
                </div>
            </div>

            <div class="discount-row">
                <div>
                    <div style="font-size: 13px; color: #777;">ส่วนลด</div>
                    <?php if($discount_percent > 0): ?>
                        <span class="discount-badge">ลดราคา <?= $discount_percent ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="discount-val">
                    <?= number_format($discount_amount) ?> บาท
                </div>
            </div>

            <div class="form-group">
                <label>เลือกประเภทโพสต์</label>
                <input type="text" class="form-input" value="🛍️ ประกาศขายแมว" readonly>
            </div>

            <div class="visibility-note">
                <span class="title">🌐 การเผยแพร่: สาธารณะ</span>
                <span class="desc">บุคคลทั่วไปสามารถมองเห็นโพสต์ได้</span>
            </div>

            <button type="button" class="btn-submit-post">เผยแพร่โพสต์</button>
            <div class="footer-note">ภาพนี้เป็นตัวอย่างหน้าตาและข้อมูล ไม่ใช่แบบฟอร์มที่เชื่อมต่อฐานข้อมูลจริง</div>
        </div>
    </main>

    <!-- แถบปฏิทินด้านขวา -->
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
        </div>
    </aside>
</div>
</body>
</html>
        
<?php
// คำนวณเวลาปัจจุบันด้วย Pure PHP (ไม่ใช้ JavaScript)
$tz_th = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('H:i');
$tz_jp = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('H:i');
$tz_us = (new DateTime('now', new DateTimeZone('America/New_York')))->format('H:i');
$tz_uk = (new DateTime('now', new DateTimeZone('Europe/London')))->format('H:i');
?>
<div class="widget-box" style="text-align: left;">
    <h4 style="font-size: 13px; color: #d81b60; margin: 0 0 8px 0;">🕒 เวลาปัจจุบันตามประเทศ</h4>
    <ul class="world-time-list">
        <li class="world-time-item"><span>TH ไทย (UTC+7)</span> <strong><?= $tz_th ?></strong></li>
        <li class="world-time-item"><span>JP ญี่ปุ่น (UTC+9)</span> <strong><?= $tz_jp ?></strong></li>
        <li class="world-time-item"><span>US สหรัฐฯ (UTC-4/5)</span> <strong><?= $tz_us ?></strong></li>
        <li class="world-time-item"><span>GB สหราชอาณาจักร</span> <strong><?= $tz_uk ?></strong></li>
    </ul>
</div>
</body>
</html>