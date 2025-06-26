<?php
session_start();
include 'database.php';

// รับข้อมูลจากฟอร์ม
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $password = $_POST['password'];

    // ตรวจสอบ Username และ Password ใน Table `user`
    $sql = "SELECT * FROM user WHERE user_id = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $id, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // หากพบผู้ใช้ในฐานข้อมูล
        $user = $result->fetch_assoc();

        // ตรวจสอบ role_status_id: ถ้าไม่เท่ากับ 1 ให้บันทึกข้อความ error แล้ว redirect กลับไปที่ login.php
        if ($user['role_status_id'] != 1) {
            $_SESSION['error'] = "Your account is not active.";
            header("Location: login.php");
            exit();
        }

        // เก็บข้อมูลพื้นฐานลงใน Session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_role'] = $user['role_id'];

        // ดึงชื่อจากตาราง teacher หรือ student ตาม role
        if ($user['role_id'] == 3) {
            // ถ้าเป็น teacher
            $sql_teacher = "SELECT teach_name FROM teacher WHERE teacher_id = ?";
            $stmt_teacher = $conn->prepare($sql_teacher);
            $stmt_teacher->bind_param("s", $id);
            $stmt_teacher->execute();
            $result_teacher = $stmt_teacher->get_result();
            if ($result_teacher->num_rows > 0) {
                $teacher = $result_teacher->fetch_assoc();
                $_SESSION['name'] = $teacher['teach_name'];
            }
            $stmt_teacher->close();
            header("Location: hometest.php");
        } elseif ($user['role_id'] == 4) {
            // ถ้าเป็น student
            $sql_student = "SELECT stu_name FROM student WHERE student_id = ?";
            $stmt_student = $conn->prepare($sql_student);
            $stmt_student->bind_param("s", $id);
            $stmt_student->execute();
            $result_student = $stmt_student->get_result();
            if ($result_student->num_rows > 0) {
                $student = $result_student->fetch_assoc();
                $_SESSION['name'] = $student['stu_name'];
            }
            $stmt_student->close();
            header("Location: hometest.php");
        } elseif ($user['role_id'] == 2) {
            header("Location: admin/manage_users.php");
        } elseif ($user['role_id'] == 1) {
            header("Location: excutive/maindash.html");
        } else {
            header("Location: login.php");
        }

        exit(); // หยุดการทำงานของสคริปต์หลังจาก Redirect
    } else {
        // หากไม่พบผู้ใช้
        $_SESSION['error'] = "Invalid Username or Password!";
        header("Location: login.php");
        exit();
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
    <style>
        /* สไตล์สำหรับ floating alert */
        .floating-alert {
            position: fixed;
            top: 20px;
            z-index: 1050;
            min-width: 250px;
        }

        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* สำหรับลิงค์สมัครสมาชิก */
        .signup-link {
            text-align: center;
            margin-top: 1rem;
            /* หรือใช้ 15px ตามต้องการ */
            font-size: 16px;
            color: #fff;
        }

        .signup-link a {
            color: #ff8a00;
            text-decoration: none;
            font-weight: bold;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>


    <div class="container">
        <!-- ถ้ามี error message ให้แสดง floating alert -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-warning alert-dismissible fade show floating-alert" role="alert">
                <?php
                echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8');
                unset($_SESSION['error']); // ลบ error หลังแสดงแล้ว
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <h1 class="title">เข้าสู่ระบบ</h1>
        <div class="login-box">
            <form action="login.php" method="POST">
                <input type="text" name="id" placeholder="ID" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
        </div>
        <!-- ลิงค์สมัครสมาชิก -->
        <p class="signup-link">
            ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิกที่นี่</a>
        </p>

    </div>

    <!-- Bootstrap JS (สำหรับการ dismiss alert) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>