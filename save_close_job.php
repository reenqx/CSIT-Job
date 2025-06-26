<?php
header('Content-Type: application/json');
include 'database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $jobId = $_POST['job_id'] ?? null;
    $reasonId = $_POST['close_detail_id'] ?? null;
    $detail = trim($_POST['detail'] ?? '');

    if (!$jobId || !$reasonId) {
        throw new Exception('Missing parameters');
    }

    $conn->begin_transaction();

    // 1. อัปเดทสถานะงานเป็นปิด
    $updateJobStatus = $conn->prepare("UPDATE post_job SET job_status_id = 2 WHERE post_job_id = ?");
    $updateJobStatus->bind_param("i", $jobId);
    if (!$updateJobStatus->execute()) {
        throw new Exception('Failed to update job status: ' . $updateJobStatus->error);
    }
    $updateJobStatus->close();

    // 2. บันทึกเหตุผลการปิดงาน
    $stmt = $conn->prepare("INSERT INTO close_job (post_job_id, close_detail_id, detail) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $jobId, $reasonId, $detail);

    if (!$stmt->execute()) {
        throw new Exception('Failed to save reason: ' . $stmt->error);
    }
    $stmt->close();

    $conn->commit();

    // 3. ส่งผลลัพธ์กลับเป็น JSON
    echo json_encode(['success' => true, 'message' => 'Job closed successfully']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
