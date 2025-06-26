<?php
require 'database.php'; // เชื่อมต่อฐานข้อมูล

$sql = "SELECT report_category_id, report_category_name FROM report_category ORDER BY report_category_id";
$result = $conn->query($sql);

$close_details = [];

while ($row = $result->fetch_assoc()) {
    $close_details[] = $row;
}

// ส่ง JSON กลับไปให้ JavaScript
header('Content-Type: application/json');
echo json_encode($close_details);
?>
