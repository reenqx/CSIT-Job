<?php
header('Content-Type: application/json');
include 'database.php'; // เชื่อมต่อฐานข้อมูล

// รับข้อมูลจาก AJAX
$data = json_decode(file_get_contents('php://input'), true);
$applicationId = $data['applicationId'] ?? null;
$action = $data['action'] ?? null; // "approve" หรือ "reject"

if (!$applicationId || !$action) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

// ตรวจสอบว่าใบสมัครมีอยู่จริง
$sql = "SELECT id, student_id, post_jobs_id FROM job_applications WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $applicationId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => '❌ ไม่พบใบสมัครนี้ในระบบ กรุณาตรวจสอบอีกครั้ง!']);
    exit;
}

$job_application = $result->fetch_assoc();
$student_id = $job_application['student_id'];
$post_id = $job_application['post_jobs_id'];

// ตรวจสอบว่ามีการดำเนินการ (approve/reject) ไปแล้วหรือไม่
$sql_check = "SELECT accept_status_id FROM accepted_applications WHERE job_application_id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $applicationId);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    $existing_status = $result_check->fetch_assoc();
    $existing_status_id = $existing_status['accept_status_id'];

    if ($existing_status_id == 1) {
        echo json_encode(['success' => false, 'message' => '⚠️ ใบสมัครนี้ได้รับการอนุมัติไปแล้ว!']);
    } elseif ($existing_status_id == 2) {
        echo json_encode(['success' => false, 'message' => '⚠️ ใบสมัครนี้ได้รับการปฏิเสธไปแล้ว!']);
    }
    exit;
}

// ดำเนินการตาม action
$accepted_at = date('Y-m-d H:i:s'); // เวลาปัจจุบัน

if ($action === "reject") {
    $accept_status_id = 2; // Rejected

    $sql = "INSERT INTO accepted_applications (job_application_id, post_jobs_id, student_id, accept_status_id, accepted_at) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiis", $applicationId, $post_id, $student_id, $accept_status_id, $accepted_at);

    if ($stmt->execute()) {
        $reference_id = $conn->insert_id; // ดึง ID ที่บันทึกล่าสุด

        // เพิ่มข้อมูลการแจ้งเตือน
        $user_id = $student_id;
        $role_id = 4; // สมมติว่า role_id = 4 คือผู้สมัคร
        $event_type = 'job_rejected';
        $reference_table = 'accepted_applications';
        $message = "❌ ใบสมัครของคุณได้รับการปฏิเสธแล้ว!";
        $status = 'unread';

        $sql_notify = "INSERT INTO notifications (user_id, role_id, event_type, reference_table, reference_id, message, status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_notify = $conn->prepare($sql_notify);
        $stmt_notify->bind_param("iississ", $user_id, $role_id, $event_type, $reference_table, $reference_id, $message, $status);

        if ($stmt_notify->execute()) {
            echo json_encode(['success' => true, 'message' => '❌ ปฏิเสธใบสมัครเรียบร้อยแล้ว!']);
        } else {
            echo json_encode(['success' => true, 'message' => '⚠️ ปฏิเสธสำเร็จ แต่ไม่สามารถส่งการแจ้งเตือนได้!']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '❌ เกิดข้อผิดพลาดในการปฏิเสธใบสมัคร กรุณาลองใหม่อีกครั้ง!']);
    }

} elseif ($action === "approve") {
    $accept_status_id = 1; // Approved

    // ตรวจสอบสถานะ "Approved" จากตาราง accept_status
    $sql = "SELECT id FROM accept_status WHERE accept_name_status = 'Accepted' LIMIT 1";
    $result = $conn->query($sql);
    $accept_status = $result->fetch_assoc();

    if (!$accept_status) {
        echo json_encode(['success' => false, 'message' => '❌ ไม่สามารถดำเนินการได้ เนื่องจากไม่มีสถานะ "Approved" ในระบบ!']);
        exit;
    }

    $accept_status_id = $accept_status['id'];

    $sql = "INSERT INTO accepted_applications (job_application_id, post_jobs_id, student_id, accept_status_id, accepted_at) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiis", $applicationId, $post_id, $student_id, $accept_status_id, $accepted_at);

    if ($stmt->execute()) {
        $reference_id = $conn->insert_id; // ดึง ID ที่บันทึกล่าสุด

        // เพิ่มข้อมูลการแจ้งเตือน
        $user_id = $student_id;
        $role_id = 4; // role_id = 4 นิสิต
        $event_type = 'job_accepted';
        $reference_table = 'accepted_applications';
        $message = "🎉 ใบสมัครของคุณได้รับการอนุมัติแล้ว!";
        $status = 'unread';

        $sql_notify = "INSERT INTO notifications (user_id, role_id, event_type, reference_table, reference_id, message, status) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_notify = $conn->prepare($sql_notify);
        $stmt_notify->bind_param("iississ", $user_id, $role_id, $event_type, $reference_table, $reference_id, $message, $status);

        if ($stmt_notify->execute()) {
            echo json_encode(['success' => true, 'message' => '✅ อนุมัติใบสมัครเรียบร้อยแล้ว!']);
        } else {
            echo json_encode(['success' => true, 'message' => '⚠️ อนุมัติสำเร็จ แต่ไม่สามารถส่งการแจ้งเตือนได้!']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '❌ เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง!']);
    }
}

$stmt->close();
$conn->close();
?>
