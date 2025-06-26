<?php
require 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    var_dump($_POST); exit(); // ตรวจสอบค่าที่ถูกส่งมา

    $job_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $close_detail_id = isset($_POST['close_detail_id']) ? intval($_POST['close_detail_id']) : 0;
    $detail = isset($_POST['detail']) ? trim($_POST['detail']) : '';

    if ($job_id > 0 && $close_detail_id > 0) {
        $stmt = $conn->prepare("INSERT INTO close_jobs (post_job_id, close_detail_id, detail, closed_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $job_id, $close_detail_id, $detail);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database insert failed']);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid job ID or close detail ID']);
    }
}

$conn->close();
?>
