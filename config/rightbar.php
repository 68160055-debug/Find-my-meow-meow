<!-- right_sidebar.php -->
<aside class="right-sidebar">
    <!-- โปรไฟล์ย่อ -->
    <div class="widget-card profile-summary-card">
        <img src="assets/images/<?= !empty($_SESSION['avatar']) ? htmlspecialchars($_SESSION['avatar']) : 'student.jpg' ?>" alt="Profile" class="widget-avatar">
        <h3>โปรไฟล์ (Profile)</h3>
        <p class="widget-username"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Jiraporn J.') ?></p>
        <a href="profile.php" class="btn-view-profile">ดูโปรไฟล์ของฉัน →</a>
    </div>

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

    <!-- สถิติการใช้งาน -->
    <div class="widget-card stats-card">
        <h4>📊 สถิติการใช้งาน</h4>
        <ul>
            <li><span>👥 จำนวนผู้ใช้งานวันนี้</span> <strong>12,430 คน</strong></li>
            <li><span>💬 โพสต์ใหม่วันนี้</span> <strong>856 โพสต์</strong></li>
            <li><span>💖 ผู้ถูกใจทั้งหมด</span> <strong>120.5k ครั้ง</strong></li>
        </ul>
    </div>
</aside>
