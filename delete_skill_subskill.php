<?php
// สมมติว่าไฟล์นี้วางไว้ในโฟลเดอร์เดียวกับ jobmanage.php
include 'database.php';
session_start();

// รับข้อมูลจาก POST
$post_job_id = $_POST['post_job_id'] ?? null;
$skill_id = $_POST['skill_id'] ?? null;

if (!$post_job_id || !$skill_id) {
    echo "Missing post_job_id or skill_id";
    exit;
}

// ลบข้อมูลจากตาราง post_job_skill
$stmt = $conn->prepare("DELETE FROM post_job_skill WHERE post_job_id = ? AND skill_id = ?");
$stmt->bind_param("ii", $post_job_id, $skill_id);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
