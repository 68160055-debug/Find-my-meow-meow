<?php
require 'auth.php';
require 'config/db.php';
require_once 'config/lang.php'; // เพิ่มบรรทัดนี้
require_login();
?>
<main class="dash-main">
    <!-- แถบค้นหา Google Search Header ด้านบนสุด -->
    <?php include 'config/search.php'; ?>
    
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>หน้าหลัก - MyCommunity</title>
    
</head>
<body>

<div class="community-layout">
    <!-- เรียกใช้งาน Sidebar ด้านซ้าย -->
    <?php include 'sidebar.php'; ?>

    <!-- พื้นที่คอนเทนต์หลักตรงกลาง -->
    <main class="main-content">
        <?php include 'header.php'; ?>

        <!-- ประกาศจาก Admin และกฎการใช้งาน -->
        <section class="announcement-banner-wrapper">
            <div class="admin-notice">
                <div class="notice-icon">📢</div>
                <div class="notice-text">
                    <span class="badge-admin">ประกาศจาก Admin</span>
                    <h3>ยินดีต้อนรับสู่ Find-my-meow-meow เว็บเพจประกาศหาแมวและหาบ้านให้แมว 🎉</h3>
                    <p>ขอให้ทุกคนใช้งานอย่างมีความสุข และอยู่ร่วมกันอย่างมีน้ำใจนะคะ 💙</p>
                </div>
            </div>
            <div class="rule-notice">
                <div class="notice-icon blue">📋</div>
                <div class="notice-text">
                    <strong>กฎการใช้เว็บไซต์</strong>
                    <p>กรุณาอ่านก่อนใช้งาน เพื่อประสบการณ์ที่ดีร่วมกัน</p>
                </div>
                <a href="#" class="btn-read-more">อ่านเพิ่มเติม →</a>
            </div>
        </section>

        <!-- กล่องสร้างโพสต์ใหม่ -->
        <div class="post-create-card">
            <div class="post-input-row">
                <img src="assets/images/<?= !empty($_SESSION['avatar']) ? htmlspecialchars($_SESSION['avatar']) : 'student.jpg' ?>" alt="Avatar" class="post-avatar">
                <input type="text" placeholder="โพสต์ข้อความ เช่น กำลังประกาศตามหาแมว..." class="post-input-box" />
                <button type="button" class="btn-send-post">📤</button>
            </div>
            <div class="post-actions-row">
                <button type="button" class="action-chip">🖼️ รูปภาพ</button>
                <button type="button" class="action-chip">📁 เพิ่มไฟล์/วิดีโอ</button>
                <button type="button" class="action-chip">📄 ไฟล์เอกสาร (pdf, doc)</button>
                <span class="post-hint">โพสต์ข้อความ (post)</span>
            </div>
        </div>

        <!-- ฟีดโพสต์ที่ 1: ตามหาแมว -->
        <div class="feed-card">
            <div class="feed-header">
                <img src="assets/images/student.jpg" alt="Author" class="feed-avatar">
                <div class="feed-author-info">
                    <h4>น้องเดียร์</h4>
                    <span>2 ชม. ที่แล้ว · 🌐</span>
                </div>
                <span class="feed-options">•••</span>
            </div>
            <p class="feed-text">ใครพบเจอน้องแมวที่หายไป โปรดติดต่อกลับนะคะ 🙏💙</p>
            <div class="feed-images grid-2">
                <img src="assets/images/cat.png" alt="Cat photo 1">
                <img src="assets/images/cat.png" alt="Cat photo 2">
            </div>
            <div class="feed-stats-bar">
                <span>👍 1.5k ถูกใจ</span>
                <span>💬 243 ความคิดเห็น</span>
                <span>↗️ แชร์</span>
                <span class="bookmark">🔖 บันทึก</span>
            </div>
        </div>

        <!-- ฟีดโพสต์ที่ 2: ทะเล -->
        <div class="feed-card">
            <div class="feed-header">
                <img src="assets/images/student.jpg" alt="Author" class="feed-avatar">
                <div class="feed-author-info">
                    <h4>เที่ยวไปกับเรา</h4>
                    <span>5 ชม. ที่แล้ว · 🌐</span>
                </div>
                <span class="feed-options">•••</span>
            </div>
            <p class="feed-text">ทะเลสวยมากเลยค่ะ 🌊💙</p>
            <div class="feed-images grid-1">
                <img src="assets/images/student.jpg" alt="Beach photo">
            </div>
        </div>
    </main>

    <!-- เรียกใช้งาน Sidebar ด้านขวา -->
    <?php include 'right_sidebar.php'; ?>
</div>

<!-- แบนเนอร์แจ้งเตือนด้านล่างสุด -->
<div class="bottom-notification-bar">
    <div class="notif-content">
        <span class="bell-anim">🔔</span>
        <div>
            <strong>แจ้งเตือนล่าสุด</strong>
            <p>มีการตอบกลับในโพสต์ของคุณ • 2 นาทีที่แล้ว</p>
        </div>
    </div>
    <div class="admin-broadcast-preview">
        📢 <strong>ประกาศจาก Admin: ข่าวสารสำคัญ</strong> อัปเดตระบบและฟีเจอร์ใหม่ พร้อมใช้งานแล้ววันนี้!
    </div>
    <div class="notif-actions">
        <a href="#" class="btn-detail">ดูรายละเอียด →</a>
        <button type="button" class="btn-close-notif" onclick="this.parentElement.parentElement.style.display='none'">✕</button>
    </div>
</div>

</body>
</html>