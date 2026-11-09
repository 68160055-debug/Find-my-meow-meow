<?php
require_once 'config/db.php';

// ดึงข้อมูลแมวทั้งหมดจากตาราง giver
$sql = "SELECT * FROM giver ORDER BY createddate DESC";
$result = $conn->query($sql);

$givers = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $givers[] = $row;
    }
}
?>