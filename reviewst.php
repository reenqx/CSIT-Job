<?php
session_start();
include 'database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// รับ teacher_id จาก URL (GET) สำหรับใช้งานใน AJAX และในส่วนบันทึกรีวิว
$teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : null;

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่ ถ้าไม่มีรับจาก GET (โดยแปลงเป็น string)
if (!isset($_SESSION['user_id'])) {
    $user_id = isset($_GET['id']) ? $_GET['id'] : null;
} else {
    $user_id = $_SESSION['user_id'];
}

/* ----------------------------------------------------------------
   1. ACTION สำหรับ AJAX:
      - get_jobs: ดึงข้อมูลงาน (จากตาราง post_job) ที่มีสถานะปิด (job_status_id = 2)
      - get_students: ดึงข้อมูลนิสิตที่สมัครงาน (จาก accepted_application กับ student)
      - get_categories: ดึงหมวดรีวิว (จาก review_category)
------------------------------------------------------------------*/
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'get_jobs') {
        $sql = "
            SELECT DISTINCT pj.post_job_id, pj.title
            FROM post_job pj
            WHERE pj.teacher_id = ? AND pj.job_status_id = 2
            AND EXISTS (
                SELECT 1
                FROM accepted_application aa
                WHERE aa.post_job_id = pj.post_job_id
                AND aa.accept_status_id = 1
                AND NOT EXISTS (
                    SELECT 1 FROM review r
                    WHERE r.post_job_id = pj.post_job_id
                    AND r.student_id = aa.student_id
                    AND r.teacher_id = ?
                )
            )
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $teacher_id, $teacher_id);
        $stmt->execute();
        $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode($jobs);
        exit();
    }


    if ($_GET['action'] === 'get_students') {
        $post_job_id = intval($_GET['post_job_id'] ?? 0);
        $sql = "
            SELECT DISTINCT s.student_id, s.stu_name AS student_name
            FROM accepted_application aa
            JOIN student s ON aa.student_id = s.student_id
            WHERE aa.post_job_id = ? AND aa.accept_status_id = 1
            AND NOT EXISTS (
                SELECT 1 FROM review r
                WHERE r.post_job_id = aa.post_job_id
                AND r.student_id = aa.student_id
                AND r.teacher_id = ?
            )
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $post_job_id, $teacher_id);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit();
    }


    if ($_GET['action'] === 'get_categories') {
        // ดึงหมวดรีวิวจาก review_category
        $sql = "SELECT review_category_id, review_category_name FROM review_category";
        $result = $conn->query($sql);
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        exit();
    }
}

/* ----------------------------------------------------------------
   2. การบันทึกข้อมูลรีวิว (POST)
      - รับข้อมูล: students_id, post_job_id, คะแนนในแต่ละหมวด และคอมเมนต์สำหรับหมวดที่ไม่มีคะแนน (เช่น review_category_id = 1)
      - ใช้ teacher_id ที่ได้จาก GET ในการบันทึกลงในตาราง review
------------------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับข้อมูลจากฟอร์ม
    $students_id = $_POST['students_id'] ?? '';
    // เปลี่ยนชื่อฟิลด์ให้ตรงกับฐานข้อมูล: ใช้ "post_job_id" (ไม่ใช่ post_jobs_id)
    $post_job_id = intval($_POST['post_job_id'] ?? 0);
    // สำหรับหมวดที่ไม่มีคะแนน (สมมุติว่า review_category_id = 1 คือหมวดความคิดเห็น)
    $comment = trim($_POST['comment_cat1'] ?? '');

    if (empty($students_id) || $post_job_id == 0 || empty($teacher_id)) {
        echo json_encode(["success" => false, "error" => "ข้อมูลไม่ถูกต้อง"]);
        exit();
    }
    // ตรวจสอบว่าเคยรีวิวนิสิตคนนี้ในงานนี้แล้วหรือยัง
    $check_sql = "SELECT COUNT(*) as review_count FROM review WHERE post_job_id = ? AND student_id = ? AND teacher_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iss", $post_job_id, $students_id, $teacher_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result()->fetch_assoc();

    if ($check_result['review_count'] > 0) {
        echo json_encode(["success" => false, "error" => "คุณได้รีวิวนิสิตคนนี้ในงานนี้ไปแล้ว"]);
        exit();
    }

    // ดึงหมวดรีวิวทั้งหมดจาก review_category
    $categories_sql = "SELECT review_category_id FROM review_category";
    $categories_result = $conn->query($categories_sql);
    $categories = $categories_result->fetch_all(MYSQLI_ASSOC);

    // เตรียม query INSERT ลงในตาราง review
    $sql = "INSERT INTO review (post_job_id, student_id, teacher_id, review_category_id, rating, comment) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    // วนลูปแต่ละหมวดรีวิว
    foreach ($categories as $category) {
        $review_category_id = $category['review_category_id'];
        // ถ้าเป็นหมวดที่ไม่มีคะแนน (เช่น id 1) ให้ตั้ง rating เป็น 0 และใช้คอมเมนต์ที่รับมา
        if ($review_category_id == 1) {
            $rating = 0;
            $review_comment = $comment;
        } else {
            $rating = intval($_POST["rating$review_category_id"] ?? 0);
            $review_comment = ''; // ไม่มีคอมเมนต์สำหรับหมวดที่ให้คะแนน
            // ตรวจสอบคะแนนในแต่ละหมวด (ควรอยู่ระหว่าง 1-5)
            if ($rating < 1 || $rating > 5) {
                echo json_encode(["success" => false, "error" => "กรุณาให้คะแนนทุกหมวดรีวิว"]);
                exit();
            }
        }
        // Bind และ execute query
        $stmt->bind_param("issiis", $post_job_id, $students_id, $teacher_id, $review_category_id, $rating, $review_comment);
        if (!$stmt->execute()) {
            echo json_encode(["success" => false, "error" => "SQL Error: " . $stmt->error]);
            exit();
        }
    }

    echo json_encode(["success" => true]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ให้คะแนนนิสิต</title>
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">

    <link rel="stylesheet" href="css/header-footerstyle.css">
    <style>
        #submitBtn {
            background-color: #4E2A84;
            /* สีม่วง */
            border-color: #4E2A84;
            /* สีขอบของปุ่ม */
        }

        #submitBtn:hover {
            background-color: #6A3E9F;
            /* เปลี่ยนสีเมื่อเอาเมาส์ไปวาง */
            border-color: #6A3E9F;
            /* สีขอบเมื่อวางเมาส์ */
        }

        h4.mt-3 {
            text-align: center;
            /* จัดข้อความให้ตรงกลาง */
        }

        /* การ์ด (container) สำหรับข้อมูลรีวิว */
        .container {
            max-width: 600px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
        }

        /* ส่วนที่ไม่อยู่ในการ์ด */
        .form-container {
            max-width: 600px;
            margin: 20px auto;
        }

        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-start;
        }

        .rating input {
            display: none;
        }

        .rating label {
            font-size: 24px;
            color: lightgray;
            cursor: pointer;
        }

        .rating input:checked~label,
        .rating label:hover,
        .rating label:hover~label {
            color: gold;
        }

        .review-head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 4px var(--shadow);
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="headerTop">
        <div class="headerTopImg">
            <img src="logo.png" alt="Naresuan University Logo">
            <a href="#">Naresuan University</a>
        </div>
        <nav class="header-nav">
            <?php
            if (isset($_SESSION['user_id'])) {
                echo '<a href="logout.php">Logout</a>';
            } else {
                echo '<a href="login.php">Login</a>';
            }
            ?>
        </nav>
    </header>
    <!-- End Header -->
    <nav class="review-head">
        <a href="javascript:history.back()"><i class="bi bi-chevron-left"></i></a>
   
    </nav>
    <!-- Form สำหรับให้คะแนนนิสิต -->
    <div class="form-container">
        <h2 class="text-center">ให้คะแนนนิสิต</h2>
        <form id="reviewForm">
            <label>เลือกงาน :</label>
            <!-- เปลี่ยน name เป็น post_job_id ให้ตรงกับฐานข้อมูล -->
            <select id="post_jobs_id" name="post_job_id" class="form-control" required>
                <option value="">-- กรุณาเลือกงาน --</option>
            </select>

            <label>นิสิต :</label>
            <!-- ใช้ตาราง student (student_id, stu_name) -->
            <select id="students_id" name="students_id" class="form-control" required disabled>
                <option value="">-- กรุณาเลือกงานก่อน --</option>
            </select>

            <h4 class="mt-3">คะแนน</h4>
            <div id="ratingContainer"></div>

            <button type="submit" id="submitBtn" class="btn btn-primary w-100 mt-3" disabled>ส่งรีวิว</button>
        </form>
        <p id="statusMessage" class="text-center mt-2"></p>
    </div>

    

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const jobSelect = document.getElementById("post_jobs_id");
            const studentSelect = document.getElementById("students_id");
            const ratingContainer = document.getElementById("ratingContainer");
            const submitButton = document.getElementById("submitBtn");

            const urlParams = new URLSearchParams(window.location.search);
            // รับ teacher_id จาก URL
            const teacher_id = urlParams.get('teacher_id');

            // Fetch jobs data (จากตาราง post_job ที่มี teacher_id ตรงกับที่ส่งมา และ job_status_id = 2)
            fetch(`?action=get_jobs&teacher_id=${teacher_id}`)
                .then(res => res.json())
                .then(data => {
                    jobSelect.innerHTML = '<option value="">-- กรุณาเลือกงาน --</option>';
                    data.forEach(job => {
                        jobSelect.innerHTML += `<option value="${job.post_job_id}">${job.title}</option>`;
                    });
                });

            // เมื่อเลือกงาน ให้ดึงรายชื่อนิสิตที่สมัครงานจาก accepted_application
            jobSelect.addEventListener("change", function() {
                studentSelect.innerHTML = '<option value="">-- กรุณาเลือกนิสิต --</option>';
                studentSelect.disabled = true;
                if (!jobSelect.value) return;
                fetch(`?action=get_students&teacher_id=${teacher_id}&post_job_id=${jobSelect.value}`)
                    .then(res => res.json())
                    .then(data => {
                        data.forEach(student => {
                            studentSelect.innerHTML += `<option value="${student.student_id}">${student.student_name}</option>`;
                        });
                        studentSelect.disabled = false;
                    });
            });

            // Fetch categories สำหรับรีวิว
            fetch("?action=get_categories")
    .then(res => res.json())
    .then(categories => {
        // เคลียร์ container ก่อน
        ratingContainer.innerHTML = "";

        // แยกหมวดที่มีคะแนน (review_category_id != 1) กับหมวดคอมเมนต์ (review_category_id == 1)
        const ratingCategories = categories.filter(cat => cat.review_category_id != 1);
        const commentCategory = categories.find(cat => cat.review_category_id == 1);

        // เริ่มต้นด้วยการแสดงหมวดคะแนน (หมวด 2-6 เป็นต้น)
        ratingCategories.forEach(cat => {
            const div = document.createElement("div");
            div.innerHTML = `
                <label>${cat.review_category_name}</label>
                <div class="rating">
                    ${[5, 4, 3, 2, 1].map(val => `
                        <input type="radio" name="rating${cat.review_category_id}" value="${val}" id="star${cat.review_category_id}_${val}">
                        <label for="star${cat.review_category_id}_${val}">&#9733;</label>
                    `).join('')}
                </div>
            `;
            ratingContainer.appendChild(div);
        });

        // จากนั้นให้แสดงหมวดคอมเมนต์ (review_category_id == 1)
        if (commentCategory) {
            const div = document.createElement("div");
            div.innerHTML = `
                <label>${commentCategory.review_category_name}</label>
                <textarea name="comment_cat1" class="form-control"></textarea>
            `;
            ratingContainer.appendChild(div);
        }
        // เพิ่ม event listener สำหรับ validateForm (หากมี)
        ratingContainer.addEventListener("change", validateForm);
    });


            function validateForm() {
                let allRated = true;
                document.querySelectorAll(".rating input[type='radio']").forEach(input => {
                    if (!document.querySelector(`input[name="${input.name}"]:checked`)) {
                        allRated = false;
                    }
                });
                submitButton.disabled = !(studentSelect.value && allRated);
            }

            // ส่งฟอร์มรีวิวเมื่อ submit
            document.getElementById("reviewForm").addEventListener("submit", function(event) {
                event.preventDefault(); // ป้องกันการโหลดหน้าใหม่อัตโนมัติ

                const formData = new FormData(this);

                fetch("", {
                        method: "POST",
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        document.getElementById("statusMessage").innerText = data.success ? "รีวิวถูกบันทึกแล้ว" : "❌ " + data.error;

                        if (data.success) {
                            // รีเฟรชหน้าทั้งหมดหลังจากบันทึกรีวิวสำเร็จ
                            setTimeout(() => {
                                location.reload(); // รีโหลดหน้าปัจจุบัน
                            }, 1000); // รอ 1 วินาทีเพื่อให้ข้อความสำเร็จแสดงขึ้นก่อน
                        }
                    });
            });

        });
    </script>

</body>

</html>
<?php
$conn->close();
?>