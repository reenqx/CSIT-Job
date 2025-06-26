<?php
session_start();
include 'database.php';

// รับค่า id และฟิลเตอร์
$post_job_id = isset($_GET['post_job_id']) ? intval($_GET['post_job_id']) : '';
$filter_major_name = isset($_GET['major_name']) ? urldecode($_GET['major_name']) : '';
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : '';

// ตรวจสอบค่าที่รับมา
if ($post_job_id <= 0) {
    echo "<p>ไม่พบข้อมูลงานที่ต้องการ</p>";
    exit; // หยุด PHP แต่ไม่ส่ง JSON
}

// ตรวจสอบค่าที่ได้รับจาก URL (Debugging)
error_log("post_job_id: " . $post_job_id);
error_log("major_name: " . $filter_major_name);
error_log("year: " . $filter_year);

$sql = "SELECT DISTINCT 
    ja.job_application_id AS job_application_id, 
    ja.post_job_id,pj.title,
    s.student_id AS student_id, 
    s.stu_name, 
    s.year, 
    s.profile, 
    m.major_name 
FROM job_application ja 
LEFT JOIN student s ON ja.student_id = s.student_id
LEFT JOIN major m ON s.major_id = m.major_id
LEFT JOIN post_job pj ON ja.post_job_id = pj.post_job_id
WHERE ja.post_job_id = ?";

$params = [$post_job_id];
$types = "i";

// เพิ่มตัวกรอง Major โดยใช้ `major_name`
if (!empty($filter_major_name)) {
    $sql .= " AND m.major_name = ?";
    $params[] = $filter_major_name;
    $types .= "s";
}

// เพิ่มตัวกรอง Year (ปีการศึกษา)
if ($filter_year > 0) {
    $sql .= " AND s.year = ?";
    $params[] = $filter_year;
    $types .= "i";
}

// ตรวจสอบ SQL Query ที่จะถูกเรียกใช้ (Debugging)
error_log("SQL Query: " . $sql);
error_log("Parameters: " . implode(", ", $params));

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die(json_encode(["error" => "SQL Error: " . $conn->error]));
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$sqlJobs = "SELECT * 
            FROM post_job
            WHERE post_job_id = ?";
$stmtJ = $conn->prepare($sqlJobs);
$stmtJ->bind_param("i", $post_job_id);
$stmtJ->execute();
$resJobs = $stmtJ->get_result();
$jobs = [];
while ($rowJob = $resJobs->fetch_assoc()) {
    $jobs[] = $rowJob;
}
$stmtJ->close();

$sqlJobs = "SELECT pj.title AS title, st.stu_name
            FROM accepted_student acs
            JOIN post_job pj ON pj.post_job_id = acs.post_job_id
            JOIN student st ON st.student_id = acs.student_id
            WHERE acs.post_job_id = ?";
$stmtJ = $conn->prepare($sqlJobs);
if (!$stmtJ) {
    die("Prepare failed: " . $conn->error);
}
$stmtJ->bind_param("i", $post_job_id);
if (!$stmtJ->execute()) {
    die("Execute failed: " . $stmtJ->error);
}
$resJobs = $stmtJ->get_result();
$studentlist = [];
while ($rowJob = $resJobs->fetch_assoc()) {
    $studentlist[] = $rowJob;
}
$stmtJ->close();


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Teaching Assistant View Applications Page">
    <title>Teaching Assistant Applications</title>
    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Montserrat:wght@600&display=swap"
        rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/header-footerstyle.css">
    <link rel="stylesheet" href="css/viewapply.css">

    <style>
    /* Main Content */
    .container {
        max-width: 750px;
        /* ขนาดคอนเทรนเน้อทั้งหมด */
        margin: 0px auto;
        /* ระยะห่างจากด้านบนการ์ดและข้างเป็น 20px */
        padding: 20px 20px;
        /* เพิ่มระยะห่างด้านบน */
        padding-top: 0px;
        /*เปลี่ยนเป็นน้อยลง */
    }

    /* การ์ดแสดงรายชื่อคนสมัครงาน */
    .application-list {
        display: flex;
        flex-direction: column;
        max-width: 750px;
        margin: 0px auto;
        padding: 10px;
    }

    /*ส่วนบน ย้อนกลับ view app manage job*/
    .back-head {
        justify-content: left;
        align-items: center;
        padding-top: 25px;
        width: 100%;
        border-radius: 8px;
        margin-bottom: 25px;
        padding-left: 40px;
        font-size: 25px;
    }


    .back-head:hover {
        transform: translateY(-2px);
    }

    .title-container {
        /*เส้นใต้*/
        justify-content: space-between;
        /* ทำให้ข้อความอยู่คนละฝั่ง */
        width: 50%;
        /* กำหนดความกว้างของ container */
        display: flex;
        align-items: center;
        border-bottom: 1px solid #333;
        padding-bottom: 0.1px;
        margin-bottom: 40px;
        /*ความยาวแถบบน view app , manage job*/
        width: 100%;
        /* จัดให้อยู่กึ่งกลาง */
        margin-left: auto;
        /* จัดให้อยู่กึ่งกลาง */
        margin-right: auto;
    }

    .title-container a {
        padding: 10px 20px;
        font-size: 16px;
        background-color: transparent;
        transition: background-color 0.3s ease;
        /*เปลี่ยนสี hover ช้าลง*/
        text-decoration: none;
        /* ลบขีดเส้นใต้จากลิงก์ */
        color: black;
        /* เปลี่ยนสีข้อความ */
        border-top-left-radius: 10px;
        /* มุมซ้ายบนมน */
        border-top-right-radius: 10px;
        /* มุมขวาบนมน */
        border-bottom-left-radius: 0;
        /* มุมซ้ายล่างเหลี่ยม */
        border-bottom-right-radius: 0;
        /* มุมขวาล่างเหลี่ยม */
        user-select: none;
        /* ไม่ให้ข้อความถูกเลือก */
    }

    /*แทบเลือกทั้งหมด วิทคอม ไอที*/
    .bar {
        display: flex;
        align-items: center;
        /* จัดให้องค์ประกอบอยู่ตรงกลางแนวตั้ง */
        padding: 0px 0px;
        /* กำหนดระยะห่างภายในแถบ */
        gap: 50px;
    }

    .bar a {
        text-decoration: none;
        /* เริ่มต้นให้ไม่มีเส้นใต้ */
        color: #000000;
        /* ตั้งค่าสีของข้อความเป็นสีดำ */
    }

    .bar a:focus,
    .bar a.active {
        /* เมื่อ hover, focus หรือมีคลาส active จะมีเส้นใต้ */
        text-decoration: underline;
    }

    .head-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
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
            // ตรวจสอบสถานะการล็อกอิน
            if (isset($_SESSION['user_id'])) {
                echo '<a href="logout.php">Logout</a>';
            } else {
                // หากยังไม่ได้ล็อกอิน แสดงปุ่มเข้าสู่ระบบ
                echo '<a href="login.php">Login</a>';
            }
            ?>
        </nav>
    </header>

    <!-- ปุ่ม back -->
    <nav class="back-head">
        <a href="teacher_profile.php"> <i class="bi bi-chevron-left"></i></a>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="title-container">
            <a href="viewapply.php?post_job_id=<?php echo $post_job_id; ?>">View Applications</a>
            <!-- ลิงก์ไปยัง viewapply.php -->
            <a href="jobmanage.php?post_job_id=<?php echo $post_job_id; ?>">Manage Job</a>
            <!-- ลิงก์ไปยัง jobmanage.php -->
        </div>
        <br>
        <div class="head-title">
            <?php
            if (!empty($jobs)) {
                echo '<h1>' . htmlspecialchars($jobs[0]['title']) . '</h1>';
            } else {
                echo '<h1>No job found</h1>';
            }
            ?>
            <?php if (!empty($studentlist)): ?>
            <a href="studentlist.php?post_job_id=<?php echo $post_job_id; ?>">
                <i class="bi bi-card-list"></i>
            </a>
            <?php endif; ?>


        </div>
        <br>
        <div class="bar">
            <a href="viewapply.php" class="<?= empty($_GET['major_name']) ? 'active' : '' ?>">ทั้งหมด</a>

            <?php
            $sql_major = "SELECT major_name, major_name_th FROM major";
            $res_major = $conn->query($sql_major);
            if ($res_major && $res_major->num_rows > 0) {
                while ($row = $res_major->fetch_assoc()) {
                    $major_en = $row['major_name'];         // ค่าที่ใช้ส่งใน URL
                    $major_th = $row['major_name_th'];      // ค่าที่แสดงในปุ่ม
                    $activeClass = (isset($_GET['major_name']) && $_GET['major_name'] == $major_en) ? 'active' : '';
                    echo '<a href="viewapply.php?major_name=' . urlencode($major_en) . '" class="' . $activeClass . '">' . htmlspecialchars($major_th) . '</a>';
                }
            }
            ?>

            <i class="bi bi-filter ms-auto" id="filter-btn" style="cursor: pointer;"></i>
        </div>

        <!-- ฟิลเตอร์ -->
        <div id="hidden-message" class="message-box">
            <p>สาขา</p>
            <?php
            $sql_major = "SELECT major_name, major_name_th FROM major"; // ดึง 2 ฟิลด์
            $res_major = $conn->query($sql_major);
            if ($res_major && $res_major->num_rows > 0) {
                while ($row = $res_major->fetch_assoc()) {
                    $major_en = $row['major_name'];       // ใช้ส่งใน URL
                    $major_th = $row['major_name_th'];    // ใช้แสดงผลปุ่ม
                    echo '<button class="branch-btn" data-major="' . htmlspecialchars($major_en) . '">' . htmlspecialchars($major_th) . '</button>';
                }
            }
            ?>
            <br><br>
            <p>ชั้นปี</p>
            <button class="year-btn" data-year="1">ปี 1</button>
            <button class="year-btn" data-year="2">ปี 2</button>
            <button class="year-btn" data-year="3">ปี 3</button>
            <button class="year-btn" data-year="4">ปี 4</button>

            <br><br>
            <button id="clear-btn">ล้าง</button>
            <button id="apply-btn">ตกลง</button>
        </div>
    </div>

    <div class="application-list">
        <!-- รายการใบสมัคร -->
        <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <div class="application-card">

            <img class="profile-img" src="<?= htmlspecialchars($row['profile']) ?>" alt="">
            <div class="details">
                <div class="name"><?= htmlspecialchars($row['stu_name']) ?></div>
                <div class="department">สาขา <?= htmlspecialchars($row['major_name']) ?></div>
                <div class="year">ปี <?= htmlspecialchars($row['year']) ?></div>
            </div>
            <a href="viewapply2.php?job_application_id=<?php echo $row['job_application_id']; ?>" class="chevron-link">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
        <?php endwhile; ?>
        <?php else: ?>
        <p>ไม่พบข้อมูลการสมัคร</p>
        <?php endif; ?>
    </div>

    </div>


    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const barLinks = document.querySelectorAll(".bar a");

        barLinks.forEach(link => {
            link.addEventListener("click", async function(event) {
                event.preventDefault();

                const params = new URLSearchParams(window.location.search);
                const urlParams = new URL(this.href).searchParams;
                const major_name = urlParams.get("major_name");
                const post_job_id = params.get("post_job_id");
                const jobId = params.get("id");

                // ✅ ปรับ URL
                if (post_job_id) {
                    params.set("post_job_id", post_job_id);
                }

                if (major_name) {
                    params.set("major_name", decodeURIComponent(major_name).replace(/\+/g,
                        " "));
                } else {
                    params.delete("major_name");
                }

                params.delete("year"); // ล้าง filter ปี

                if (jobId) {
                    params.set("id", jobId);
                }

                // ✅ อัปเดต URL
                history.pushState({}, "", "viewapply.php?" + params.toString());

                // ✅ ลบ active class เดิมทั้งหมด
                barLinks.forEach(l => l.classList.remove("active"));

                // ✅ ใส่ active ให้ลิงก์ที่ถูกคลิก
                this.classList.add("active");

                const applicationList = document.querySelector(".application-list");

                try {
                    const response = await fetch("viewapply.php?" + params.toString());
                    const html = await response.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, "text/html");
                    const newAppList = doc.querySelector(".application-list");

                    if (newAppList) {
                        applicationList.innerHTML = newAppList.innerHTML;
                    } else {
                        applicationList.innerHTML = "โหลดข้อมูลไม่สำเร็จ";
                    }
                } catch (error) {
                    console.error("เกิดข้อผิดพลาดในการโหลดข้อมูล:", error);
                    applicationList.innerHTML = "เกิดข้อผิดพลาดในการโหลดข้อมูล";
                }
            });
        });
    });
    </script>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const applicationList = document.querySelector(".application-list");
        const filterBtn = document.getElementById("filter-btn");
        const messageBox = document.getElementById("hidden-message");
        const branchButtons = document.querySelectorAll(".branch-btn");
        const yearButtons = document.querySelectorAll(".year-btn");
        const clearBtn = document.getElementById("clear-btn");
        const applyBtn = document.getElementById("apply-btn");
        const barLinks = document.querySelectorAll(".bar a");

        let selectedMajor = "";
        let selectedYear = "";
        let filterBoxVisible = false;

        // ✅ ปิดปุ่ม "ตกลง" เริ่มต้น
        applyBtn.disabled = true;

        // ✅ ฟังก์ชันเปิด/ปิดปุ่ม "ตกลง" ตามการเลือก
        function updateApplyButtonState() {
            const hasMajor = selectedMajor !== "";
            const hasYear = selectedYear !== "";
            applyBtn.disabled = !(hasMajor || hasYear);
        }

        // ✅ toggle กล่อง filter
        filterBtn.addEventListener("click", () => {
            filterBoxVisible = !filterBoxVisible;
            messageBox.style.display = filterBoxVisible ? "block" : "none";

            // ✅ ลบ active class ออกจากลิงก์ทั้งหมดใน .bar
            barLinks.forEach(link => link.classList.remove("active"));
        });

        // ✅ ซ่อนกล่องเมื่อกดลิงก์ filter ด้านบน
        barLinks.forEach(link => {
            link.addEventListener("click", () => {
                messageBox.style.display = "none";
                filterBoxVisible = false;
            });
        });

        // ✅ เลือกสาขา
        branchButtons.forEach(button => {
            button.addEventListener("click", () => {
                branchButtons.forEach(btn => btn.classList.remove("active"));
                button.classList.add("active");
                selectedMajor = button.dataset.major;
                updateApplyButtonState();
            });
        });

        // ✅ เลือกชั้นปี
        yearButtons.forEach(button => {
            button.addEventListener("click", () => {
                yearButtons.forEach(btn => btn.classList.remove("active"));
                button.classList.add("active");
                selectedYear = button.dataset.year;
                updateApplyButtonState();
            });
        });

        // ✅ ล้าง filter
        clearBtn.addEventListener("click", () => {
            branchButtons.forEach(btn => btn.classList.remove("active"));
            yearButtons.forEach(btn => btn.classList.remove("active"));
            selectedMajor = "";
            selectedYear = "";
            updateApplyButtonState();
        });

        // ✅ กด "ตกลง" แล้วโหลดข้อมูล
        applyBtn.addEventListener("click", async () => {
            const params = new URLSearchParams(window.location.search);
            const post_job_id = params.get("post_job_id");
            const jobId = params.get("id");

            if (post_job_id) {
                params.set("post_job_id", post_job_id);
            }

            if (selectedMajor) {
                params.set("major_name", selectedMajor);
            } else {
                params.delete("major_name");
            }

            if (selectedYear) {
                params.set("year", selectedYear);
            } else {
                params.delete("year");
            }

            if (jobId) {
                params.set("id", jobId);
            }

            console.log("📦 ส่ง filter:", params.toString());

            history.pushState({}, "", "viewapply.php?" + params.toString());

            try {
                const response = await fetch("viewapply.php?" + params.toString());
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, "text/html");
                const newAppList = doc.querySelector(".application-list");

                if (newAppList) {
                    applicationList.innerHTML = newAppList.innerHTML;
                } else {
                    applicationList.innerHTML = "โหลดข้อมูลไม่สำเร็จ";
                }
            } catch (error) {
                console.error("เกิดข้อผิดพลาด:", error);
                applicationList.innerHTML = "เกิดข้อผิดพลาดในการโหลดข้อมูล";
            }
        });
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-..."
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-..."
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>