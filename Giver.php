<?php
require_once 'config/db.php';

// ดึงข้อมูลแมวทั้งหมดจากตาราง Giver
$sql = "SELECT * FROM Giver ORDER BY CreatedDate DESC";
$result = $conn->query($sql);

$givers = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $givers[] = $row;
    }
}
?>