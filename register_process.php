<?php
session_start();
include 'database.php';

// รับค่าจากฟอร์ม
$id = $_POST['id'];
$username = $_POST['username'];
$email = $_POST['email'];
$password = $_POST['password'];
$role_id = $_POST['role_id']?? null;
$gender_id = $_POST['gender_id']?? null;
$major_id = $_POST['major_id'] ?? null;
$year = $_POST['year'] ?? null;
$phone = $_POST['phone'] ?? null;
$status = 1;
$profile = "profile/img.jpg";

// INSERT ข้อมูลผู้ใช้ลงตาราง user
$sql_user = "INSERT INTO user (user_id, password, role_id, role_status_id)
             VALUES (?, ?, ?, ?)";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("ssii", $id, $password, $role_id, $status);

if ($stmt_user->execute()) {
    if ($role_id == 4) {
        // นักศึกษา
        $sql_student = "INSERT INTO student (student_id, stu_name, stu_email, major_id, year, gender_id, profile)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_student = $conn->prepare($sql_student);
        $stmt_student->bind_param("sssiiis", $id, $username, $email, $major_id, $year, $gender_id, $profile);
        $stmt_student->execute();
    } elseif ($role_id == 3) {
        // อาจารย์
        $sql_teacher = "INSERT INTO teacher (teacher_id, teach_name, teach_email, major_id, teach_phone_number, gender_id, profile)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_teacher = $conn->prepare($sql_teacher);
        $stmt_teacher->bind_param("sssisis", $id, $username, $email, $major_id, $phone, $gender_id, $profile);
        $stmt_teacher->execute();
    } elseif ($role_id == 1) {
        // executive
        $sql_executive = "INSERT INTO executive (executive_id, exec_name, exec_email)
                           VALUES (?, ?, ?)";
        $stmt_executive = $conn->prepare($sql_executive);
        $stmt_executive->bind_param("sss", $id, $username, $email);
        $stmt_executive->execute();
    } elseif ($role_id == 2) {
        // admin
        $sql_admin = "INSERT INTO admin (	admin_id, ad_name, ad_email)
                        VALUES (?, ?, ?)";
        $stmt_admin = $conn->prepare($sql_admin);
        $stmt_admin->bind_param("sss", $id, $username, $email);
        $stmt_admin->execute();
    }

    echo "<script>alert('สมัครสมาชิกสำเร็จ!'); window.location='login.php';</script>";
} else {
    echo "<script>alert('เกิดข้อผิดพลาดในการสมัครสมาชิก'); window.history.back();</script>";
}
