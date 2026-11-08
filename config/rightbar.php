<!-- right_sidebar.php -->
<aside class="right-sidebar">
    <!-- โปรไฟล์ย่อ -->
    <div class="widget-card profile-summary-card">
        <img src="assets/images/<?= !empty($_SESSION['avatar']) ? htmlspecialchars($_SESSION['avatar']) : 'student.jpg' ?>" alt="Profile" class="widget-avatar">
        <h3>โปรไฟล์ (Profile)</h3>
        <p class="widget-username"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Jiraporn J.') ?></p>
        <a href="profile.php" class="btn-view-profile">ดูโปรไฟล์ของฉัน →</a>
    </div>

    <!-- ปฏิทิน มกราคม 2026 -->
    <div class="widget-card calendar-card">
        <div class="calendar-header">
            <h4>📅 มกราคม 2026</h4>
            <span class="cal-nav">›</span>
        </div>
        <table class="calendar-table">
            <thead>
                <tr>
                    <th>อา.</th><th>จ.</th><th>อ.</th><th>พ.</th><th>พฤ.</th><th>ศ.</th><th>ส.</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>1</td><td>2</td><td>3</td><td>4</td><td>5</td><td>6</td><td>7</td></tr>
                <tr><td>8</td><td>9</td><td>10</td><td><span class="cal-active">11</span></td><td>12</td><td>13</td><td>14</td></tr>
                <tr><td>15</td><td>16</td><td>17</td><td>18</td><td>19</td><td>20</td><td>21</td></tr>
                <tr><td>22</td><td>23</td><td>24</td><td>25</td><td>26</td><td>27</td><td>28</td></tr>
                <tr><td>29</td><td>30</td><td>31</td><td></td><td></td><td></td><td></td></tr>
            </tbody>
        </table>
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