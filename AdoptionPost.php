<?php
require_once 'config/db.php';

// ดึงข้อมูลแมวทั้งหมดจากตาราง AdoptionPost
$sql = "SELECT * FROM AdoptionPost ORDER BY CreatedDate DESC";
$result = $conn->query($sql);

$AdoptionPosts = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $AdoptionPosts[] = $row;
    }
}
?>