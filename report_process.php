<?php
session_start();
include 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if form is submitted via POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id = $_SESSION['user_id']; // Student who reported
    $post_job_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 1;

    $report_category_id = isset($_POST['report_reason']) ? intval($_POST['report_reason']) : 1;

    if (!$post_job_id || !$report_category_id) {
        header("Location: joinustest.php?id=$post_job_id&error=missing_data");
        exit;
    }

    // Insert the report into the database
    $sql = "INSERT INTO report (post_job_id, user_id, report_category_id, report_status_id) VALUES (?, ?, ?, 1)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $post_job_id, $user_id, $report_category_id);

    if ($stmt->execute()) {
        // Get the last inserted report ID
        $reference_id = $conn->insert_id ;

        // Insert notification for the student (reporter)

        // Get the teacher ID who posted the job
        $sql_teacher = "SELECT teacher_id FROM post_job WHERE post_job_id = ?";
        $stmt_teacher = $conn->prepare($sql_teacher);
        $stmt_teacher->bind_param("i", $post_job_id);
        $stmt_teacher->execute();
        $result_teacher = $stmt_teacher->get_result();
        $teacher = $result_teacher->fetch_assoc();
        $teachers_id = $teacher['teacher_id'];
        // Insert notification for the teacher
        
        $role_id_teacher = 3; // Teacher role
        $event_type = 'report';
        $reference_table = 'report';
        $message_teacher = "Your job post has been reported.";
        $status = 'unread';

        $sql_notify_teacher = "INSERT INTO notification (user_id, role_id, event_type, reference_table, reference_id, message, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_notify_teacher = $conn->prepare($sql_notify_teacher);
        $stmt_notify_teacher->bind_param("sississ", $teachers_id, $role_id_teacher, $event_type, $reference_table, $reference_id, $message_teacher, $status);
        $stmt_notify_teacher->execute();

        // Redirect back to job page with success message
        header("Location: joinustest.php?id=$post_job_id&success=report_submitted");
        exit;
    } else {
        header("Location: joinustest.php?id=$post_job_id&error=db_error");
        exit;
    }

    // Close statements
    $stmt->close();
    $stmt_notify_student->close();
    $stmt_notify_teacher->close();
    $stmt_teacher->close();
    $conn->close();
}
?>
