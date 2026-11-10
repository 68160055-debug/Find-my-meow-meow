<?php
// ตัวอย่าง cat_detail.php
include('auth.php'); // เช็ก session เข้าสู่ระบบ
// รับค่า id แมวมาดึงข้อมูลจาก Database
$cat_id = $_GET['id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายละเอียดการซื้อแมว</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="main-container">
        <!-- แถบเมนูด้านซ้ายคงเดิม -->
        <?php include('config/leftbar.php'); ?>

        <!-- ส่วนเนื้อหาตรงกลาง: นำโครงฟอร์มตามรูปที่ 2 มาใส่ตรงนี้ -->
        <div class="content-area">
            <div class="card-detail-form">
                <h3>ตัวอย่างหน้าสร้างโพสต์ขายแมว</h3>
                <!-- กล่อง input หัวข้อโพสต์ -->
                <!-- รูปภาพแมว -->
                <!-- ข้อมูลราคา ส่วนลด และปุ่มเผยแพร่โพสต์ -->
            </div>
        </div>

        <!-- แถบด้านขวา (ถ้าต้องการให้มีเหมือนเดิม) -->
        <?php include('config/rightbar.php'); ?>
    </div>
</body>
</html>