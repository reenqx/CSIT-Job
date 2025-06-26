<?php
session_start();
include 'database.php';
$_SESSION['teacher_id'] = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'CSIT0131';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    date_default_timezone_set('Asia/Bangkok');
    $created_at = date('Y-m-d H:i:s');
    // รับค่าจากฟอร์ม
    $title = $_POST['title'] ?? null;
    $description = $_POST['description'] ?? null;
    $start = $_POST['job_start'] ?? date('Y-m-d H:i:s');
    $end = $_POST['job_end'] ?? date('Y-m-d H:i:s');
    $number_student = isset($_POST['number_student']) && is_numeric($_POST['number_student']) ? intval($_POST['number_student']) : null;
    $reward_type_id = $_POST['reward_type_id'] ?? null;
    $time_and_wage = $_POST['time_and_wage'] ?? null;
    $category_id = $_POST['job_category_id'] ?? null;
    $subcategory_id = $_POST['job_subcategory_id'] ?? null;
    $job_status_id = 1;
    $teacher_id = $_SESSION['teacher_id'];
    $images = $_POST['image'] ?? null;
    // รับข้อมูล Skill ที่เลือกจาก hidden inputs
    $skillsArray = $_POST['skills'] ?? [];
    $subskillsArray = $_POST['subskills'] ?? []; // รูปแบบ: subskills[skill_id] = array( subskill_id, ... )

    // ตรวจสอบค่าที่จำเป็น
    if (!$title || !$reward_type_id || !$number_student) {
        echo "<script>alert('Error: Missing required fields.'); window.history.back();</script>";
        exit;
    }
    if ($start && $end && $end <= $start) {
        echo "<script>alert('Error: เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น!'); window.history.back();</script>";
        exit;
    }
    if ($reward_type_id == "1" && (!is_numeric($time_and_wage) || $time_and_wage <= 0)) {
        echo "<script>alert('Error: กรุณากรอกจำนวนชั่วโมงที่ถูกต้อง!'); window.history.back();</script>";
        exit;
    } elseif ($reward_type_id == "2" && (!is_numeric($time_and_wage) || $time_and_wage <= 0)) {
        echo "<script>alert('Error: กรุณากรอกจำนวนเงินที่ถูกต้อง!'); window.history.back();</script>";
        exit;
    }

    // บันทึกข้อมูลลงใน post_job
    $stmt = $conn->prepare("INSERT INTO post_job
        (title, reward_type_id, description, job_start, job_end, number_student, time_and_wage, job_category_id, job_subcategory_id, teacher_id, created_at, job_status_id, image) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "sisssiiiissis",
        $title,
        $reward_type_id,
        $description,
        $start,
        $end,
        $number_student,
        $time_and_wage,
        $category_id,
        $subcategory_id,
        $teacher_id,
        $created_at,
        $job_status_id,
        $images
    );
    if ($stmt->execute()) {
        $post_job_id = $conn->insert_id;
        // บันทึกข้อมูล Skill (วนลูปตามแต่ละ Skill ที่เลือก)
        // รับข้อมูล Skill จาก hidden inputs ที่ส่งมา
        $skillsArray = $_POST['skills'] ?? [2];
        $subskillsArray = $_POST['subskills'] ?? [2]; // subskills[skill_id] = array( subskill_id, ... )

        // วนลูป insert ข้อมูลลงใน post_job_skill
        foreach ($skillsArray as $skillId) {
            if (isset($subskillsArray[$skillId]) && is_array($subskillsArray[$skillId])) {
                foreach ($subskillsArray[$skillId] as $subskillId) {
                    $stmtSkill = $conn->prepare("INSERT INTO post_job_skill (post_job_id, skill_id, subskill_id) VALUES (?, ?, ?)");
                    $stmtSkill->bind_param("iii", $post_job_id, $skillId, $subskillId);
                    if (!$stmtSkill->execute()) {
                        echo "Insert error: " . $stmtSkill->error;
                    }
                    $stmtSkill->close();
                }
            }
        }


        echo "<script>alert('New job posted successfully'); window.location='teacher_profile.php';</script>";
    } else {
        echo "<script>alert('Error: {$stmt->error}'); window.history.back();</script>";
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Posting Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/jobpose.css">
    <link rel="stylesheet" href="css/header-footerstyle.css">
    <style>
        .skills-container {
            width: 100%;
            max-width: 800px;
            max-height: 400px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 15px 28px;
            background: #fff;
            overflow-y: auto;
            overflow-x: hidden;
            margin-bottom: 20px;
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
    <!-- Back -->
    <nav class="back-head">
        <a href="javascript:history.back()"> <i class="bi bi-chevron-left"></i></a>
    </nav>
    <!-- Main Content -->
    <main class="container">
        <div class="form-card">
            <h1 class="form-title">Job Posting</h1>

            <form method="POST" action="jobpost.php">
                <!-- Job Name -->
                <div class="form-group">
                    <label class="form-label">Job Name/ชื่องาน</label>
                    <input type="text" class="form-input" name="title" required>
                </div>
                <!-- Skill Selection Section -->
                <?php
                // ดึงรายการ Skill จากตาราง skill
                $post_job_skill = [];
                $sql = "SELECT skill_id, skill_name FROM skill ORDER BY skill_id";
                $result = $conn->query($sql);
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $post_job_skill[] = $row;
                    }
                }
                ?>
                <div class="form-group">
                    <label class="form-label">Skill Required / ทักษะที่ต้องการ</label>
                    <!-- เลือก Skill หลัก -->
                    <select class="form-select" id="skill-select">
                        <option value="">-- เลือกประเภทสกิล --</option>
                        <?php foreach ($post_job_skill as $skill) : ?>
                            <option value="<?php echo $skill['skill_id']; ?>">
                                <?php echo htmlspecialchars($skill['skill_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Container สำหรับเลือก Subskill เมื่อเลือก Skill แล้ว -->
                <div class="form-group" id="subskill-container" style="display: none;">
                    <label class="form-label">Subskill / สกิลย่อย</label>
                    <!-- multi-select สำหรับ Subskill -->
                    <select class="form-select" id="subskill-select" multiple>
                        <!-- ตัวเลือก Subskill จะถูกเติมโดย JavaScript -->
                    </select>
                    <button type="button" class="btn btn-info mt-2" id="add-skill-btn">เพิ่ม Skill + Subskill</button>
                </div>
                <!-- Summary Box สำหรับแสดงรายการ Skill ที่เลือกไว้ -->
                <!-- Summary Box -->
                <div class="form-group mt-3">
                    <label class="form-label">รายการที่เลือก:</label>
                    <ul id="selection-summary"></ul>
                </div>

                <!-- Hidden inputs เพื่อส่งค่า -->
                <div id="hidden-inputs"></div>
                <!-- Job Category -->
                <?php
                $categories = [];
                $sql = "SELECT job_category_id, job_category_name FROM job_category ORDER BY job_category_id";
                $result = $conn->query($sql);
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $categories[] = $row;
                    }
                }
                ?>
                <div class="form-group">
                    <label class="form-label">Job Category/ประเภทงาน</label>
                    <select class="form-select" name="job_category_id" id="category-select" required>
                        <option value="">-- เลือกประเภทงาน --</option>
                        <?php foreach ($categories as $category_id) : ?>
                            <option value="<?php echo $category_id['job_category_id']; ?>">
                                <?php echo htmlspecialchars($category_id['job_category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Job Subcategory -->
                <div class="form-group" id="job-sub-container" style="display: none;">
                    <label class="form-label">Job Subcategory/งานย่อย</label>
                    <select class="form-select" name="job_subcategory_id" id="job-sub-select" required>
                        <option value="">-- เลือกงานย่อย --</option>
                    </select>
                </div>
                <!-- Student Count -->
                <div class="form-group">
                    <label class="form-label">Student Count Required/จำนวนตำแหน่งที่ต้องการ</label>
                    <input type="number" id="vacancy" name="number_student" min="1">
                </div>
                <!-- Start Date & Time -->
                <div class="form-group">
                    <label class="form-label">Start Date & Time/เวลาเริ่มงาน</label>
                    <input type="datetime-local" class="form-input" name="job_start" id="job-start" required>
                </div>
                <!-- End Date & Time -->
                <div class="form-group">
                    <label class="form-label">End Date & Time/เวลาสิ้นสุดงาน</label>
                    <input type="datetime-local" class="form-input" name="job_end" id="job-end" required>
                </div>
                <!-- Cover Photo -->
                <div class="form-group">
                    <label class="form-label">Cover photo/ภาพหน้าปกงาน</label>
                    <div class="images">
                        <img src="images/img1.jpg" alt="Image 1" onclick="selectImage(this)">
                        <img src="images/img2.jpg" alt="Image 2" onclick="selectImage(this)">
                        <input type="hidden" name="image" id="selectedImagePath">
                    </div>
                </div>
                <!-- Reward -->
                <?php
                $reward = [];
                $sql = "SELECT reward_type_id, reward_type_name_th FROM reward_type ORDER BY reward_type_id";
                $result = $conn->query($sql);
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $reward[] = $row;
                    }
                }
                ?>
                <div class="form-group">
                    <label class="form-label">Job Category/ผลตอบแทน</label>
                    <select class="form-select" name="reward_type_id" id="reward-type-select" required>
                        <option value="">-- เลือกประเภทผลตอบแทน --</option>
                        <?php foreach ($reward as $reward_type) : ?>
                            <option value="<?php echo $reward_type['reward_type_id']; ?>">
                                <?php echo htmlspecialchars($reward_type['reward_type_name_th']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Reward Input -->
                <div class="form-group" id="reward-input-container" style="display: none;">
                    <label class="form-label" id="reward-label">
                        <span id="reward-label-text"></span> <span id="reward-unit"></span>
                    </label>
                    <input type="number" class="form-input" name="time_and_wage" id="reward-input" min="1" required>
                </div>
                <!-- Job Details -->
                <div class="form-group">
                    <label class="form-label">Job Details/รายละเอียดงาน</label>
                    <textarea id="job-details" name="description" placeholder=""></textarea>
                </div>
                <!-- Submit Button -->
                <div class="submit-group">
                    <button type="submit" class="submit-btn">Add Job</button>
                </div>
            </form>
        </div>
    </main>
    <footer class="footer">
        <p>© CSIT - Computer Science and Information Technology</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/jobpost.js"></script>
    <script>
        function selectImage(imageElement) {
            var images = document.querySelectorAll('.images img');
            images.forEach(function(img) {
                img.classList.remove('selected');
            });
            imageElement.classList.add('selected');
            var imagePath = imageElement.src.split('/').pop();
            document.getElementById('selectedImagePath').value = "images/" + imagePath;
        }
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
        // สำหรับ Job Subcategory
        document.addEventListener("DOMContentLoaded", function() {
            const categorySelect = document.getElementById("category-select");
            const subcategoryContainer = document.getElementById("job-sub-container");
            const subcategorySelect = document.getElementById("job-sub-select");

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
                            console.error('Error:', error);
                            subcategoryContainer.style.display = 'none';
                        });
                } else {
                    subcategoryContainer.style.display = 'none';
                    subcategorySelect.innerHTML = '<option value="">-- เลือกงานย่อย --</option>';
                }
            }
            const initialCategoryId = categorySelect.value;
            const initialSubcategoryId = "";
            loadSubcategories(initialCategoryId, initialSubcategoryId);
            categorySelect.addEventListener('change', function() {
                loadSubcategories(this.value);
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selections = []; // ใช้เก็บ selection ปัจจุบัน

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
        document.addEventListener('DOMContentLoaded', function() {
            // Global array เก็บข้อมูลที่เลือกไว้
            var selections = [];

            // อ้างอิง element ที่เกี่ยวข้อง
            const skillSelect = document.getElementById('skill-select');
            const subskillContainer = document.getElementById('subskill-container');
            const subskillSelect = document.getElementById('subskill-select');
            const addSkillBtn = document.getElementById('add-skill-btn');
            const selectionSummary = document.getElementById('selection-summary');
            const hiddenInputs = document.getElementById('hidden-inputs');

            // เมื่อมีการเลือก Skill หลัก ให้ fetch Subskill จาก API
            skillSelect.addEventListener('change', function() {
                const skillId = this.value;
                if (skillId) {
                    fetch('get_subskill.php?skill_id=' + skillId)
                        .then(response => response.json())
                        .then(data => {
                            // Debug: ตรวจสอบข้อมูลที่ได้จาก API
                            console.log('Subskills:', data);
                            // เคลียร์ select เก่า
                            subskillSelect.innerHTML = '';
                            if (data.length > 0) {
                                data.forEach(function(subskill) {
                                    // ตรวจสอบ key ที่ส่งกลับจาก API ว่าตรงกันหรือไม่
                                    const option = document.createElement('option');
                                    option.value = subskill.subskill_id; // หาก API ส่งเป็น subskill_id
                                    option.textContent = subskill.subskill_name; // หาก API ส่งเป็น subskill_name
                                    subskillSelect.appendChild(option);
                                });
                                subskillContainer.style.display = 'block';
                            } else {
                                subskillContainer.style.display = 'none';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching subskills:', error);
                            subskillContainer.style.display = 'none';
                        });
                } else {
                    subskillContainer.style.display = 'none';
                    subskillSelect.innerHTML = '';
                }
            });

            // เมื่อคลิกปุ่ม "เพิ่ม Skill + Subskill"
            addSkillBtn.addEventListener('click', function() {
                const skillId = skillSelect.value;
                if (!skillId) {
                    alert('กรุณาเลือก Skill');
                    return;
                }
                const skillName = skillSelect.options[skillSelect.selectedIndex].text;
                // รับค่าจาก subskill-select (แบบ multi-select)
                const selectedSubOptions = Array.from(subskillSelect.selectedOptions);
                if (selectedSubOptions.length === 0) {
                    alert('กรุณาเลือกอย่างน้อย 1 Subskill');
                    return;
                }
                const subskills = selectedSubOptions.map(function(opt) {
                    return {
                        id: opt.value,
                        name: opt.text
                    };
                });
                // ตรวจสอบว่ามี Skill นี้อยู่ใน selections แล้วหรือไม่
                const existing = selections.find(item => item.skillId === skillId);
                if (existing) {
                    // เพิ่ม Subskill ใหม่โดยไม่ซ้ำ
                    selectedSubOptions.forEach(function(opt) {
                        if (!existing.subskills.some(s => s.id === opt.value)) {
                            existing.subskills.push({
                                id: opt.value,
                                name: opt.text
                            });
                        }
                    });
                } else {
                    selections.push({
                        skillId: skillId,
                        skillName: skillName,
                        subskills: subskills
                    });
                }
                renderSelections();
            });

            // ฟังก์ชันสำหรับแสดงรายการที่เลือกและสร้าง hidden inputs
            function renderSelections() {
                selectionSummary.innerHTML = '';
                hiddenInputs.innerHTML = '';
                selections.forEach((selection, index) => {
                    // สร้างรายการแสดงใน summary
                    const li = document.createElement('li');
                    li.textContent = `${selection.skillName}: ${selection.subskills.map(s => s.name).join(', ')}`;
                    // สร้างปุ่มลบรายการ
                    const removeBtn = document.createElement('button');
                    removeBtn.textContent = 'ลบ';
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn btn-sm btn-danger ms-2';
                    removeBtn.addEventListener('click', () => {
                        selections.splice(index, 1);
                        renderSelections();
                    });
                    li.appendChild(removeBtn);
                    selectionSummary.appendChild(li);

                    // สร้าง hidden input สำหรับส่งค่า Skill
                    const inputSkill = document.createElement('input');
                    inputSkill.type = 'hidden';
                    inputSkill.name = 'skills[]';
                    inputSkill.value = selection.skillId;
                    hiddenInputs.appendChild(inputSkill);
                    // สำหรับแต่ละ Subskill
                    selection.subskills.forEach(sub => {
                        const inputSub = document.createElement('input');
                        inputSub.type = 'hidden';
                        inputSub.name = `subskills[${selection.skillId}][]`;
                        inputSub.value = sub.id;
                        hiddenInputs.appendChild(inputSub);
                    });
                });
            }
        });
    </script>

</body>

</html>