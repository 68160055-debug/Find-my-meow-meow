<?php
require_once 'config/db.php';

// ดึงข้อมูลทั้งหมดจากตาราง cat เรียงลำดับจากใหม่ไปเก่าตาม CreatedDate
$sql = "SELECT * FROM cat ORDER BY createddate DESC";
$result = $conn->query($sql);

$cats = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cats[] = $row;
    }
}
?>