<?php
header('Content-Type: application/json');
include 'database.php'; // เชื่อมต่อฐานข้อมูล

// รับข้อมูลจาก AJAX
$data = json_decode(file_get_contents('php://input'), true);
$applicationId = $data['applicationId'] ?? null;
$action = $data['action'] ?? null; // "approve" หรือ "reject"
$salary = $data['salary'] ?? null; // เงินเดือนสำหรับผู้สมัครที่ได้รับการตอบรับ

if (!$applicationId || !$action) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

// ตรวจสอบว่าใบสมัครมีอยู่จริง
$sql = "SELECT job_application.job_application_id, job_application.student_id, 
        job_application.post_job_id, post_job.title, post_job.number_student, 
        post_job.time_and_wage, post_job.teacher_id AS teacher_id
        FROM job_application 
        JOIN post_job ON job_application.post_job_id = post_job.post_job_id 
        WHERE job_application.job_application_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $applicationId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => '❌ ไม่พบใบสมัครนี้ในระบบ กรุณาตรวจสอบอีกครั้ง!']);
    exit;
}

$job_application = $result->fetch_assoc();
$student_id     = $job_application['student_id'];
$post_id        = $job_application['post_job_id'];
$job_title      = $job_application['title']; // ดึงชื่องาน
$number_student = $job_application['number_student'];
$teacher_id     = $job_application['teacher_id']; // <--- เพิ่มตรงนี้!

// ตรวจสอบว่ามีการดำเนินการ (approve/reject) ไปแล้วหรือไม่
$sql_check = "SELECT accept_status_id FROM accepted_application WHERE job_application_id = ?";
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

// กำหนด timezone เป็นประเทศไทยและเวลาปัจจุบัน
date_default_timezone_set('Asia/Bangkok');
$created_at = date('Y-m-d H:i:s');

if ($action === "reject") {
    $accept_status_id = 2; // Rejected

    $sql = "INSERT INTO accepted_application (job_application_id, post_job_id, student_id, accept_status_id, created_at) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiis", $applicationId, $post_id, $student_id, $accept_status_id, $created_at);

    if ($stmt->execute()) {
        $reference_id = $conn->insert_id; // ID ที่บันทึกใน accepted_application

        // ส่งการแจ้งเตือนให้นิสิต (event_type เปลี่ยนเป็น 'job_application')
        $user_id = $student_id;
        $role_id = 4; // สำหรับผู้สมัคร
        $event_type = 'job_application';
        $reference_table = 'accepted_application';
        $message = "❌ คุณถูกปฏิเสธจากงาน '$job_title' ";
        $status = 'unread';

        $sql_notify = "INSERT INTO notification (user_id, role_id, event_type, reference_table, reference_id, message, status, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_notify = $conn->prepare($sql_notify);
        $stmt_notify->bind_param("sississs", $user_id, $role_id, $event_type, $reference_table, $reference_id, $message, $status, $created_at);

        if ($stmt_notify->execute()) {
            echo json_encode(['success' => true, 'message' => "❌ ปฏิเสธใบสมัครสำหรับงาน '$job_title' เรียบร้อยแล้ว!"]);
        } else {
            echo json_encode(['success' => true, 'message' => '⚠️ ปฏิเสธสำเร็จ แต่ไม่สามารถส่งการแจ้งเตือนได้!']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '❌ เกิดข้อผิดพลาดในการปฏิเสธใบสมัคร กรุณาลองใหม่อีกครั้ง!']);
    }
}

if ($action === "approve") {
    $accept_status_id = 1; // Approved

    // บันทึกข้อมูลการอนุมัติใบสมัคร
    $sql = "INSERT INTO accepted_application (job_application_id, post_job_id, student_id, accept_status_id, created_at) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiis", $applicationId, $post_id, $student_id, $accept_status_id, $created_at);

    if ($stmt->execute()) {
        $accepted_application_id = $conn->insert_id; // เก็บ ID สำหรับอ้างอิง
        $time_and_wage = $job_application['time_and_wage']; // ใช้ค่าจากฐานข้อมูล post_job

        // เพิ่มข้อมูลลงใน accepted_student
        $sql_insert_student = "INSERT INTO accepted_student (post_job_id, student_id, salary) VALUES (?, ?, ?)";
        $stmt_insert_student = $conn->prepare($sql_insert_student);
        $stmt_insert_student->bind_param("isi", $post_id, $student_id, $time_and_wage);

        if ($stmt_insert_student->execute()) {
            // ส่งแจ้งเตือนให้นิสิตที่ได้รับการ approve (event_type 'job_application')
            $user_id = $student_id;
            $role_id = 4; // สำหรับผู้สมัคร
            $event_type = 'job_application';
            $reference_table = 'accepted_application';
            $message = "✅ คุณได้รับการอนุมัติจากงาน '$job_title' !";
            $status = 'unread';

            $sql_notify_student = "INSERT INTO notification (user_id, role_id, event_type, reference_table, reference_id, message, status, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_notify_student = $conn->prepare($sql_notify_student);
            $stmt_notify_student->bind_param("sississs", $user_id, $role_id, $event_type, $reference_table, $accepted_application_id, $message, $status, $created_at);
            $stmt_notify_student->execute();

            // ตรวจสอบจำนวนใบสมัครที่ได้รับการอนุมัติใน accepted_student
            $sql_check_full = "SELECT 
                (SELECT COUNT(*) FROM accepted_student WHERE post_job_id = ?) AS accepted_count
                FROM post_job 
                WHERE post_job_id = ?";
            $stmt_check = $conn->prepare($sql_check_full);
            $stmt_check->bind_param("ii", $post_id, $post_id);
            $stmt_check->execute();
            $check_result = $stmt_check->get_result();
            $check_data = $check_result->fetch_assoc();

            if ($check_data['accepted_count'] >= $number_student) {
                // อัปเดตสถานะงานเป็นเต็ม (job_status_id = 4)
                $sql_update_job = "UPDATE post_job SET job_status_id = 4 WHERE post_job_id = ?";
                $stmt_update = $conn->prepare($sql_update_job);
                $stmt_update->bind_param("i", $post_id);
                $stmt_update->execute();

                // ดำเนินการปฏิเสธใบสมัครที่เหลือ (auto-reject)
                // ก่อนทำการ auto-reject ให้เลือกข้อมูลใบสมัครที่ยังไม่ได้ดำเนินการ
                $sql_pending = "SELECT job_application_id, student_id 
                                FROM job_application 
                                WHERE post_job_id = ? 
                                AND job_application_id NOT IN (SELECT job_application_id FROM accepted_application)";
                $stmt_pending = $conn->prepare($sql_pending);
                $stmt_pending->bind_param("i", $post_id);
                $stmt_pending->execute();
                $result_pending = $stmt_pending->get_result();
                $pending_applications = [];

                while ($row = $result_pending->fetch_assoc()) {
                    $pending_applications[] = $row;
                }

                // ทำการ auto-reject ใบสมัครที่ยังเหลืออยู่ทีละใบ พร้อมเก็บ ID เพื่อนำไปส่ง noti
                foreach ($pending_applications as $pending) {
                    $reject_application_id = $pending['job_application_id'];
                    $auto_student_id = $pending['student_id'];
                    $accept_status_id = 2;

                    // บันทึกเข้า accepted_application ทีละรายการ เพื่อเก็บ ID สำหรับอ้างอิง
                    $sql_reject = "INSERT INTO accepted_application 
                                    (job_application_id, post_job_id, student_id, accept_status_id, created_at)
                                    VALUES (?, ?, ?, ?, ?)";
                    $stmt_reject = $conn->prepare($sql_reject);
                    $stmt_reject->bind_param("iiiis", $reject_application_id, $post_id, $auto_student_id, $accept_status_id, $created_at);

                    if ($stmt_reject->execute()) {
                        $reference_id = $conn->insert_id;

                        // ส่งการแจ้งเตือนให้นิสิตที่ถูก auto-reject
                        $notification_message = "❌ คุณถูกปฏิเสธจากงาน '$job_title'";
                        $event_type_auto = 'job_application_rejected';
                        $role_id_auto = 4;
                        $reference_table_auto = 'accepted_application';
                        $status_auto = 'unread';

                        $sql_notify_auto = "INSERT INTO notification 
                                            (user_id, role_id, event_type, reference_table, reference_id, message, status, created_at) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt_notify_auto = $conn->prepare($sql_notify_auto);
                        $stmt_notify_auto->bind_param("sississs", 
                            $auto_student_id, 
                            $role_id_auto, 
                            $event_type_auto, 
                            $reference_table_auto, 
                            $reference_id, 
                            $notification_message, 
                            $status_auto, 
                            $created_at
                        );
                        $stmt_notify_auto->execute();   
                    }
                }

                // แจ้งเตือนสำหรับผู้สอนเมื่อปิดรับสมัคร (ยังคง event_type 'post_expire')
                $user_id_notify = $teacher_id; // สามารถปรับเป็นผู้เกี่ยวข้องกับโพสต์งานได้ตามต้องการ
                $role_id_notify = 3; // สำหรับผู้สอน
                $event_type_notify = 'post_expire';
                $reference_table_notify = 'post_job';
                $message_notify = "ตำแหน่งงาน '{$job_title}' รับสมัครครบจำนวนแล้ว";
                $status_notify = 'unread';

                $sql_notify = "INSERT INTO notification (user_id, role_id, event_type, reference_table, reference_id, message, status, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_notify = $conn->prepare($sql_notify);
                $stmt_notify->bind_param("sississs", $user_id_notify, $role_id_notify, $event_type_notify, $reference_table_notify, $post_id, $message_notify, $status_notify, $created_at);
                $stmt_notify->execute();

                echo json_encode(['success' => true, 'message' => "✅ ใบสมัครของคุณสำหรับงาน '$job_title' อนุมัติสำเร็จแล้ว และงานนี้ได้ทำการปิดรับสมัคร!"]);
            } else {
                echo json_encode(['success' => true, 'message' => "✅ ใบสมัครของคุณสำหรับงาน '$job_title' อนุมัติสำเร็จแล้ว!"]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => '❌ เกิดข้อผิดพลาดในการบันทึกข้อมูลผู้สมัคร กรุณาลองใหม่อีกครั้ง!']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '❌ เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง!']);
    }
}

$stmt->close();
$conn->close();
?>