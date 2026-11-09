
<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="logo-area">
        <h2>🐾 Find-my-meow-meow</h2>
    </div>
    <nav class="side-nav">
        <a href="index.php" class="nav-item <?= $current_page === 'index.php' ? 'active' : '' ?>">
            <span class="icon">🏠</span> หน้าแรก <span class="sub-text">(Home)</span>
        </a>
        <a href="profile.php" class="nav-item <?= $current_page === 'profile.php' ? 'active' : '' ?>">
            <span class="icon">👤</span> ประวัติผู้ใช้งาน <span class="sub-text">(Profile)</span>
        </a>
        <a href="contact.php" class="nav-item <?= $current_page === 'contact.php' ? 'active' : '' ?>">
            <span class="icon">📞</span> ติดต่อผู้ประกาศ <span class="sub-text">(Contact Us)</span>
        </a>
        <a href="buy_cat.php" class="nav-item <?= $current_page === 'buy_cat.php' ? 'active' : '' ?>">
            <span class="icon">🛒</span> ซื้อแมว <span class="sub-text">(Market)</span>
        </a>
        <a href="adopter.php" class="nav-item <?= $current_page === 'adopter.php' ? 'active' : '' ?>">
            <span class="icon">💬</span> ติดต่อผู้ขอรับเลี้ยง <span class="sub-text">(Adopter Chat)</span>
        </a>
        <a href="settings.php" class="nav-item <?= $current_page === 'settings.php' ? 'active' : '' ?>">
            <span class="icon">⚙️</span> การตั้งค่า <span class="sub-text">(Settings)</span>
        </a>
    </nav>

    <!-- กล่องรีวิวแมว 💖 -->
    <div class="cat-review-card">
        <div class="cat-mascot">🐱</div>
        <div class="cat-review-info">
            <p class="cat-review-title">คะแนนความพึงพอใจ</p>
            <p class="cat-review-score">⭐ <strong>4.8</strong> <span class="muted">(1.2k รีวิว)</span></p>
        </div>
    </div>

    <!-- ปุ่มออกจากระบบ -->
    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout-side">
            <span>🚪</span> ออกจากระบบ (Logout)
        </a>
    </div>
</aside>