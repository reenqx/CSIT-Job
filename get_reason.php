<?php
header('Content-Type: application/json');
include 'database.php';

// ✅ ใช้ table ที่ถูกต้อง: close_detail
$sql = "SELECT * FROM close_detail";
$result = mysqli_query($conn, $sql);

$reasons = [];
while ($row = mysqli_fetch_assoc($result)) {
    $reasons[] = $row;
}

echo json_encode($reasons);
?>