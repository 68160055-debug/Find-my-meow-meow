
<header class="top-header" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; background: #fff; border-bottom: 1px solid #ffccd5;">
    <!-- ฟอร์มค้นหาที่ใช้งานได้จริง -->
    <form method="get" action="index.php" style="flex-grow: 1; max-width: 420px; margin: 0;">
        <div style="position: relative;">
            <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="🔍 ค้นหาโพสต์, ชื่อผู้ใช้..." style="width: 100%; padding: 8px 15px 8px 38px; border-radius: 20px; border: 1px solid #ffccd5; background: #fff8f9; font-family: 'Kanit', sans-serif; outline: none; font-size: 14px;">
            <span style="position: absolute; left: 14px; top: 8px; color: #aaa; font-size: 14px;">🔍</span>
        </div>
    </form>

    <div class="user-top-actions" style="display: flex; align-items: center; gap: 15px;">
        <!-- กระดิ่งแจ้งเตือนลิงก์ไปหน้าโปรไฟล์/แจ้งเตือน -->
        <a href="profile.php" class="notification-badge-wrapper" title="การแจ้งเตือน" style="position: relative; text-decoration: none; color: inherit; font-size: 18px;">
            <span class="notif-icon">🔔</span>
            <span class="badge-count" style="position: absolute; top: -6px; right: -8px; background: #d81b60; color: white; border-radius: 50%; font-size: 11px; padding: 1px 5px; font-weight: bold;">3</span>
        </a>
        <div class="user-avatar-mini">
            <a href="profile.php">
                <img src="assets/images/<?= !empty($_SESSION['avatar']) ? htmlspecialchars($_SESSION['avatar']) : 'student.jpg' ?>" alt="User Avatar" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid #ffccd5; vertical-align: middle;">
            </a>
        </div>
    </div>
</header>