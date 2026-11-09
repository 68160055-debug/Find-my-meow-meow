<?php
require_once 'config/db.php';

// ดึงข้อมูลทั้งหมดจากตาราง Cat เรียงลำดับจากใหม่ไปเก่าตาม CreatedDate
$sql = "SELECT * FROM Cat ORDER BY CreatedDate DESC";
$result = $conn->query($sql);

$cats = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cats[] = $row;
    }
}
?>