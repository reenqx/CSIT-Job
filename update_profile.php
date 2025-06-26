<?php
session_start();
include 'database.php'; // เชื่อมต่อฐานข้อมูล

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบว่า POST มีค่าที่ต้องการครบถ้วนหรือไม่
    if (isset($_POST['about_text']) && isset($_POST['skills_text']) && isset($_POST['interest_text'])) {
        $about_text   = $_POST['about_text'];
        $skills_text  = $_POST['skills_text'];
        $interest_text = $_POST['interest_text'];

        // ตรวจสอบว่า session มี user_id หรือไม่
        if (!isset($_SESSION['user_id'])) {
            echo "User not logged in.";
            exit;
        }
        $user_id = $_SESSION['user_id']; // คาดว่าในตาราง students, คอลัมน์ที่ใช้เป็น primary key คือ students_id

        // สร้าง SQL statement เพื่ออัปเดตข้อมูลในตาราง students
        $sql = "UPDATE students SET other = ?, skills = ?, interest = ? WHERE students_id = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo "Prepare failed: " . $conn->error;
            exit;
        }
        
        // สมมุติว่า students_id เป็น string ถ้าเป็น integer ให้เปลี่ยนเป็น "i"
        $stmt->bind_param("ssss", $about_text, $skills_text, $interest_text, $user_id);

        if ($stmt->execute()) {
            echo "Data updated successfully.";
        } else {
            echo "Error updating data: " . $stmt->error;
        }

        $stmt->close();
        $conn->close();
    } else {
        echo "Missing data in POST request.";
    }
} else {
    echo "Invalid request method.";
}
?>