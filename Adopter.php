
<?php
require_once 'config/db.php';

// ดึงข้อมูลแมวทั้งหมดจากตาราง Adopter
$sql = "SELECT * FROM Adopter ORDER BY CreatedDate DESC";
$result = $conn->query($sql);

$Adopters = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $Adopters[] = $row;
    }
}
?>