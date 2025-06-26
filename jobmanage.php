<?php
// Start session and database connection
include 'database.php';
session_start();

// กำหนด teacher_id โดยใช้ session หากมี ถ้าไม่มีให้ใช้ default ที่มีอยู่ในฐานข้อมูล (ตรวจสอบใน teacher table ด้วยว่า teacher_id นี้มีจริง)
$teacher_id = $_SESSION['user_id'] ?? null;
$job_id = intval($_GET['post_job_id'] ?? 33);

$post_job_id = intval($_GET['post_job_id'] ?? 0);
$post_job_id = intval($_GET['post_job_id'] ?? 33);
$skillSummary = [];
$stmt = $conn->prepare("
    SELECT 
        pjs.skill_id,
        s.skill_name,
        pjs.subskill_id,
        ss.subskill_name
    FROM 
        post_job_skill pjs
    JOIN 
        skill s ON pjs.skill_id = s.skill_id
    JOIN 
        subskill ss ON pjs.subskill_id = ss.subskill_id
    WHERE 
        pjs.post_job_id = ?
");
$stmt->bind_param("i", $post_job_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $skillId = $row['skill_id'];
    if (!isset($skillSummary[$skillId])) {
        $skillSummary[$skillId] = [
            'skill_name' => $row['skill_name'],
            'subskills' => []
        ];
    }
    $skillSummary[$skillId]['subskills'][] = $row['subskill_name'];
}
$stmt->close();


// Validate job_id
if (!$job_id) {
    exit("Invalid Job ID!");
}
if (!$teacher_id) {
    exit("Invalid User ID!");
}

// ตรวจสอบว่า teacher_id มีอยู่ในตาราง teacher หรือไม่
$check_teacher = $conn->prepare("SELECT teacher_id FROM teacher WHERE teacher_id = ?");
$check_teacher->bind_param("s", $teacher_id);
$check_teacher->execute();
if ($check_teacher->get_result()->num_rows === 0) {
    exit("Error: Teacher ID not found in database!");
}
$check_teacher->close();

// Fetch job details
$job_sql = "SELECT p.*, r.reward_type_name, c.job_category_name AS job_category, t.teach_name AS teacher,
                   ps.skill_id, ps.subskill_id
            FROM post_job p
            LEFT JOIN job_category c ON p.job_category_id = c.job_category_id
            LEFT JOIN reward_type r ON p.reward_type_id = r.reward_type_id
            LEFT JOIN teacher t ON p.teacher_id = t.teacher_id
            LEFT JOIN post_job_skill ps ON p.post_job_id = ps.post_job_id
            WHERE p.post_job_id = ?";

$stmt = $conn->prepare($job_sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    exit("Job not found!");
}

// Fetch dropdown data function
function fetchDropdown($conn, $sql)
{
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

$skills = fetchDropdown($conn, "SELECT skill_id, skill_name FROM skill ORDER BY skill_id");
$categories = fetchDropdown($conn, "SELECT job_category_id, job_category_name FROM job_category ORDER BY job_category_id");
$reward = fetchDropdown($conn, "SELECT reward_type_id, reward_type_name FROM reward_type ORDER BY reward_type_id");
$close_detail = fetchDropdown($conn, "SELECT close_detail_id, close_detail_name FROM close_detail ORDER BY close_detail_id");

// Preload selected subskills
$subskills_selected = [];
$subskill_query = $conn->prepare("SELECT subskill_id FROM post_job_skill WHERE post_job_id = ?");
$subskill_query->bind_param("i", $job_id);
$subskill_query->execute();
$result = $subskill_query->get_result();
while ($row = $result->fetch_assoc()) {
    $subskills_selected[] = $row['subskill_id'];
}
$subskill_query->close();

// Preload subcategories (ถ้ามี job_category_id)
$subcategories = [];
if (!empty($job['job_category_id'])) {
    $subcat_query = $conn->prepare("SELECT job_subcategory_id, job_subcategory_name FROM job_subcategory WHERE job_category_id = ?");
    $subcat_query->bind_param("i", $job['job_category_id']);
    $subcat_query->execute();
    $subcategories = $subcat_query->get_result()->fetch_all(MYSQLI_ASSOC);
    $subcat_query->close();
}


// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['done'])) {
    date_default_timezone_set('Asia/Bangkok');

    // Collect form data
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $start = $_POST['job_start'] ?? date('Y-m-d H:i:s');
    $end = $_POST['job_end'] ?? date('Y-m-d H:i:s');
    $category_id = intval($_POST['job_category_id'] ?? 0);
    $reward_type_id = intval($_POST['reward_type_id'] ?? 1);
    // หากไม่มีเลือก job_subcategory ให้กำหนดเป็น null (หรือ 0 ถ้า foreign key รองรับ)
    $sub_id = !empty($_POST['job_subcategory_id']) ? intval($_POST['job_subcategory_id']) : null;
    $timeandwage = $_POST['time_and_wage'] ?? null;
    $number_student = intval($_POST['number_student'] ?? 1);
    $images = $_POST['image'] ?? ($job['image'] ?? null);
    $job_status_id = 1; // ตั้งสถานะงานเป็นเปิด (open)

    // ตรวจสอบความถูกต้องของ job_status_id
    $check_status = $conn->prepare("SELECT job_status_id FROM job_status WHERE job_status_id = ?");
    $check_status->bind_param("i", $job_status_id);
    $check_status->execute();
    if ($check_status->get_result()->num_rows === 0) {
        exit("Error: job_status_id is invalid!");
    }
    $check_status->close();

    // Update job (ปรับปรุงข้อมูลงาน)
    $update_sql = "UPDATE post_job SET 
                title = ?, reward_type_id = ?, description = ?, job_start = ?, job_end = ?,
                number_student = ?, time_and_wage = ?, job_category_id = ?, job_subcategory_id = ?,
                teacher_id = ?, created_at = NOW(), job_status_id = ?, image = ? 
                WHERE post_job_id = ?";

    $stmt = $conn->prepare($update_sql);
    // เปลี่ยน bind_param type string ให้ถูกต้องตามลำดับข้อมูล
    $stmt->bind_param(
        "sisssiiiisisi",
        $title,           // s
        $reward_type_id,  // i
        $description,     // s
        $start,           // s
        $end,             // s
        $number_student,  // i
        $timeandwage,     // i (หรือ d หากเป็น double)
        $category_id,     // i
        $sub_id,          // i
        $teacher_id,      // s  <-- teacher_id เป็น string
        $job_status_id,   // i
        $images,          // s
        $job_id           // i
    );

    if ($stmt->execute()) {
        echo "<script>alert('Job updated successfully'); window.location='teacher_profile.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }


    if (!empty($_POST['skills'])) {
        // ลบ skill เดิมก่อน insert ใหม่
        $delete_skill = $conn->prepare("DELETE FROM post_job_skill WHERE post_job_id = ?");
        $delete_skill->bind_param("i", $job_id);
        $delete_skill->execute();
        $delete_skill->close();

        // เพิ่ม skill/subskill ใหม่
        foreach ($_POST['skills'] as $skill_id) {
            $skill_id = intval($skill_id);
            if (!empty($_POST['subskills'][$skill_id])) {
                foreach ($_POST['subskills'][$skill_id] as $subskillId) {
                    $insert_skill = $conn->prepare("INSERT INTO post_job_skill (post_job_id, skill_id, subskill_id) VALUES (?, ?, ?)");
                    $insert_skill->bind_param("iii", $job_id, $skill_id, $subskillId);
                    $insert_skill->execute();
                    $insert_skill->close();
                }
            }
        }
    }
}

// Process job delete
if (isset($_POST['delete_job'])) {
    $delete_status = 3;
    $stmt = $conn->prepare("UPDATE post_job SET job_status_id = ? WHERE post_job_id = ?");
    $stmt->bind_param("ii", $delete_status, $job_id);
    if ($stmt->execute()) {
        echo "<script>alert('Job marked as deleted'); window.location='teacher_profile.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

$salary = $_POST['calculated_salary'] ?? 0;

// สมมุติ update accepted_student table
$sql = "UPDATE accepted_student SET salary = ? WHERE student_id = ? AND post_job_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("dii", $salary, $studentId, $jobId);
$stmt->execute();


$conn->close();
?>




<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Posting Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://gitcdn.github.io/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/header-footerstyle.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script src="js/jobmanage.js"></script>
    <link rel="stylesheet" href="css/jobmanage.css">
    <style>
        #statusBtn {
            font-size: 18px;
            font-weight: bold;
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            outline: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* สถานะเปิด (สีเขียวพาสเทล) */
        .open {
            background-color: rgb(98, 214, 98);
            /* เขียวพาสเทล */
            color: rgb(255, 255, 255);
            box-shadow: 0px 4px 10px rgba(160, 231, 160, 0.8);
        }

        /* สถานะปิด (สีส้มพาสเทล) */
        .close {
            background-color: rgb(233, 117, 93);
            /* ส้มพาสเทล */
            color: rgb(255, 255, 255);
            box-shadow: 0px 4px 10px rgba(255, 181, 167, 0.8);
        }

        /* เอฟเฟกต์ตอน hover */
        #statusBtn:hover {
            transform: scale(1.1);
        }

        /* เอฟเฟกต์ตอนกด */
        #statusBtn:active {
            transform: scale(0.9);
        }

        .skills-container {
            width: 100%;
            /* ขนาดเต็มพื้นที่ */
            max-width: 800px;
            /* กำหนดความกว้างสูงสุด */
            max-height: 400px;
            /* กำหนดความสูงสูงสุดของกล่อง */
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            /* มุมโค้ง */
            padding: 15px 28px;
            background: #fff;
            overflow-y: auto;
            /* ให้มีแถบเลื่อนแนวตั้ง */
            overflow-x: hidden;
            /* ป้องกันการเลื่อนแนวนอน */
            margin-bottom: 20px;
        }

        /* ปรับให้ Checkbox ไม่ชิดกันเกินไป */
        .form-check {
            padding: 5px;
        }

        .skills-container::-webkit-scrollbar {
            width: 8px;
        }

        .skills-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .skills-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        #reward-label {
            display: flex;
            justify-content: space-between;
            /* จัดข้อความให้ขยายไปทั้งสองข้าง */
            align-items: center;
            /* จัดให้ข้อความและหน่วยอยู่แนวเดียวกัน */
        }

        #reward-label-text {
            flex-grow: 1;
            /* ทำให้ข้อความหลักขยายเต็มที่ */
        }

        #reward-unit {
            margin-left: 5px;
            /* เพิ่มระยะห่างระหว่างข้อความหลักกับหน่วย */
            font-weight: normal;
            /* กำหนดให้หน่วยไม่หนา */
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


    <!--เครื่องหมายย้อนกลับ-->
    <nav class="back-head">
        <a href="javascript:history.back()"> <i class="bi bi-chevron-left"></i></a>
    </nav>

    <div class="title-container">
        <a href="viewapply.php?post_job_id=<?php echo $post_job_id; ?>" class="nav-link ">View Applications</a>
        <a href="#" class="nav-link bg-gray" onclick="toggleManageJob(this)">Manage Job</a>
    </div>


    <!-- Main Content -->
    <main class="container">

        <!--ส่วนfromต่างๆ-->
        <div class="form-card">
            <div class="d-flex justify-content-between text-center">
                <h4 class="head-title">Manage Job</h4>
                <!-- ปุ่มสถานะ -->
                <button id="statusBtn"
                    onclick="toggleStatus()"
                    class="btn <?php echo ($job['job_status_id'] == 1) ? 'open' : 'close'; ?>"
                    data-status="<?php echo $job['job_status_id']; ?>"
                    data-job-id="<?php echo $job['post_job_id']; ?>">
                    <?php echo ($job['job_status_id'] == 1) ? 'เปิด (Open)' : 'ปิด (Close)'; ?>
                </button>

                <!-- Overlay -->
                <div id="modalOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 998;"></div>

                <!-- Modal -->
                <div id="closeReasonModal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 25px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4); width: 400px; text-align: center; z-index: 999;">
                    <h3 style="margin-bottom: 15px; font-size: 20px; color: #333;">เลือกเหตุผลในการปิดงาน</h3>

                    <select id="close_detail_id" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 16px;">
                        <option value="">-- กรุณาเลือกเหตุผล --</option>
                    </select>

                    <div id="additionalDetail" style="display: none; margin-top: 15px;">
                        <input type="text" id="detail" placeholder="กรอกเหตุผลเพิ่มเติม" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 16px;">
                    </div>

                    <div style="margin-top: 20px; display: flex; justify-content: space-between;">
                        <button id="closeModalBtn" style="background-color: rgb(241, 71, 88); color: white; padding: 10px 20px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer;">ยกเลิก</button>
                        <button id="confirmCloseBtn" style="background-color: rgb(72, 208, 103); color: white; padding: 10px 20px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer;"
                            data-job-id="<?php echo $job['post_job_id']; ?>">ยืนยัน</button>
                    </div>
                </div>

                <script>
                    // ✅ โหลดเหตุผลจาก get_reason.php
                    function loadCloseReasons() {
                        fetch('get_reason.php')
                            .then(response => response.json())
                            .then(data => {
                                const select = document.getElementById('close_detail_id');
                                select.innerHTML = '<option value="">-- กรุณาเลือกเหตุผล --</option>';
                                data.forEach(reason => {
                                    const option = document.createElement('option');
                                    option.value = reason.close_detail_id;
                                    option.textContent = reason.close_detail_name;
                                    select.appendChild(option);
                                });
                            })
                            .catch(error => console.error('Error loading reasons:', error));
                    }

                    // ✅ toggle modal
                    function openCloseReasonModal() {
                        document.getElementById('modalOverlay').style.display = 'block';
                        document.getElementById('closeReasonModal').style.display = 'block';
                        loadCloseReasons();
                    }

                    function closeModal() {
                        document.getElementById('modalOverlay').style.display = 'none';
                        document.getElementById('closeReasonModal').style.display = 'none';
                    }

                    // ✅ ตรวจสอบการเปลี่ยนแปลงของ select
                    document.getElementById('close_detail_id').addEventListener('change', function() {
                        const selectedText = this.options[this.selectedIndex].text;
                        const additionalDetail = document.getElementById('additionalDetail');
                        if (selectedText === 'อื่น ๆ' || selectedText === 'อื่นๆ' || selectedText.includes('อื่น')) {
                            additionalDetail.style.display = 'block';
                        } else {
                            additionalDetail.style.display = 'none';
                        }
                    });

                    // ✅ ยกเลิก modal
                    document.getElementById('closeModalBtn').addEventListener('click', closeModal);

                    // ✅ ยืนยันปิดงาน
                    document.getElementById('confirmCloseBtn').addEventListener('click', function() {
                        const jobId = this.getAttribute('data-job-id');
                        const closeDetailId = document.getElementById('close_detail_id').value;
                        const detail = document.getElementById('detail').value;

                        if (!closeDetailId) {
                            alert('กรุณาเลือกเหตุผลในการปิดงาน');
                            return;
                        }

                        fetch('save_close_job.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded'
                                },
                                body: `job_id=${jobId}&close_detail_id=${closeDetailId}&detail=${encodeURIComponent(detail)}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert(data.message);
                                    location.reload();
                                } else {
                                    alert('เกิดข้อผิดพลาด: ' + data.message);
                                }
                            })

                            .catch(error => console.error('Error saving close reason:', error));
                    });

                    // ✅ toggle สถานะปุ่ม
                    function toggleStatus() {
                        const statusBtn = document.getElementById('statusBtn');
                        const currentStatus = parseInt(statusBtn.getAttribute('data-status'));

                        if (currentStatus === 1) {
                            openCloseReasonModal();
                        } else {
                            // เปิดงานใหม่
                            const jobId = statusBtn.getAttribute('data-job-id');
                            fetch('update_status.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    },
                                    body: `job_id=${jobId}&new_status=1`
                                })
                                .then(response => response.text())
                                .then(data => {
                                    if (data.trim() === 'success') {

                                        location.reload(); //แก้ ลบแจ้งเตือนเฉยๆอบรรทัดข้างบน
                                    } else {
                                        alert('เกิดข้อผิดพลาด: ' + data);
                                    }
                                })
                                .catch(error => console.error('Error updating status:', error));
                        }
                    }
                </script>




            </div>
        </div>



        <form method="POST" action="jobmanage.php?post_job_id=<?php echo $job_id; ?>">


            <div class="form-group">
                <label class="form-label">Job Name/ชื่องาน</label>
                <input type="text" class="form-input" name="title" value="<?php echo htmlspecialchars($job['title']); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Skill</label>
                <select class="form-select" id="skill-select">
                    <option value="">-- เลือก Skill --</option>
                    <?php foreach ($skills as $skill): ?>
                        <option value="<?php echo $skill['skill_id']; ?>"><?php echo htmlspecialchars($skill['skill_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="subskill-container" style="display: none;">
                <label class="form-label">Subskill</label>
                <select class="form-select" id="subskill-select" multiple>
                    <!-- ตัวเลือก Subskill จะถูกเติมโดย JS -->
                </select>
                <button type="button" class="btn btn-info mt-2" id="add-skill">เพิ่ม Skill + Subskill</button>
            </div>

            <!-- Summary Box สำหรับแสดงรายการ Skill ที่เลือกไว้ (Preload) -->
            <div class="form-group mt-3">
                <label class="form-label">รายการที่เลือก:</label>
                <ul id="selection-summary">
                    <?php if (!empty($skillSummary)): ?>
                        <?php foreach ($skillSummary as $skillId => $data): ?>
                            <li>
                                <?php echo htmlspecialchars($data['skill_name']); ?>:
                                <?php echo implode(", ", array_map('htmlspecialchars', $data['subskills'])); ?>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>ไม่มีรายการที่เลือกไว้</li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Hidden inputs เพื่อส่งค่า -->
            <div id="hidden-inputs">
                <?php foreach ($skillSummary as $skillId => $skillData): ?>
                    <input type="hidden" name="skills[]" value="<?php echo $skillId; ?>">
                    <?php foreach ($skillData['subskills'] as $subskillId): ?>
                        <input type="hidden" name="subskills[<?php echo $skillId; ?>][]" value="<?php echo $subskillId; ?>">
                    <?php endforeach; ?>
                <?php endforeach; ?>

            </div>



            <div class="form-group">
                <label class="form-label">Job Category/ประเภทงาน</label>
                <select class="form-select" name="job_category_id" id="category-select">
                    <option value="">-- เลือกประเภทงาน --</option>
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?php echo $category['job_category_id']; ?>" <?php echo ($category['job_category_id'] == $job['job_category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['job_category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="job-sub-container" style="display: none;">
                <label class="form-label">Job Subcategory/งานย่อย</label>
                <select class="form-select" name="job_subcategory_id" id="job-sub-select" required>
                    <option value="">-- เลือกงานย่อย --</option>
                </select>
            </div>

            <!--จำนวนนิสิตที่รับ-->
            <div class="form-group">
                <label class="form-label">Student Count Required/จำนวนตำแหน่งที่ต้องการ</label>
                <input type="number" name="number_student" value="<?php echo htmlspecialchars($job['number_student']); ?>" min="1" required>
            </div>

            <!--วันเริ่มงานกับจบงาน-->
            <div class="form-group">
                <label class="form-label">Start Date & Time/เวลาเริ่มงาน</label>
                <input type="datetime-local" name="job_start" value="<?php echo htmlspecialchars($job['job_start']); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">End Date & Time/เวลาสิ้นสุดงาน</label>
                <input type="datetime-local" name="job_end" value="<?php echo htmlspecialchars($job['job_end']); ?>" required>
            </div>


            <div class="form-group">
                <label class="form-label">Cover photo/ภาพหน้าปกงาน</label>
                <div class="images">
                    <img src="images/img1.jpg" alt="Image 1" onclick="selectImage(this)">
                    <img src="images/img2.jpg" alt="Image 2" onclick="selectImage(this)">
                    <input type="hidden" name="image" id="selectedImagePath">
                </div>
            </div>



            <div class="form-group">
                <label class="form-label">Reward type /ผลตอบแทน </label>
                <select class="form-select" name="reward_type_id" id="reward-type-select">
                    <option value="">-- เลือกประเภทผลตอบแทน --</option>
                    <?php foreach ($reward as $reward_type) : ?>
                        <option value="<?php echo $reward_type['reward_type_id']; ?>" <?php echo ($reward_type['reward_type_id'] == $job['reward_type_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($reward_type['reward_type_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ช่องสำหรับกรอกค่าตามประเภท -->
            <div class="form-group" id="reward-input-container">
                <label class="form-label" id="reward-label">
                    <span id="reward-label-text">จำนวนผลตอบแทน</span> <span id="reward-unit"></span>
                </label>
                <input type="number" class="form-input" name="time_and_wage" id="reward-input" value="<?php echo htmlspecialchars($job['time_and_wage']); ?>" min="1">
            </div>

            <div class="form-group" id="day-count-container" style="display: none;">
                <label class="form-label">จำนวนวัน</label>
                <input type="number" class="form-input" name="day_count" id="day-count-input" min="1" value="1">
            </div>

            <div class="form-group">
                <label class="form-label">รวมค่าตอบแทนทั้งหมด</label>
                <input type="text" class="form-input" id="total-reward-display" readonly>
            </div>






            <div class="form-group">
                <label class="form-label">Job Details/รายละเอียดงาน</label>
                <textarea name="description"><?php echo htmlspecialchars($job['description']); ?></textarea>
            </div>



            <!--ปุ่มส่ง-->
            <div class="submit-group">
                <button type="submit" name="delete_job" class="delete-btn" style="background-color: <?php echo ($job['job_status_id'] == 3) ? 'gray' : 'white'; ?>;">
                    <?php echo ($job['job_status_id'] == 3) ? 'Deleted' : 'Delete'; ?>
                </button>
                <button type="submit" name="done" class="submit-btn">Done</button>
            </div>
        </form>
        </div>

    </main>
    <footer class="footer">
        <p>© CSIT - Computer Science and Information Technology</p>
    </footer>
    <!-- Script วางหลัง modal -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('closeModalBtn').addEventListener('click', function() {
                document.getElementById('modalOverlay').style.display = 'none';
                document.getElementById('closeReasonModal').style.display = 'none';
            });

        });
    </script>
    <script>
        // เปลี่ยนสถานะของภาพที่เลือก
        function selectImage(imageElement) {
            // ลบคลาส selected ออกจากภาพทั้งหมด
            var images = document.querySelectorAll('.images img');
            images.forEach(img => img.classList.remove('selected'));

            // เพิ่มคลาส selected ให้กับภาพที่ถูกเลือก
            imageElement.classList.add('selected');

            // ดึง path ของภาพที่ถูกเลือก (เอาแค่ชื่อไฟล์ไม่รวม URL)
            var imagePath = imageElement.src.split('/').pop(); // หรือใช้ substring เพื่อดึงชื่อไฟล์

            // อัปเดตค่า imagePath ให้กับ input hidden
            document.getElementById('selectedImagePath').value = "images/" + imagePath;
        }

        // เมื่อคลิกที่ลิงก์ "View Applications" หรือ "Manage Job" 
        // เพื่อให้ลิงก์ที่คลิกแสดงสถานะ active
        function toggleViewApp(element) {
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            element.classList.add('active');
        }

        // เมื่อคลิกที่ลิงก์ "Manage Job" เพื่อให้ลิงก์ที่คลิกแสดงสถานะ active
        function toggleManageJob(element) {
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            element.classList.add('active');
        }
    </script>


    <script>
        document.getElementById("job-end").addEventListener("change", function() {
            const startTime = document.getElementById("job-start").value;
            const endTime = this.value;

            if (startTime && endTime && endTime <= startTime) {
                alert("เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น!");
                this.value = ""; // ล้างค่าเวลาสิ้นสุด
            }
        });
    </script>

    <script>
        document.getElementById("reward-type-select").addEventListener("change", function() {
            const rewardType = this.value;
            const rewardInputContainer = document.getElementById("reward-input-container");
            const rewardLabelText = document.getElementById("reward-label-text");
            const rewardUnit = document.getElementById("reward-unit");
            const rewardInput = document.getElementById("reward-input");

            if (rewardType) {
                if (rewardType == "1") {
                    rewardLabelText.textContent = "Hours/จำนวนชั่วโมง";
                    rewardUnit.textContent = "ชั่วโมง";
                    rewardInput.placeholder = "กรอกจำนวนชั่วโมงที่ต้องการให้ผู้สมัครทำงาน";
                } else if (rewardType == "2") {
                    rewardLabelText.textContent = "Money/จำนวนเงิน";
                    rewardUnit.textContent = "บาท";
                    rewardInput.placeholder = "กรอกจำนวนเงินที่ต้องการให้ผู้สมัครทำงาน";
                } else if (rewardType == "3") {
                    rewardLabelText.textContent = "Money/จำนวนเงิน";
                    rewardUnit.textContent = "บาท";
                    rewardInput.placeholder = "กรอกจำนวนเงินที่ต้องการให้ผู้สมัครทำงาน";
                }

                rewardInputContainer.style.display = "block";
            } else {
                rewardInputContainer.style.display = "none";
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('category-select');
            const subcategoryContainer = document.getElementById('job-sub-container');
            const subcategorySelect = document.getElementById('job-sub-select');

            function loadSubcategories(categoryId, selectedSubcategoryId = null) {
                if (categoryId) {
                    fetch('get_job_subcategories.php?category_id=' + categoryId)
                        .then(response => response.json())
                        .then(data => {
                            subcategorySelect.innerHTML = '<option value="">-- เลือกงานย่อย --</option>';

                            if (data.length > 0) {
                                data.forEach(subcategory => {
                                    const option = document.createElement('option');
                                    option.value = subcategory.job_subcategory_id;
                                    option.textContent = subcategory.job_subcategory_name;

                                    // เช็คถ้ามี subcategory ที่ถูกเลือกไว้
                                    if (selectedSubcategoryId && subcategory.job_subcategory_id == selectedSubcategoryId) {
                                        option.selected = true;
                                    }

                                    subcategorySelect.appendChild(option);
                                });
                                subcategoryContainer.style.display = 'block';
                            } else {
                                subcategoryContainer.style.display = 'none';
                            }
                        })
                        .catch(error => {
                            console.error('เกิดข้อผิดพลาด:', error);
                            subcategoryContainer.style.display = 'none';
                        });
                } else {
                    subcategoryContainer.style.display = 'none';
                    subcategorySelect.innerHTML = '<option value="">-- เลือกงานย่อย --</option>';
                }
            }

            // ✅ โหลด subcategory ตอนเปิดหน้า
            const initialCategoryId = categorySelect.value;
            const initialSubcategoryId = "<?php echo $job['job_subcategory_id'] ?? ''; ?>";
            loadSubcategories(initialCategoryId, initialSubcategoryId);

            // ✅ โหลดใหม่เมื่อเปลี่ยน category
            categorySelect.addEventListener('change', function() {
                loadSubcategories(this.value);
            });
        });
    </script>

    <script>
        // ✅ แก้ตรงนี้ให้ชัดเจน: ใช้ global selections array ตัวเดียวทั่วทั้งไฟล์!
        window.selections = [];
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const skillSelect = document.getElementById('skill-select');
            const subskillContainer = document.getElementById('subskill-container');
            const subskillSelect = document.getElementById('subskill-select');
            const addSkillBtn = document.getElementById('add-skill');
            const selectionSummary = document.getElementById('selection-summary');
            const hiddenInputs = document.getElementById('hidden-inputs');

            const selections = window.selections;
            // เก็บ skill-subskill ที่เลือกไว้

            // เมื่อเลือก Skill -> โหลด Subskill
            skillSelect.addEventListener('change', function() {
                const skillId = this.value;
                if (skillId) {
                    fetch('get_subskill.php?skill_id=' + skillId)
                        .then(response => response.json())
                        .then(data => {
                            subskillSelect.innerHTML = '';
                            data.forEach(subskill => {
                                const option = document.createElement('option');
                                option.value = subskill.subskill_id;
                                option.textContent = subskill.subskill_name;
                                subskillSelect.appendChild(option);
                            });
                            subskillContainer.style.display = 'block';
                        });
                } else {
                    subskillContainer.style.display = 'none';
                    subskillSelect.innerHTML = '';
                }
            });

            // ปุ่ม "เพิ่ม Skill + Subskill"
            addSkillBtn.addEventListener('click', function() {
                const skillId = skillSelect.value;
                const skillText = skillSelect.options[skillSelect.selectedIndex].text;
                const selectedSubskills = Array.from(subskillSelect.selectedOptions).map(opt => ({
                    id: opt.value,
                    name: opt.text
                }));

                if (!skillId || selectedSubskills.length === 0) {
                    alert('กรุณาเลือก Skill และอย่างน้อย 1 Subskill');
                    return;
                }

                const existing = selections.find(s => s.skillId === skillId);
                if (existing) {
                    // รวม subskill ใหม่เข้าไปโดยไม่ alert
                    selectedSubskills.forEach(subskill => {
                        if (!existing.subskills.find(s => s.id === subskill.id)) {
                            existing.subskills.push(subskill);
                        }
                    });
                } else {
                    const newSelection = {
                        skillId,
                        skillName: skillText,
                        subskills: selectedSubskills
                    };
                    selections.push(newSelection);
                }

                renderSelections();
            });


            function renderSelections() {
                selectionSummary.innerHTML = '';
                hiddenInputs.innerHTML = '';

                selections.forEach((selection, index) => {
                    const li = document.createElement('li');
                    li.textContent = `${selection.skillName}: ${selection.subskills.map(s => s.name).join(', ')}`;

                    const removeBtn = document.createElement('button');
                    removeBtn.textContent = 'ลบ';
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn btn-sm btn-danger ms-2';
                    removeBtn.addEventListener('click', () => {
                        if (confirm('ยืนยันการลบรายการนี้หรือไม่?')) {
                            // ✅ เรียก API ลบออกจาก database ด้วย
                            fetch('delete_skill_subskill.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    },
                                    body: `post_job_id=<?php echo $job_id; ?>&skill_id=${selection.skillId}`
                                })
                                .then(response => response.text())
                                .then(data => {
                                    if (data.trim() === 'success') {
                                        // ลบออกจาก selections array
                                        selections.splice(index, 1);
                                        renderSelections();
                                    } else {
                                        alert('เกิดข้อผิดพลาด: ' + data);
                                    }
                                })
                                .catch(error => console.error('Error:', error));
                        }
                    });

                    li.appendChild(removeBtn);
                    selectionSummary.appendChild(li);

                    // สร้าง hidden input สำหรับ form submit
                    const skillInput = document.createElement('input');
                    skillInput.type = 'hidden';
                    skillInput.name = 'skills[]';
                    skillInput.value = selection.skillId;
                    hiddenInputs.appendChild(skillInput);

                    selection.subskills.forEach(subskill => {
                        const subskillInput = document.createElement('input');
                        subskillInput.type = 'hidden';
                        subskillInput.name = `subskills[${selection.skillId}][]`;
                        subskillInput.value = subskill.id;
                        hiddenInputs.appendChild(subskillInput);
                    });
                });
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selections = window.selections;
            // ใช้เก็บ selection ปัจจุบัน

            <?php
            // ✅ PHP part: ดึง skill/subskill ทั้งหมด
            $preloadSkills = [];
            $stmt = $conn->prepare("SELECT skill_id, subskill_id FROM post_job_skill WHERE post_job_id = ?");
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $skillId = $row['skill_id'];
                $subskillId = $row['subskill_id'];
                if (!isset($preloadSkills[$skillId])) {
                    $preloadSkills[$skillId] = [];
                }
                $preloadSkills[$skillId][] = $subskillId;
            }
            $stmt->close();


            // ✅ ดึงชื่อ skill/subskill ที่เกี่ยวข้องมา
            foreach ($preloadSkills as $skillId => $subskillIds) {
                // Skill name
                $skillNameQuery = $conn->prepare("SELECT skill_name FROM skill WHERE skill_id = ?");
                $skillNameQuery->bind_param("i", $skillId);
                $skillNameQuery->execute();
                $skillNameResult = $skillNameQuery->get_result()->fetch_assoc();
                $skillName = $skillNameResult['skill_name'];
                $skillNameQuery->close();

                // Subskills
                $subskills = [];
                foreach ($subskillIds as $subskillId) {
                    $subskillNameQuery = $conn->prepare("SELECT subskill_name FROM subskill WHERE subskill_id = ?");
                    $subskillNameQuery->bind_param("i", $subskillId);
                    $subskillNameQuery->execute();
                    $subskillNameResult = $subskillNameQuery->get_result()->fetch_assoc();
                    $subskills[] = [
                        'id' => $subskillId,
                        'name' => $subskillNameResult['subskill_name']
                    ];
                    $subskillNameQuery->close();
                }

                // ✅ echo preload selections
                echo "selections.push({
            skillId: '$skillId',
            skillName: '" . addslashes($skillName) . "',
            subskills: " . json_encode($subskills) . "
        });\n";

                // ✅ echo preload subskills dropdown select2
                echo "preloadSubskills('$skillId', " . json_encode(array_map('strval', $subskillIds)) . ");\n";
            }
            ?>

            renderSelections();
        });
    </script>

    <script>
        function preloadSubskills(skillId, selectedSubskillIds) {
            fetch('get_subskill.php?skill_id=' + skillId)
                .then(response => response.json())
                .then(data => {
                    // ถ้า skill ที่ preload ตรงกับ skill-select ที่แสดงอยู่ จะ preload subskill ลงไป
                    const skillSelect = document.getElementById('skill-select');
                    const subskillSelect = document.getElementById('subskill-select');

                    // ตั้งค่าค่า skill-select
                    skillSelect.value = skillId;

                    // ล้าง subskill-select
                    subskillSelect.innerHTML = '';

                    data.forEach(subskill => {
                        const option = document.createElement('option');
                        option.value = subskill.subskill_id;
                        option.textContent = subskill.subskill_name;

                        if (selectedSubskillIds.includes(subskill.subskill_id.toString())) {
                            option.selected = true;
                        }

                        subskillSelect.appendChild(option);
                    });

                    // แสดง subskill-container
                    document.getElementById('subskill-container').style.display = 'block';
                });
        }
        document.addEventListener('DOMContentLoaded', function() {
            const selections = window.selections;
            // ✅ ประกาศ global
            <?php foreach ($skillSummary as $skillId => $skillData): ?>
                selections.push({
                    skillId: '<?php echo $skillId; ?>',
                    skillName: '<?php echo addslashes($skillData['skill_name']); ?>',
                    subskills: [
                        <?php foreach ($skillData['subskills'] as $subskillName): ?>
                            <?php
                            // หา subskill id จากชื่อ
                            $subskillQuery = $conn->prepare("SELECT subskill_id FROM subskill WHERE subskill_name = ?");
                            $subskillQuery->bind_param("s", $subskillName);
                            $subskillQuery->execute();
                            $subskillResult = $subskillQuery->get_result()->fetch_assoc();
                            $subskillId = $subskillResult['subskill_id'] ?? 0;
                            $subskillQuery->close();
                            ?> {
                                id: '<?php echo $subskillId; ?>',
                                name: '<?php echo addslashes($subskillName); ?>'
                            },
                        <?php endforeach; ?>
                    ]
                });
            <?php endforeach; ?>

            renderSelections(); // ✅ เรียกเพื่อสร้าง hidden input จาก preload

        });
        <?php
        // ✅ PHP preload data
        $preloadSkills = [];
        $stmt = $conn->prepare("SELECT skill_id, subskill_id FROM post_job_skill WHERE post_job_id = ?");
        $stmt->bind_param("i", $job_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $skillId = $row['skill_id'];
            $subskillId = $row['subskill_id'];
            if (!isset($preloadSkills[$skillId])) {
                $preloadSkills[$skillId] = [];
            }
            $preloadSkills[$skillId][] = $subskillId;
        }
        $stmt->close();

        // ✅ preload รายการที่เลือก
        foreach ($preloadSkills as $skillId => $subskillIds) {
            // ดึงชื่อ skill
            $skillNameQuery = $conn->prepare("SELECT skill_name FROM skill WHERE skill_id = ?");
            $skillNameQuery->bind_param("i", $skillId);
            $skillNameQuery->execute();
            $skillNameResult = $skillNameQuery->get_result()->fetch_assoc();
            $skillName = $skillNameResult['skill_name'];
            $skillNameQuery->close();

            // ดึง subskill
            $subskills = [];
            foreach ($subskillIds as $subskillId) {
                $subskillNameQuery = $conn->prepare("SELECT subskill_name FROM subskill WHERE subskill_id = ?");
                $subskillNameQuery->bind_param("i", $subskillId);
                $subskillNameQuery->execute();
                $subskillNameResult = $subskillNameQuery->get_result()->fetch_assoc();
                $subskills[] = [
                    'id' => $subskillId,
                    'name' => $subskillNameResult['subskill_name']
                ];
                $subskillNameQuery->close();
            }

            // ✅ push preload ลง selections
            echo "selections.push({
                skillId: '$skillId',
                skillName: '" . addslashes($skillName) . "',
                subskills: " . json_encode($subskills) . "
            });\n";
        }
        ?>

        // ✅ preload เสร็จแล้ว เรียก renderSelections() เลย!
        renderSelections();

        });
    </script>
    <script>
        // ส่งข้อมูลนี้เข้า JS (แนะนำไว้ก่อน tag ปิด body )
        const preloadSelections = <?php echo json_encode($skillSummary); ?>;
        const selections = window.selections;
        // ใช้ global ตัวนี้เท่านั้น!
        for (const skillId in preloadSelections) {
            selections.push({
                skillId: skillId,
                skillName: preloadSelections[skillId].skill_name,
                subskills: preloadSelections[skillId].subskills.map(name => ({
                    name: name,
                    id: null
                })) // subskill_id ตอนนี้ null เพราะ preload, แต่เราจะใช้ id นี้ส่ง form
            });
        }

        renderSelections(); // ใช้ function renderSelections() เดิม ไม่ต้องสร้าง renderPreloadSelections ใหม่

        document.addEventListener('DOMContentLoaded', function() {
            const selections = window.selections;
            // ✅ Global array ที่ใช้ renderSelections()

            <?php foreach ($skillSummary as $skillId => $skillData): ?>
                const preloadSubskills = [];
                <?php foreach ($skillData['subskills'] as $subskillName): ?>
                    <?php
                    // หา subskill id จากชื่อ (จะได้ส่ง form ได้ด้วย)
                    $subskillQuery = $conn->prepare("SELECT subskill_id FROM subskill WHERE subskill_name = ?");
                    $subskillQuery->bind_param("s", $subskillName);
                    $subskillQuery->execute();
                    $subskillResult = $subskillQuery->get_result()->fetch_assoc();
                    $subskillId = $subskillResult['subskill_id'] ?? 0;
                    $subskillQuery->close();
                    ?>
                    preloadSubskills.push({
                        id: '<?php echo $subskillId; ?>',
                        name: '<?php echo addslashes($subskillName); ?>'
                    });
                <?php endforeach; ?>

                selections.push({
                    skillId: '<?php echo $skillId; ?>',
                    skillName: '<?php echo addslashes($skillData['skill_name']); ?>',
                    subskills: preloadSubskills
                });
            <?php endforeach; ?>

            renderSelections(); // ✅ เรียกทันที เพื่อ inject hidden input เตรียมบันทึก
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rewardTypeSelect = document.getElementById('reward-type-select'); //แก้ อันนี้จะเพิ่มที่ต้องคำนวณเงินแต่มันไม่ได้
            const rewardInputContainer = document.getElementById('reward-input-container');
            const rewardInput = document.getElementById('reward-input');
            const rewardLabelText = document.getElementById('reward-label-text');
            const rewardUnit = document.getElementById('reward-unit');
            const dayCountContainer = document.getElementById('day-count-container');
            const dayCountInput = document.getElementById('day-count-input');

            function updateRewardInput() {
                const selectedRewardType = parseInt(rewardTypeSelect.value);

                if (!selectedRewardType) {
                    rewardInputContainer.style.display = 'none';
                    dayCountContainer.style.display = 'none';
                    return;
                }

                rewardInputContainer.style.display = 'block';

                if (selectedRewardType === 1) {
                    rewardLabelText.textContent = 'ค่าตอบแทน';
                    rewardUnit.textContent = 'บาท';
                    dayCountContainer.style.display = 'none';
                } else if (selectedRewardType === 2) {
                    rewardLabelText.textContent = 'ค่าตอบแทนแบบครั้งเดียว';
                    rewardUnit.textContent = 'บาท';
                    dayCountContainer.style.display = 'none';
                } else if (selectedRewardType === 3) {
                    rewardLabelText.textContent = 'ค่าตอบแทนต่อวัน';
                    rewardUnit.textContent = 'บาท';
                    dayCountContainer.style.display = 'block';
                }
            }

            rewardTypeSelect.addEventListener('change', updateRewardInput);
            updateRewardInput();

            // คำนวณค่าตอบแทนรวม
            function calculateTotalReward() {
                rewardInput.addEventListener('input', calculateTotalReward);
                dayCountInput.addEventListener('input', calculateTotalReward);
                rewardTypeSelect.addEventListener('change', calculateTotalReward);

                const selectedRewardType = parseInt(rewardTypeSelect.value);
                let total = parseFloat(rewardInput.value) || 0;

                if (selectedRewardType === 3) {
                    const days = parseFloat(dayCountInput.value) || 1;
                    total = total * days;
                }

                // แสดงผลรวมใน input แสดงผล
                const totalRewardDisplay = document.getElementById('total-reward-display');
                totalRewardDisplay.value = total.toFixed(2) + ' บาท';

                return total;
            }


            // ตอน submit form ให้เพิ่ม hidden input ส่งค่าที่คำนวณไป
            const form = document.querySelector('form');
            form.addEventListener('submit', function() {
                let existingHidden = document.querySelector('input[name="calculated_salary"]');
                if (!existingHidden) {
                    existingHidden = document.createElement('input');
                    existingHidden.type = 'hidden';
                    existingHidden.name = 'calculated_salary';
                    form.appendChild(existingHidden);
                }
                existingHidden.value = calculateTotalReward();
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const jobElement = document.getElementById('job');
            const jobId = jobElement.getAttribute('data-job-id');
            const jobEndTime = new Date(jobElement.getAttribute('data-job-end')).getTime(); //แก้ ส่วนนี้จะทำตั้งเวลาปิดอัตโนมัติ กำล้งลองทำแต่ไม่ได้555

            function checkJobEnd() {
                const now = new Date().getTime();
                const timeRemaining = jobEndTime - now;

                if (timeRemaining <= 0) {
                    console.log("🔔 Job expired. Closing job...");

                    // เรียก API ไปปิดงาน
                    fetch('auto_close_job.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `job_id=${jobId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                console.log('✅ Job closed successfully');
                                alert('Job has been auto-closed.');
                                // อาจจะ refresh หน้า หรืออัปเดต UI ตรงนี้ได้
                            } else {
                                console.error('❌ Failed to close job:', data.message);
                            }
                        })
                        .catch(error => console.error('❌ Error closing job:', error));
                } else {
                    console.log(`⌛ Time remaining: ${Math.floor(timeRemaining / 1000)} seconds`);
                    // เช็คใหม่ทุก 1 วิ
                    setTimeout(checkJobEnd, 1000);
                }
            }

            checkJobEnd();
        });
    </script>



</body>






</html>