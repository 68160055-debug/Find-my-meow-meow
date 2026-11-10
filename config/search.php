<!-- header.php -->
<header class="top-header">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" placeholder="ค้นหาเพจ, ผู้ใช้, หรือโพสต์..." />
    </div>
    <div class="user-top-actions">
        <div class="notification-badge-wrapper" title="การแจ้งเตือน">
            <span class="notif-icon">🔔</span>
            <span class="badge-count">3</span>
        </div>
        <div class="user-avatar-mini">
            <img src="assets/images/<?= !empty($_SESSION['avatar']) ? htmlspecialchars($_SESSION['avatar']) : 'student.jpg' ?>" alt="User Avatar">
        </div>
    </div>
</header>
