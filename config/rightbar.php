<?php
// ตำแหน่งไฟล์: login/config/rightbar.php
date_default_timezone_set('Asia/Bangkok');

$uid = (int)($_SESSION['user_id'] ?? 0);
$current_page = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$is_en = (($_SESSION['site_lang'] ?? 'th') === 'en');

// 1. บันทึกเวลาเข้าใช้งานจริงและดึงสถิติจาก MySQL
$today_users = 0;
$today_visits = 0;
$total_members = 0;
$last_visit_time = date('d/m/Y H:i:s');

if (isset($conn) && $conn instanceof mysqli) {
    $conn->query("CREATE TABLE IF NOT EXISTS visit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        page_name VARCHAR(100) NOT NULL,
        visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if ($uid > 0) {
        $now_str = date('Y-m-d H:i:s');
        $ins = $conn->prepare("INSERT INTO visit_logs (user_id, page_name, visited_at) VALUES (?, ?, ?)");
        if ($ins) {
            $ins->bind_param("iss", $uid, $current_page, $now_str);
            $ins->execute();
            $ins->close();
        }

        $q_last = $conn->prepare("SELECT MAX(visited_at) AS last_seen FROM visit_logs WHERE user_id = ?");
        if ($q_last) {
            $q_last->bind_param("i", $uid);
            $q_last->execute();
            $row_last = $q_last->get_result()->fetch_assoc();
            if (!empty($row_last['last_seen'])) {
                $last_visit_time = date('d/m/Y H:i:s', strtotime($row_last['last_seen']));
            }
            $q_last->close();
        }
    }

    $res_today = $conn->query("SELECT COUNT(DISTINCT user_id) AS u_cnt, COUNT(*) AS v_cnt FROM visit_logs WHERE DATE(visited_at) = CURDATE()");
    if ($res_today && $r = $res_today->fetch_assoc()) {
        $today_users = (int)$r['u_cnt'];
        $today_visits = (int)$r['v_cnt'];
    }

    $res_mem = $conn->query("SELECT COUNT(*) AS m_cnt FROM users");
    if ($res_mem && $rm = $res_mem->fetch_assoc()) {
        $total_members = (int)$rm['m_cnt'];
    }
}

$today_posts = !empty($_SESSION['user_posts']) ? count($_SESSION['user_posts']) : 0;
$total_likes = 0;

// 2. ประกาศตัวแปรปฏิทินในตัว (แก้ Error Undefined variable $month / $year)
$rb_month = isset($_GET['month']) ? max(1, min(12, (int)$_GET['month'])) : (int)date('n');
$rb_year  = isset($_GET['year'])  ? (int)$_GET['year'] : (int)date('Y');
$rb_days_in_month = cal_days_in_month(CAL_GREGORIAN, $rb_month, $rb_year);
$rb_first_day = (int)date('w', strtotime(sprintf('%04d-%02d-01', $rb_year, $rb_month)));

$rb_months_th = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
$rb_months_en = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
$rb_month_name = $is_en ? $rb_months_en[$rb_month] : $rb_months_th[$rb_month];
$rb_display_year = $is_en ? $rb_year : ($rb_year + 543);
$rb_days_header = $is_en ? ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] : ['อา','จ','อ','พ','พฤ','ศ','ส'];

// 3. คำนวณเวลาปัจจุบัน 4 ประเทศด้วย Pure PHP
$tz_th = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('H:i');
$tz_jp = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('H:i');
$tz_us = (new DateTime('now', new DateTimeZone('America/New_York')))->format('H:i');
$tz_uk = (new DateTime('now', new DateTimeZone('Europe/London')))->format('H:i');

// ตรวจสอบรูปโปรไฟล์ด้วย Pure PHP (ไม่ใช้ onerror JS)
$raw_av = !empty($current_user['avatar']) ? $current_user['avatar'] : (!empty($_SESSION['avatar']) ? $_SESSION['avatar'] : 'student.jpg');
$rb_avatar = is_file(__DIR__ . '/../assets/images/' . basename($raw_av)) ? basename($raw_av) : 'student.jpg';
?>
<aside class="right-sidebar">
    <!-- โปรไฟล์ย่อ -->
    <div class="widget-card profile-summary-card">
        <img src="assets/images/<?= htmlspecialchars($rb_avatar, ENT_QUOTES, 'UTF-8') ?>" alt="Profile" class="widget-avatar">
        <h3><?= $is_en ? 'Profile' : 'โปรไฟล์ (Profile)' ?></h3>
        <p class="widget-username"><?= htmlspecialchars($current_user['full_name'] ?? ($_SESSION['full_name'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></p>
        <a href="profile.php" class="btn-view-profile"><?= $is_en ? 'View My Profile →' : 'ดูโปรไฟล์ของฉัน →' ?></a>
    </div>

    <!-- ปฏิทินเดือนปัจจุบัน -->
    <div class="widget-card calendar-card">
        <div class="calendar-header">
            <h4>📅 <?= $rb_month_name ?> <?= $rb_display_year ?></h4>
        </div>
        <table class="calendar-table">
            <thead>
                <tr>
                    <?php foreach ($rb_days_header as $dh): ?>
                        <th><?= $dh ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                <?php
                $d = 1;
                $today_j = (int)date('j');
                $today_n = (int)date('n');
                $today_y = (int)date('Y');
                for ($i = 0; $i < 42; $i++) {
                    if ($i >= $rb_first_day && $d <= $rb_days_in_month) {
                        $is_today = ($d === $today_j && $rb_month === $today_n && $rb_year === $today_y);
                        echo $is_today ? "<td><span class='cal-active'>{$d}</span></td>" : "<td>{$d}</td>";
                        $d++;
                    } else {
                        echo "<td></td>";
                    }
                    if ($i % 7 == 6 && $d <= $rb_days_in_month) echo "</tr><tr>";
                    elseif ($i % 7 == 6) break;
                }
                ?>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- เวลาปัจจุบันตามประเทศ (Pure PHP) -->
    <div class="widget-box" style="text-align: left;">
        <h4 style="font-size: 13px; color: #d81b60; margin: 0 0 8px 0;">🕒 <?= $is_en ? 'World Time' : 'เวลาปัจจุบันตามประเทศ' ?></h4>
        <ul class="world-time-list">
            <li class="world-time-item"><span>TH ไทย (UTC+7)</span> <strong><?= $tz_th ?></strong></li>
            <li class="world-time-item"><span>JP ญี่ปุ่น (UTC+9)</span> <strong><?= $tz_jp ?></strong></li>
            <li class="world-time-item"><span>US สหรัฐฯ (UTC-4/5)</span> <strong><?= $tz_us ?></strong></li>
            <li class="world-time-item"><span>GB สหราชอาณาจักร</span> <strong><?= $tz_uk ?></strong></li>
        </ul>
    </div>

    <!-- สถิติการใช้งานจริงจาก MySQL -->
    <div class="widget-card stats-card">
        <h4>📊 <?= $is_en ? 'Real Usage Stats' : 'สถิติการใช้งานจริง' ?></h4>
        <ul>
            <li><span>👥 <?= $is_en ? 'Users Today' : 'ผู้ใช้งานวันนี้' ?></span> <strong><?= number_format($today_users) ?> <?= $is_en ? '' : 'คน' ?></strong></li>
            <li><span>👀 <?= $is_en ? 'Visits Today' : 'เข้าชมวันนี้' ?></span> <strong><?= number_format($today_visits) ?> <?= $is_en ? '' : 'ครั้ง' ?></strong></li>
            <li><span>🧑‍🤝‍🧑 <?= $is_en ? 'Total Members' : 'สมาชิกทั้งหมด' ?></span> <strong><?= number_format($total_members) ?> <?= $is_en ? '' : 'คน' ?></strong></li>
            <li><span>💬 <?= $is_en ? 'New Posts' : 'โพสต์ใหม่วันนี้' ?></span> <strong><?= number_format($today_posts) ?> <?= $is_en ? '' : 'โพสต์' ?></strong></li>
            <li><span>💖 <?= $is_en ? 'Total Likes' : 'ผู้ถูกใจทั้งหมด' ?></span> <strong><?= number_format($total_likes) ?> <?= $is_en ? '' : 'ครั้ง' ?></strong></li>
            <li><span>🕒 <?= $is_en ? 'Last Visit' : 'เข้าใช้งานล่าสุด' ?></span> <strong><?= htmlspecialchars($last_visit_time, ENT_QUOTES, 'UTF-8') ?></strong></li>
        </ul>
    </div>
</aside>