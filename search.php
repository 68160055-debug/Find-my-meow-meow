<?php
// ตำแหน่งไฟล์: login/config/search.php (Pure PHP ไม่มี JavaScript)
$search_query = isset($_GET['q']) ? htmlspecialchars(trim($_GET['q']), ENT_QUOTES, 'UTF-8') : '';
$raw_avatar = !empty($current_user['avatar']) ? $current_user['avatar'] : (!empty($_SESSION['avatar']) ? $_SESSION['avatar'] : 'student.jpg');
$avatar_path = __DIR__ . '/../assets/images/' . basename($raw_avatar);
$current_avatar = is_file($avatar_path) ? basename($raw_avatar) : 'student.jpg';

$default_name = $L['default_user'] ?? 'ผู้ใช้งาน';
$current_name = !empty($current_user['full_name']) ? $current_user['full_name'] : (!empty($_SESSION['full_name']) ? $_SESSION['full_name'] : $default_name);

$ph_text     = $L['search_ph'] ?? 'ค้นหาชื่อคน, ชื่อโพสต์, แอดมิน (เช่น "หาบ้าน" -ดุ หรือ ขาว OR ส้ม)...';
$btn_text    = $L['search_btn'] ?? 'ค้นหา';
$notif_title = $L['notif_title'] ?? 'การแจ้งเตือน';
?>
<header class="top-dash-header" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 25px; background: #ffffff; border-radius: 20px; border: 1px solid #ffccd5; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(255, 117, 140, 0.05);">
    <form action="search_results.php" method="GET" style="flex: 1; max-width: 550px; position: relative; margin: 0;">
        <span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); font-size: 16px; color: #d81b60;">🔍</span>
        <input type="text" name="q" value="<?= $search_query ?>" placeholder="<?= htmlspecialchars($ph_text, ENT_QUOTES, 'UTF-8') ?>" required style="width: 100%; padding: 11px 45px 11px 42px; border-radius: 25px; border: 1.5px solid #ffccd5; outline: none; font-family: 'Kanit', sans-serif; font-size: 14px; background: #fff8f9; color: #444; transition: all 0.2s;">
        <button type="submit" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: #d81b60; color: white; border: none; border-radius: 20px; padding: 5px 14px; font-size: 12px; cursor: pointer; font-family: 'Kanit', sans-serif;"><?= htmlspecialchars($btn_text, ENT_QUOTES, 'UTF-8') ?></button>
    </form>
    
    <div style="display: flex; align-items: center; gap: 18px;">
        <span style="font-size: 20px; cursor: pointer; color: #e57373;" title="<?= htmlspecialchars($notif_title, ENT_QUOTES, 'UTF-8') ?>">🔔</span>
        <a href="profile.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none;">
            <img src="assets/images/<?= htmlspecialchars($current_avatar, ENT_QUOTES, 'UTF-8') ?>" alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #ff758c;">
            <strong style="font-size: 14px; color: #333;"><?= htmlspecialchars($current_name, ENT_QUOTES, 'UTF-8') ?></strong>
        </a>
    </div>
</header>