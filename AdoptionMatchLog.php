<?php
require_once 'config/db.php';

// ดึงข้อมูลแมวทั้งหมดจากตาราง AdoptionMatchLog
$sql = "SELECT * FROM AdoptionMatchLog ORDER BY CreatedDate DESC";
$result = $conn->query($sql);

$AdoptionMatchLogs = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $AdoptionMatchLogs[] = $row;
    }
}
?>