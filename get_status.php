<?php
require 'database.php'; // ไฟล์เชื่อมต่อฐานข้อมูล

$job_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$sql = "SELECT job_status_id FROM post_jobs WHERE post_jobs_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$stmt->bind_result($status);
$stmt->fetch();
$stmt->close();

echo json_encode(['status' => $status]);
?>
