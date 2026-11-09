
<?php
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_day_of_week = date('w', strtotime("$year-$month-01"));
$month_names_th = [1=>'มกราคม', 2=>'กุมภาพันธ์', 3=>'มีนาคม', 4=>'เมษายน', 5=>'พฤษภาคม', 6=>'มิถุนายน', 7=>'กรกฎาคม', 8=>'สิงหาคม', 9=>'กันยายน', 10=>'ตุลาคม', 11=>'พฤศจิกายน', 12=>'ธันวาคม'];
?>
<aside class="right-sidebar">
    <!-- โปรไฟล์ย่อ -->
    <div class="widget-card profile-summary-card">
        <img src="assets/images/<?= !empty($_SESSION['avatar']) ? htmlspecialchars($_SESSION['avatar']) : 'student.jpg' ?>" alt="Profile" class="widget-avatar">
        <h3>โปรไฟล์ (Profile)</h3>
        <p class="widget-username"><?= htmlspecialchars($_SESSION['full_name'] ?? 'ผู้ใช้งาน') ?></p>
        <a href="profile.php" class="btn-view-profile">ดูโปรไฟล์ของฉัน →</a>
    </div>

    <!-- ปฏิทินแบบไดนามิก -->
    <div class="widget-card calendar-card">
        <div class="calendar-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <h4>📅 <?= $month_names_th[$month] ?> <?= $year + 543 ?></h4>
        </div>
        <table class="calendar-table">
            <thead>
                <tr>
                    <th>อา.</th><th>จ.</th><th>อ.</th><th>พ.</th><th>พฤ.</th><th>ศ.</th><th>ส.</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                <?php
                $current_day = 1;
                $today_d = (int)date('j');
                $today_m = (int)date('n');
                $today_y = (int)date('Y');
                
                for ($i = 0; $i < 42; $i++) {
                    if ($i >= $first_day_of_week && $current_day <= $days_in_month) {
                        $is_today = ($current_day == $today_d && $month == $today_m && $year == $today_y);
                        echo $is_today ? "<td><span class='cal-active'>{$current_day}</span></td>" : "<td>{$current_day}</td>";
                        $current_day++;
                    } else {
                        echo "<td></td>";
                    }
                    if ($i % 7 == 6) {
                        echo "</tr><tr>";
                        if ($current_day > $days_in_month) break;
                    }
                }
                ?>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- สถิติการใช้งาน -->
    <div class="widget-card stats-card">
        <h4>📊 สถิติการใช้งาน</h4>
        <ul>
            <li><span>👥 จำนวนผู้ใช้งาน</span> <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'Online') ?></strong></li>
            <li><span>🕒 สถานะ</span> <strong>ออนไลน์</strong></li>
        </ul>
    </div>
</aside>