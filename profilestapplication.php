<?php
session_start();
include 'database.php';
// รับค่า student_id จาก URL ด้วย filter_input เพื่อความปลอดภัย
$student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
if (!$student_id) {
    echo "ข้อมูล student_id ไม่ถูกต้องหรือไม่ได้ส่งมา";
    exit;
}

// ✅ จำเป็นต้องกำหนด $user_id ให้กับ calculate_review.php ใช้ได้
$user_id = $student_id;
 //  ⭐ ส่งค่าผ่าน session เพื่อให้ calculate_review ใช้ค่านี้
if (!$student_id) {
    header("Location: index.php"); // หรือ redirect ไปหน้าหลัก
    exit;
}

// ✅ ดึงข้อมูลรีวิวผ่าน include โดยไม่ต้องเรียกฟังก์ชัน
include 'calculate_review_student.php';

// ตอนนี้คุณสามารถใช้ตัวแปร $calculation ได้ทันที!

// ถ้าเป็น POST (อัปโหลดรูปโปรไฟล์ หรือ อัปเดตข้อมูลอื่นๆ)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // หากมีการส่งไฟล์รูปโปรไฟล์ (Profile Image Upload)
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'profile/'; // โฟลเดอร์เป้าหมาย
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); // สร้างโฟลเดอร์หากไม่มี
        }
        $fileTmpPath = $_FILES['profile_image']['tmp_name'];
        $fileName = basename($_FILES['profile_image']['name']);
        $fileNameNew = uniqid('profile_', true) . "_" . $fileName;
        $fileDest = $uploadDir . $fileNameNew;
        
        // ถ้าย้ายไฟล์สำเร็จ
        if (move_uploaded_file($fileTmpPath, $fileDest)) {
            $sqlUpdateProfile = "UPDATE student SET profile = ? WHERE student_id = ?";
            $stmtProfile = $conn->prepare($sqlUpdateProfile);
            $stmtProfile->bind_param("ss", $fileDest, $student_id);
            echo ($stmtProfile->execute()) ? "success" : "db_error";
            $stmtProfile->close();
        } else {
            echo "upload_failed";
        }
        // ปิดการเชื่อมต่อฐานข้อมูลหลังจากอัปโหลดเสร็จ
        $conn->close();
        exit();
    }

    // ส่วนอัปเดตข้อมูล skills และ hobbies
    $selectedSkills = isset($_POST['selectedSkills']) ? explode(',', $_POST['selectedSkills']) : [];
    $selectedHobbies = isset($_POST['selectedHobbies']) ? explode(',', $_POST['selectedHobbies']) : [];

    // อัปเดตข้อมูล skills และ hobbies
    $success = updateSkillsAndHobbies($conn, $student_id, $selectedSkills, $selectedHobbies);

    // ส่งผลลัพธ์กลับไปยัง JavaScript
    echo json_encode(["success" => $success]);
    exit();
}

// ฟังก์ชันอัปเดต skills และ hobbies
function updateSkillsAndHobbies($conn, $student_id, $selectedSkills, $selectedHobbies)
{
    // เริ่มต้นการทำงานกับฐานข้อมูล
    $conn->begin_transaction();

    try {
        // 1. ลบข้อมูล skills เดิมที่เกี่ยวข้องกับนิสิต
        $delete_skills_sql = "DELETE FROM student_skill WHERE student_id = ?";
        $stmt = $conn->prepare($delete_skills_sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();

        // 2. อัปเดต skills ที่เลือกใหม่
        foreach ($selectedSkills as $skillData) {
            list($skill_id, $subskill_id) = explode("-", $skillData);
            $insert_skill_sql = "INSERT INTO student_skill (student_id, skill_id, subskill_id) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_skill_sql);
            $stmt->bind_param("sii", $student_id, $skill_id, $subskill_id);
            $stmt->execute();
        }

        // 3. ลบข้อมูล hobbies เดิมที่เกี่ยวข้องกับนิสิต
        $delete_hobbies_sql = "DELETE FROM student_hobby WHERE student_id = ?";
        $stmt = $conn->prepare($delete_hobbies_sql);
        $stmt->bind_param("s", $student_id);
        $stmt->execute();

        // 4. อัปเดต hobbies ที่เลือกใหม่
        foreach ($selectedHobbies as $hobbyData) {
            list($hobby_id, $subhobby_id) = explode("-", $hobbyData);
            $insert_hobby_sql = "INSERT INTO student_hobby (student_id, hobby_id, subhobby_id) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_hobby_sql);
            $stmt->bind_param("sii", $student_id, $hobby_id, $subhobby_id);
            $stmt->execute();
        }

        // หากไม่มีข้อผิดพลาด ให้ทำการ commit ข้อมูล
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาดใด ๆ ให้ทำการ rollback ข้อมูล
        $conn->rollback();
        return false;
    }
}


// ดึงข้อมูลนักศึกษา
$sql = "
SELECT s.student_id, s.profile, s.stu_name, s.stu_email, s.major_id, s.year, 
       m.major_name
FROM student s
JOIN major m ON s.major_id = m.major_id
WHERE s.student_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $student = $result->fetch_assoc();
} else {
    echo "ไม่พบข้อมูลนักศึกษาที่ตรงกับ ID";
}

/* --- ดึงรายการ Skill พร้อม Subskill --- */
$all_skills = [];
$sql_skills = "SELECT s.skill_id, s.skill_name, ss.subskill_id, ss.subskill_name 
               FROM skill s 
               LEFT JOIN subskill ss ON s.skill_id = ss.skill_id 
               ORDER BY s.skill_name ASC, ss.subskill_name ASC";
$result_skills = $conn->query($sql_skills);
if ($result_skills) {
    while ($row = $result_skills->fetch_assoc()) {
        $sid = $row['skill_id'];
        if (!isset($all_skills[$sid])) {
            $all_skills[$sid] = [
                'skill_id'   => $row['skill_id'],
                'skill_name' => $row['skill_name'],
                'subskills'  => []
            ];
        }
        if (!empty($row['subskill_id'])) {
            $all_skills[$sid]['subskills'][] = [
                'subskill_id'   => $row['subskill_id'],
                'subskill_name' => $row['subskill_name']
            ];
        }
    }
    // Re-index as a simple array
    $all_skills = array_values($all_skills);
}

/* --- ดึงรายการ Hobby พร้อม Subhobby --- */
$all_hobbies = [];
$sql_hobbies = "SELECT h.hobby_id, h.hobby_name, sh.subhobby_id, sh.subhobby_name
                FROM hobby h 
                LEFT JOIN subhobby sh ON h.hobby_id = sh.hobby_id
                ORDER BY h.hobby_name ASC, sh.subhobby_name ASC";
$result_hobbies = $conn->query($sql_hobbies);
if ($result_hobbies) {
    while ($row = $result_hobbies->fetch_assoc()) {
        $hid = $row['hobby_id'];
        if (!isset($all_hobbies[$hid])) {
            $all_hobbies[$hid] = [
                'hobby_id'   => $row['hobby_id'],
                'hobby_name' => $row['hobby_name'],
                'subhobbies' => []
            ];
        }
        if (!empty($row['subhobby_id'])) {
            $all_hobbies[$hid]['subhobbies'][] = [
                'subhobby_id'   => $row['subhobby_id'],
                'subhobby_name' => $row['subhobby_name']
            ];
        }
    }
    $all_hobbies = array_values($all_hobbies);
}

/* --- ดึงข้อมูล Skill ที่นิสิตเลือก --- */
$student_skills = [];
$sql_student_skill = "SELECT skill_id, subskill_id FROM student_skill WHERE student_id = ?";
$stmt_skill = $conn->prepare($sql_student_skill);
$stmt_skill->bind_param("s", $student_id);
$stmt_skill->execute();
$result_student_skill = $stmt_skill->get_result();
while ($row = $result_student_skill->fetch_assoc()) {
    $student_skills[$row['skill_id']][] = $row['subskill_id'];
}
$stmt_skill->close();

/* สร้างข้อความแสดงผล Skill (แสดงชื่อ skill กับ subskill ที่เลือก) */
$skills_display = [];
foreach ($all_skills as $skill) {
    if (isset($student_skills[$skill['skill_id']])) {
        $selected_subskills = [];
        foreach ($skill['subskills'] as $subskill) {
            if (in_array($subskill['subskill_id'], $student_skills[$skill['skill_id']])) {
                $selected_subskills[] = $subskill['subskill_name'];
            }
        }
        if (!empty($selected_subskills)) {
            $skills_display[] = $skill['skill_name'] . ": " . implode(", ", $selected_subskills);
        }
    }
}
// เปลี่ยนจาก " | " เป็น <br> เพื่อเว้นบรรทัด
$skills_list_display = implode("<br>", $skills_display);

/* --- ดึงข้อมูล Hobby ที่นิสิตเลือก --- */
$student_hobbies = [];
$sql_student_hobby = "SELECT hobby_id, subhobby_id FROM student_hobby WHERE student_id = ?";
$stmt_hobby = $conn->prepare($sql_student_hobby);
$stmt_hobby->bind_param("s", $student_id);
$stmt_hobby->execute();
$result_student_hobby = $stmt_hobby->get_result();
while ($row = $result_student_hobby->fetch_assoc()) {
    $student_hobbies[$row['hobby_id']][] = $row['subhobby_id'];
}
$stmt_hobby->close();


/* สร้างข้อความแสดงผล Hobby (แสดงชื่อ hobby กับ subhobby) */
$hobby_display = [];
foreach ($all_hobbies as $hobby) {
    if (isset($student_hobbies[$hobby['hobby_id']])) {
        $selected_subhobbies = [];
        foreach ($hobby['subhobbies'] as $subhobby) {
            if (in_array($subhobby['subhobby_id'], $student_hobbies[$hobby['hobby_id']])) {
                $selected_subhobbies[] = $subhobby['subhobby_name'];
            }
        }
        if (!empty($selected_subhobbies)) {
            $hobby_display[] = $hobby['hobby_name'] . ": " . implode(", ", $selected_subhobbies);
        }
    }
}
$hobby_list_display = implode("<br>", $hobby_display);


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/stupfstyle.css">
    <link rel="stylesheet" href="css/header-footerstyle.css">
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

    <!-- Profile Header & Review -->
    <div class="profile-container">
        <div class="header">
            <a href="javascript:history.back()"><i class="bi bi-chevron-left text-white h4"></i></a>
            <div class="profile">
                <img class="profile-pic" id="profile_picture" src="<?php echo htmlspecialchars($student['profile']); ?>"
                    alt="Profile Picture" style="cursor: default;">
                <input type="file" id="profile_image_input" style="display:none;" accept="image/*">
                <div class="detail-name">
                    <div class="name"><?php echo htmlspecialchars($student['stu_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="sub-title">สาขา
                        <?php echo htmlspecialchars($student['major_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
        </div>
        <div class="content">
            <div class="detail-head">
                <a href="review_student.php?student_id=<?php echo urlencode($student_id); ?>">
                    <div class="review">
                        <div class="rating bg-sumary"><?php echo number_format($calculation['avg_rating'], 1); ?>
                        </div>
                        <div class="review-detail">
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++) {
                                     echo ($i <= $calculation['avg_rating']) ? '★' : '☆';
                                } ?>
                            </div>
                            <small>from <?php echo $calculation['total_groups']; ?> people</small>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content Section -->
    <div class="container">
        <!-- Skills Section -->
        <h3>Skills</h3>
        <section class="skills">
            <!-- View Mode -->
            <p id="skills_text_display"><?php echo $skills_list_display; ?></p>
            <!-- Edit Mode: ช่องค้นหา -->
            <div id="skills_search_container" style="display:none; margin-bottom: 10px;">
                <input type="text" id="skills_search" placeholder="ค้นหา skills..." style="padding: 6px; width: 100%;">
            </div>

            <!-- Edit Mode: แสดงรายการแบบกลุ่ม -->

            <div id="skills_checkbox_list" style="display:none;">
                <?php foreach ($all_skills as $skill):
                    // กำหนดให้ main checkbox ถูกเลือกหากมี subskill ที่เลือกแล้ว
                    $mainSelected = isset($student_skills[$skill['skill_id']]) && count($student_skills[$skill['skill_id']]) > 0;
                ?>
                <div class="group-checkbox">
                    <label>
                        <input type="checkbox" class="main-skill" data-skill-id="<?php echo $skill['skill_id']; ?>"
                            <?php echo $mainSelected ? "checked" : ""; ?>>
                        <?php echo htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </label>
                    <div class="subskills" id="subskills-<?php echo $skill['skill_id']; ?>"
                        style="margin-left:20px; display: <?php echo $mainSelected ? 'block' : 'none'; ?>;">
                        <?php if (!empty($skill['subskills'])): ?>
                        <?php foreach ($skill['subskills'] as $subskill):
                                    $checkbox_value = $skill['skill_id'] . '-' . $subskill['subskill_id'];
                                    $isChecked = isset($student_skills[$skill['skill_id']]) && in_array($subskill['subskill_id'], $student_skills[$skill['skill_id']]);
                                ?>
                        <label>
                            <input type="checkbox" name="skills[]" value="<?php echo $checkbox_value; ?>"
                                <?php echo $isChecked ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($subskill['subskill_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </label><br>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <label>No subskills available</label>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </section>

        <!-- Hobby Section -->
        <h3>Hobby</h3>
        <section class="hobby">
            <!-- View Mode -->
            <p id="hobby_text_display"><?php echo $hobby_list_display; ?></p>
            <!-- Edit Mode: ช่องค้นหา -->
            <div id="hobby_search_container" style="display:none; margin-bottom: 10px;">
                <input type="text" id="hobby_search" placeholder="ค้นหา hobby..." style="padding: 6px; width: 100%;">
            </div>
            <!-- Edit Mode: แสดงรายการแบบกลุ่ม -->
            <div id="hobby_checkbox_list" style="display:none;">
                <?php foreach ($all_hobbies as $hobby):
                    $mainHobbySelected = isset($student_hobbies[$hobby['hobby_id']]) && count($student_hobbies[$hobby['hobby_id']]) > 0;
                ?>
                <div class="group-checkbox">
                    <label>
                        <input type="checkbox" class="main-hobby" data-hobby-id="<?php echo $hobby['hobby_id']; ?>"
                            <?php echo $mainHobbySelected ? "checked" : ""; ?>>
                        <?php echo htmlspecialchars($hobby['hobby_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </label>
                    <div class="subhobbies" id="subhobbies-<?php echo $hobby['hobby_id']; ?>"
                        style="margin-left:20px; display: <?php echo $mainHobbySelected ? 'block' : 'none'; ?>;">
                        <?php if (!empty($hobby['subhobbies'])): ?>
                        <?php foreach ($hobby['subhobbies'] as $subhobby):
                                    $checkbox_value = $hobby['hobby_id'] . '-' . $subhobby['subhobby_id'];
                                    $isChecked = isset($student_hobbies[$hobby['hobby_id']]) && in_array($subhobby['subhobby_id'], $student_hobbies[$hobby['hobby_id']]);
                                ?>
                        <label>
                            <input type="checkbox" name="hobby[]" value="<?php echo $checkbox_value; ?>"
                                <?php echo $isChecked ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($subhobby['subhobby_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </label><br>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <label>No subhobbies available</label>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </section>
    </div>
</body>

</html>
<?php $conn->close(); ?>