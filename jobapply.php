<?php
include 'database.php';
session_start();
$user_id = $_SESSION['user_id']??null;
$name = $_SESSION['name']??null;

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$GPA = $phone = "";
$error = "";
$success = "";
// รับค่า post_jobs_id จาก URL
$post_job_id = isset($_GET['id']) ? $_GET['id'] : 1;

// ตรวจสอบว่า post_jobs_id มีค่าหรือไม่
if (!$post_job_id) {
    die("❌ Error: ไม่มี post_jobs_id ถูกส่งมา!");
}

// ตรวจสอบว่า post_jobs_id มีอยู่ในฐานข้อมูล และดึง teacher_id
$sql_check = "SELECT teacher_id FROM post_job WHERE post_job_id = ?";
$stmt_check = $conn->prepare($sql_check);

if ($stmt_check === false) {
    die("❌ Error: การเตรียม SQL ล้มเหลว! " . $conn->error);
}

$stmt_check->bind_param("i", $post_job_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($row = $result_check->fetch_assoc()) {
    $teacher_id = $row['teacher_id']; // ดึงค่า teacher_id
} else {
    die("❌ Error: post_jobs_id ไม่มีอยู่จริงในฐานข้อมูล!");
}

$stmt_check->close();

// ดึงข้อมูลนักศึกษา
$sql = "SELECT post_job.title, student.stu_name AS name, student.year, student.stu_email AS email, major.major_name 
        FROM student
        JOIN major ON student.major_id = major.major_id 
        JOIN post_job ON post_job.post_job_id = ?
        WHERE student.student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $post_job_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

$row = $result->num_rows > 0 ? $result->fetch_assoc() : null;



// ตรวจสอบการส่งฟอร์ม
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $GPA = isset($_POST['GPA']) ? floatval($_POST['GPA']) : null;
    $phone = isset($_POST['Phone_number']) ? trim($_POST['Phone_number']) : null;

    // ตรวจสอบการอัปโหลดไฟล์
    if (!empty($_FILES["resume"]["name"])) {
        $upload_dir = "resumes/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // สร้างโฟลเดอร์ถ้ายังไม่มี
        }

        $resume_name = basename($_FILES["resume"]["name"]);
        $resume_path = $upload_dir . time() . "_" . $resume_name;

        if (move_uploaded_file($_FILES["resume"]["tmp_name"], $resume_path)) {
            // บันทึกข้อมูลลงตาราง job_applications
            $sql_insert = "INSERT INTO job_application (student_id, post_job_id, GPA, stu_phone_number, resume) VALUES (?, ?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("iidss", $student_id, $post_job_id, $GPA, $phone, $resume_path);

            if ($stmt_insert->execute()) {
                // เพิ่มข้อมูลลงในตาราง notifications
                $user_id = $teacher_id;
                $role_id = 4; // ตัวอย่าง role_id (ต้องเป็นตัวเลขที่ถูกต้องตามระบบของคุณ)
                $event_type = 'job_application'; // กำหนด event type
                $reference_table = 'job_application'; // ตารางอ้างอิง
                $reference_id = $conn->insert_id;
                $message = "คุณได้รับใบสมัครงานจากนิสิตในงานรับสมัครคนสนใจทำต่อด้วยชื่องาน " . htmlspecialchars($row['title']);
                $status = 'unread'; // สถานะการแจ้งเตือน (ยังไม่ได้อ่าน)

                $sql_notify = "INSERT INTO notification (user_id, role_id, event_type, reference_table, reference_id, message, status) 
                VALUES (?, ?, ?, ?,?, ?, ?)";
                $stmt_notify = $conn->prepare($sql_notify);
                $stmt_notify->bind_param("sississ", $teacher_id, $role_id, $event_type, $reference_table, $reference_id, $message, $status);



                if ($stmt_notify->execute()) {
                    // แสดงข้อความแจ้งเตือน
                    echo '<div class="alert alert-success">สมัครงานเสร็จสิ้นแล้ว!</div>';
                    echo '<script>
                            setTimeout(function() {
                            window.history.go(-2); // ย้อนกลับไปหน้าก่อนหน้าของก่อนหน้า
                         }, 1000); // รอ 1 วินาที 
                        </script>';
                } else {
                    echo "❌ ไม่สามารถบันทึกการแจ้งเตือนได้!";
                }

                $stmt_notify->close();
            } else {
                $error = "❌ เกิดข้อผิดพลาด: " . $conn->error;
            }
            $stmt_insert->close();
        } else {
            $error = "❌ ไม่สามารถอัปโหลดไฟล์ได้";
        }
    } else {
        $error = "❌ กรุณาอัปโหลด Resume";
    }
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Application Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/jobapplystyle.css">
    <link rel="stylesheet" href="css/header-footerstyle.css">
</head>

<body>

<header class="headerTop">
        <div class="headerTopImg">
            <img src="logo.png" alt="Naresuan University Logo">
            <a href="#">Naresuan University</a>
        </div>
        <nav class="header-nav">
            <?php
            if (isset($_SESSION['user_id'])) {
                if ($_SESSION['user_role'] == 3) {
                    echo '<a href="teacher_profile.php">Profile\'s ' . htmlspecialchars($name) . '</a>';


                } elseif ($_SESSION['user_role'] == 4) {
                    echo '<a href="stuf.php">Profile\'s ' . htmlspecialchars($name) . '</a>';


                }
            } else {
                echo '<a href="login.php">Login</a>';
            }
            ?>
        </nav>
    </header>

    <nav class="back-head">
        <a href="javascript:history.back()"> <i class="bi bi-chevron-left"></i></a>
    </nav>

    <main class="container">
        <div class="form-card">
            <h1 class="form-title">Job Application</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Name/ชื่อและนามสกุล</label>
                    <input type="text" class="form-input" name="name" value="<?php echo htmlspecialchars($row['name']); ?>" readonly>
                </div>

                <div class="form-group">
                    <label class="form-label">Resume/เรซูเม่</label>
                    <div class="file-upload">
                        <input type="file" name="resume" accept="image/*, application/pdf" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Major/สาขา</label>
                    <input type="text" class="form-input" value="<?php echo htmlspecialchars($row['major_name']); ?>" readonly>
                </div>

                <div class="form-group">
                    <label class="form-label">Year/ชั้นปี</label>
                    <input type="text" class="form-input" value="<?php echo htmlspecialchars($row['year']); ?>" readonly>
                </div>

                <div class="form-group">
                    <label class="form-label">GPAX/เกรดเฉลี่ย</label>
                    <input type="number" step="0.01" min="0" max="4" class="form-input" name="GPA" required>
                </div>

                <div class="form-group">
                    <label class="form-label">E-mail/อีเมล</label>
                    <input type="email" class="form-input" value="<?php echo htmlspecialchars($row['email']); ?>" readonly>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone/เบอร์ติดต่อ</label>
                    <input type="tel" class="form-input" name="Phone_number" required>
                </div>

                <input type="hidden" name="post_jobs_id" value="<?php echo htmlspecialchars($post_jobs_id); ?>">

                <div class="submit-group">
                    <button type="submit" class="submit-btn">Apply</button>
                </div>
            </form>
        </div>
    </main>

    <footer class="footer">
        <p>© CSIT - Computer Science and Information Technology</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>