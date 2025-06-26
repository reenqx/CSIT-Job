<?php
session_start();
// เชื่อมต่อฐานข้อมูล
include 'database.php';
$user_id = $_SESSION['user_id'] ?? null;
// รวมไฟล์คำนวณรีวิว
include 'calculate_review.php';

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ถ้าเป็น POST (อัปโหลดรูปโปรไฟล์ หรือ อัปเดตข้อมูลอื่นๆ)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isProfileUpdated = false;
    $isSkillsUpdated = false;
    $isHobbiesUpdated = false;

    // ตรวจสอบว่ามีการอัปโหลดรูปภาพโปรไฟล์ใหม่หรือไม่
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        // อัปโหลดโปรไฟล์ใหม่
        $uploadDir = 'profile/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileTmpPath = $_FILES['profile_image']['tmp_name'];
        $fileName = basename($_FILES['profile_image']['name']);
        $fileNameNew = uniqid('profile_', true) . "_" . $fileName;
        $fileDest = $uploadDir . $fileNameNew;

        if (move_uploaded_file($fileTmpPath, $fileDest)) {
            // อัปเดตข้อมูลรูปโปรไฟล์ในฐานข้อมูล
            $sqlUpdateProfile = "UPDATE student SET profile = ? WHERE student_id = ?";
            $stmtProfile = $conn->prepare($sqlUpdateProfile);
            $stmtProfile->bind_param("ss", $fileDest, $user_id);
            if ($stmtProfile->execute()) {
                $isProfileUpdated = true;
            } else {
                echo json_encode(["success" => false, "message" => "Database error: " . $stmtProfile->error]);
                $stmtProfile->close();
                exit();
            }
            $stmtProfile->close();
        } else {
            echo json_encode(["success" => false, "message" => "Upload failed."]);
            exit();
        }
    }

    // ตรวจสอบและอัปเดตข้อมูล skills และ hobbies
    $selectedSkills = isset($_POST['selectedSkills']) ? explode(',', $_POST['selectedSkills']) : [];
    $selectedHobbies = isset($_POST['selectedHobbies']) ? explode(',', $_POST['selectedHobbies']) : [];

    if (!empty($selectedSkills) || !empty($selectedHobbies)) {
        // อัปเดต skills และ hobbies
        $success = updateSkillsAndHobbies($conn, $user_id, $selectedSkills, $selectedHobbies);
        if ($success) {
            $isSkillsUpdated = true;
            $isHobbiesUpdated = true;
        }
    }

    // อัปเดตข้อมูลทุกส่วนได้สำเร็จ
    if ($isProfileUpdated || $isSkillsUpdated || $isHobbiesUpdated) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "No data to update"]);
    }

    $conn->close();
    exit();
}

// ฟังก์ชันอัปเดต skills และ hobbies
function updateSkillsAndHobbies($conn, $user_id, $selectedSkills, $selectedHobbies)
{
    $conn->begin_transaction();

    try {
        // ลบข้อมูล skills เดิมที่เกี่ยวข้องกับนิสิต
        $delete_skills_sql = "DELETE FROM student_skill WHERE student_id = ?";
        $stmt = $conn->prepare($delete_skills_sql);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();

        // อัปเดต skills ใหม่
        foreach ($selectedSkills as $skillData) {
            list($skill_id, $subskill_id) = explode("-", $skillData);
            $insert_skill_sql = "INSERT INTO student_skill (student_id, skill_id, subskill_id) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_skill_sql);
            $stmt->bind_param("sii", $user_id, $skill_id, $subskill_id);
            $stmt->execute();
        }

        // ลบข้อมูล hobbies เดิมที่เกี่ยวข้องกับนิสิต
        $delete_hobbies_sql = "DELETE FROM student_hobby WHERE student_id = ?";
        $stmt = $conn->prepare($delete_hobbies_sql);
        $stmt->bind_param("s", $user_id);
        $stmt->execute();

        // อัปเดต hobbies ใหม่
        foreach ($selectedHobbies as $hobbyData) {
            list($hobby_id, $subhobby_id) = explode("-", $hobbyData);
            $insert_hobby_sql = "INSERT INTO student_hobby (student_id, hobby_id, subhobby_id) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_hobby_sql);
            $stmt->bind_param("sii", $user_id, $hobby_id, $subhobby_id);
            $stmt->execute();
        }

        // commit ข้อมูล
        $conn->commit();
        return true;
    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาดให้ rollback ข้อมูล
        $conn->rollback();
        return false;
    }
}

// ถ้ามีการส่งค่าผ่าน URL (GET) สำหรับอัปเดตสถานะแจ้งเตือน
if (isset($_GET['id'])) {
    $notification_id = intval($_GET['id']); // ป้องกัน SQL Injection
    // เปลี่ยนชื่อคอลัมน์จาก notifications_id เป็น notification_id ตามฐานข้อมูล
    $update_sql = "UPDATE notification SET status = 'read' WHERE notification_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $notification_id);
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "id" => $notification_id]);
    } else {
        echo json_encode(["success" => false, "error" => $conn->error]);
    }
    $stmt->close();
    $conn->close();
    exit();
}

// ฟังก์ชันดึงการแจ้งเตือน (ใช้ alias ให้ตรงกับฐานข้อมูล)
function getNotifications($conn, $user_id)
{
    $sql = "SELECT 
                notification.notification_id AS id, 
                notification.message, 
                notification.created_at AS time, 
                notification.status, 
                accepted_application.accept_status_id, 
                accept_status.accept_status_name
            FROM notification
            JOIN accepted_application ON notification.reference_id = accepted_application.accepted_application_id 
            JOIN accept_status ON accepted_application.accept_status_id = accept_status.accept_status_id
            WHERE notification.user_id = ? 
            ORDER BY notification.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'title' => $row['accept_status_name'],
            'message' => $row['message'],
            'time' => $row['time'],
            'status' => strtolower($row['status']),
            'accept_status_id' => $row['accept_status_id'] ?? null
        ];
    }
    $stmt->close();
    return $notifications;
}
$notifications = getNotifications($conn, $user_id);

// ดึงข้อมูลนักศึกษา
$sql = "
SELECT s.student_id, s.profile, s.stu_name, s.stu_email, s.major_id, s.year, 
       m.major_name
FROM student s
JOIN major m ON s.major_id = m.major_id
WHERE s.student_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
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
$stmt_skill->bind_param("s", $user_id);
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
$stmt_hobby->bind_param("s", $user_id);
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
    <!-- CSS & Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/stupfstyle.css">
    <link rel="stylesheet" href="css/header-footer.html">
    <!-- JSON data for notifications -->
    <script type="application/json" id="notifications-data">
        <?php echo json_encode($notifications); ?>
    </script>
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
                <img class="profile-pic" id="profile_picture" src="<?php echo htmlspecialchars($student['profile']); ?>" alt="Profile Picture" style="cursor: default;">
                <input type="file" id="profile_image_input" style="display:none;" accept="image/*">
                <div class="detail-name">
                    <div class="name"><?php echo htmlspecialchars($student['stu_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="sub-title">สาขา <?php echo htmlspecialchars($student['major_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
        </div>
        <div class="content">
            <div class="detail-head">
                <a href="review.php?user_id=<?php echo urlencode($user_id); ?>">
                    <div class="review">
                        <div class="rating bg-sumary"><?php echo number_format($calculation['avg_rating'], 1); ?></div>
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
                <div>
                    <button class="notification-btn">
                        <i class="bi bi-bell"></i>
                        <span class="notification-badge" <?php echo ($unread_count == 0) ? 'style="display:none;"' : ''; ?>>
                            <?php echo $unread_count; ?>
                        </span>
                        <button class="edit-button" onclick="toggleEdit()">Edit</button>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Notifications Card -->
    <div class="notifications-card" id="notifications">
        <div class="headerNoti">
            <h1 class="page-title">Notifications</h1>
            <span class="notification-count" <?php echo ($unread_count == 0) ? 'style="display:none;"' : ''; ?>>
                <?php echo $unread_count; ?> new
            </span>
            <button class="close-button" id="close-notifications">&times;</button>
        </div>
        <div class="tabs">
            <div class="tab active" data-filter="all">All</div>
            <div class="tab" data-filter="unread">Unread</div>
            <div class="tab" data-filter="accepted">Accepted</div>
            <div class="tab" data-filter="reject">Rejected</div>
        </div>
        <div class="notification-list" id="notification-list">
            <?php foreach ($notifications as $notification) { ?>
                <a href="viewnoti.php?id=<?php echo $notification['id']; ?>" class="notification-item" data-status="<?php echo $notification['status']; ?>">
                    <div class="notification-content">
                        <h3 class="notification-title"><?php echo $notification['title']; ?></h3>
                        <p class="notification-message"><?php echo $notification['message']; ?></p>
                        <span class="notification-time"><?php echo $notification['time']; ?></span>
                    </div>
                </a>
            <?php } ?>
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
                            <input type="checkbox" class="main-skill" data-skill-id="<?php echo $skill['skill_id']; ?>" <?php echo $mainSelected ? "checked" : ""; ?>>
                            <?php echo htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                        <div class="subskills" id="subskills-<?php echo $skill['skill_id']; ?>" style="margin-left:20px; display: <?php echo $mainSelected ? 'block' : 'none'; ?>;">
                            <?php if (!empty($skill['subskills'])): ?>
                                <?php foreach ($skill['subskills'] as $subskill):
                                    $checkbox_value = $skill['skill_id'] . '-' . $subskill['subskill_id'];
                                    $isChecked = isset($student_skills[$skill['skill_id']]) && in_array($subskill['subskill_id'], $student_skills[$skill['skill_id']]);
                                ?>
                                    <label>
                                        <input type="checkbox" name="skills[]" value="<?php echo $checkbox_value; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
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
                            <input type="checkbox" class="main-hobby" data-hobby-id="<?php echo $hobby['hobby_id']; ?>" <?php echo $mainHobbySelected ? "checked" : ""; ?>>
                            <?php echo htmlspecialchars($hobby['hobby_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                        <div class="subhobbies" id="subhobbies-<?php echo $hobby['hobby_id']; ?>" style="margin-left:20px; display: <?php echo $mainHobbySelected ? 'block' : 'none'; ?>;">
                            <?php if (!empty($hobby['subhobbies'])): ?>
                                <?php foreach ($hobby['subhobbies'] as $subhobby):
                                    $checkbox_value = $hobby['hobby_id'] . '-' . $subhobby['subhobby_id'];
                                    $isChecked = isset($student_hobbies[$hobby['hobby_id']]) && in_array($subhobby['subhobby_id'], $student_hobbies[$hobby['hobby_id']]);
                                ?>
                                    <label>
                                        <input type="checkbox" name="hobby[]" value="<?php echo $checkbox_value; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
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


        <button class="save-button" style="display:none;" onclick="saveChanges()">Save</button>

    </div>

    <footer class="footer">
        <p>© CSIT - Computer Science and Information Technology</p>
    </footer>

    <!-- JavaScript สำหรับ Notifications, Edit, และ Save -->
    <script>
        // Notifications & Filtering (เหมือนเดิม)
        document.addEventListener("DOMContentLoaded", function() {
            const tabs = document.querySelectorAll(".tab");
            const notificationList = document.getElementById("notification-list");
            const notificationBadge = document.querySelector(".notification-badge");
            const notificationCount = document.querySelector(".notification-count");

            function fetchNotifications(filterType) {
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        let parser = new DOMParser();
                        let doc = parser.parseFromString(html, "text/html");
                        let notifications = JSON.parse(doc.getElementById("notifications-data").textContent);
                        updateNotifications(notifications, filterType);
                    })
                    .catch(error => console.error("Error fetching notifications:", error));
            }

            function updateNotifications(notifications, filterType) {
                notificationList.innerHTML = "";
                let unreadCount = 0;
                notifications.forEach((notification) => {
                    if (filterType === "all" ||
                        (filterType === "unread" && notification.status === "unread") ||
                        (filterType === "accepted" && notification.title === "Accepted") ||
                        (filterType === "reject" && notification.title === "Rejected")) {
                        const notificationItem = document.createElement("div");
                        notificationItem.classList.add("notification-item", notification.status);
                        notificationItem.setAttribute("data-status", notification.status);
                        notificationItem.setAttribute("data-id", notification.id);
                        notificationItem.innerHTML = `
                    <div class="notification-content">
                        <h3 class="notification-title">${notification.title}</h3>
                        <p class="notification-message">${notification.message}</p>
                        <span class="notification-time">${notification.time}</span>
                    </div>
                `;
                        notificationItem.addEventListener("click", function(e) {
                            e.preventDefault();
                            markAsRead(notification.id);
                            setTimeout(function() {
                                window.location.href = "viewnoti.php?id=" + notification.id;
                            }, 100);
                        });
                        if (notification.status === "unread") {
                            unreadCount++;
                        }
                        notificationList.appendChild(notificationItem);
                    }
                });
                if (unreadCount > 0) {
                    notificationBadge.innerText = unreadCount;
                    notificationBadge.style.display = "inline-block";
                    notificationCount.innerText = `${unreadCount} new`;
                    notificationCount.style.display = "inline-block";
                } else {
                    notificationBadge.style.display = "none";
                    notificationCount.style.display = "none";
                }
            }

            function markAsRead(notificationId) {
                fetch(`?id=${notificationId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log("Notification marked as read:", notificationId);
                            let notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                            if (notificationItem) {
                                let activeTab = document.querySelector(".tab.active").getAttribute("data-filter");
                                if (activeTab === "unread") {
                                    notificationItem.remove();
                                } else {
                                    notificationItem.dataset.status = "read";
                                    notificationItem.classList.remove("unread");
                                    notificationItem.classList.add("read");
                                }
                            }
                            let currentCount = parseInt(notificationBadge.innerText) || 0;
                            if (currentCount > 0) {
                                currentCount--;
                                notificationBadge.innerText = currentCount;
                                notificationCount.innerText = `${currentCount} new`;
                                if (currentCount === 0) {
                                    notificationBadge.style.display = "none";
                                    notificationCount.style.display = "none";
                                }
                            }
                        }
                    })
                    .catch(error => console.error("Error updating notification:", error));
            }

            tabs.forEach(tab => {
                tab.addEventListener("click", function() {
                    tabs.forEach(t => t.classList.remove("active"));
                    this.classList.add("active");
                    const filterType = this.getAttribute("data-filter");
                    fetchNotifications(filterType);
                });
            });
            fetchNotifications("all");
        });

        const notificationButton = document.querySelector('.notification-btn');
        const notificationsCard = document.getElementById('notifications');
        const closeButton = document.getElementById('close-notifications');
        notificationButton.addEventListener('click', () => {
            notificationsCard.style.display = 'block';
        });
        closeButton.addEventListener('click', () => {
            notificationsCard.style.display = 'none';
        });
        document.addEventListener('click', (event) => {
            if (!notificationsCard.contains(event.target) && !notificationButton.contains(event.target)) {
                notificationsCard.style.display = 'none';
            }
        });

        // Toggle Edit Mode สำหรับ Skills และ Hobby
        function toggleEdit() {
            const skillsDisplay = document.getElementById('skills_text_display');
            const skillsEdit = document.getElementById('skills_checkbox_list');
            const skillsSearchContainer = document.getElementById('skills_search_container');

            const hobbyDisplay = document.getElementById('hobby_text_display');
            const hobbyEdit = document.getElementById('hobby_checkbox_list');
            const hobbySearchContainer = document.getElementById('hobby_search_container');

            // Toggle skills
            if (skillsDisplay.style.display !== "none") {
                skillsDisplay.style.display = "none";
                skillsEdit.style.display = "block";
                skillsSearchContainer.style.display = "block";
            } else {
                skillsDisplay.style.display = "block";
                skillsEdit.style.display = "none";
                skillsSearchContainer.style.display = "none";
            }

            // Toggle hobby
            if (hobbyDisplay.style.display !== "none") {
                hobbyDisplay.style.display = "none";
                hobbyEdit.style.display = "block";
                hobbySearchContainer.style.display = "block";
            } else {
                hobbyDisplay.style.display = "block";
                hobbyEdit.style.display = "none";
                hobbySearchContainer.style.display = "none";
            }

            // แสดงปุ่ม Save เมื่ออยู่ในโหมดแก้ไข
            const saveButton = document.querySelector('.save-button');
            let isEditMode = (skillsEdit.style.display === "block") || (hobbyEdit.style.display === "block");
            saveButton.style.display = isEditMode ? "inline-block" : "none";

            // เปิดหรือปิดการแก้ไขรูปโปรไฟล์ตามโหมด Edit
            const profilePic = document.getElementById('profile_picture');
            if (isEditMode) {
                profilePic.style.cursor = "pointer";
                profilePic.addEventListener("click", profilePicClickHandler);
            } else {
                profilePic.style.cursor = "default";
                profilePic.removeEventListener("click", profilePicClickHandler);
            }
        }

        // ฟังก์ชัน updateSkillsAndHobbies สำหรับอัปเดตข้อมูล skills/hobbies ไปยัง update_profile.php
        function updateSkillsAndHobbies() {
            let skillsCheckboxes = document.querySelectorAll('input[name="skills[]"]:checked');
            let selectedSkills = Array.from(skillsCheckboxes).map(cb => cb.value).join(',');
            let hobbyCheckboxes = document.querySelectorAll('input[name="hobby[]"]:checked');
            let selectedHobbies = Array.from(hobbyCheckboxes).map(cb => cb.value).join(',');
            let xhr = new XMLHttpRequest();
            xhr.open("POST", "update_profile.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    let skillsTextDisplay = Array.from(skillsCheckboxes)
                        .map(cb => cb.parentElement.textContent.trim())
                        .join(', ');
                    document.getElementById('skills_text_display').innerHTML = skillsTextDisplay;
                    let hobbyTextDisplay = Array.from(hobbyCheckboxes)
                        .map(cb => cb.parentElement.textContent.trim())
                        .join(', ');
                    document.getElementById('hobby_text_display').innerHTML = hobbyTextDisplay;
                    toggleEdit();
                    // รีเซ็ตตัวแปรไฟล์ใหม่
                    newProfileImageFile = null;
                }
            };
            xhr.send("skills_text=" + encodeURIComponent(selectedSkills) +
                "&hobby_text=" + encodeURIComponent(selectedHobbies));
        }

        // ฟังก์ชัน saveChanges จะเช็คว่ามีการเลือกไฟล์รูปใหม่หรือไม่
        function saveChanges() {
            let skillsCheckboxes = document.querySelectorAll('input[name="skills[]"]:checked');
            let selectedSkills = Array.from(skillsCheckboxes).map(cb => cb.value);

            let hobbyCheckboxes = document.querySelectorAll('input[name="hobby[]"]:checked');
            let selectedHobbies = Array.from(hobbyCheckboxes).map(cb => cb.value);

            let formData = new FormData();
            formData.append('selectedSkills', selectedSkills.join(','));
            formData.append('selectedHobbies', selectedHobbies.join(','));

            // ตรวจสอบว่าได้เลือกไฟล์รูปภาพใหม่หรือไม่
            let profileImageInput = document.getElementById('profile_image_input');
            if (profileImageInput.files.length > 0) {
                let profileImageFile = profileImageInput.files[0];
                formData.append('profile_image', profileImageFile);
            }

            fetch('stuf.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // รีเฟรชหน้าเมื่อข้อมูลอัปเดตสำเร็จ
                        window.location.reload();
                    } else {
                        alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' + (data.message || 'ไม่ทราบเหตุผล'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('เกิดข้อผิดพลาดในการบันทึกข้อมูล');
                });
        }




        // Event listener สำหรับค้นหาใน Skills (Real-time + Reordering)
        document.getElementById('skills_search').addEventListener('keyup', function() {
            var filter = this.value.toLowerCase().trim();
            // ดึงกลุ่มทั้งหมดใน skills_checkbox_list เป็น array
            var groups = Array.from(document.querySelectorAll('#skills_checkbox_list .group-checkbox'));

            // ถ้าไม่มีการพิมพ์ข้อความ ให้แสดงกลุ่มทั้งหมดตามลำดับเดิม
            if (filter === "") {
                groups.forEach(function(group) {
                    group.style.display = "";
                });
                return;
            }

            // สำหรับแต่ละกลุ่ม ให้ตรวจสอบว่าหมวดหมู่หลัก (category) มีคำค้นหาหรือไม่
            groups.forEach(function(group) {
                var mainLabel = group.querySelector("label").innerText.toLowerCase();
                if (mainLabel.indexOf(filter) > -1) {
                    group.style.display = "";
                    var subContainer = group.querySelector('.subskills');
                    if (subContainer) {
                        subContainer.querySelectorAll('label').forEach(function(label) {
                            label.style.display = "";
                        });
                    }
                } else {
                    var subContainer = group.querySelector('.subskills');
                    var labels = Array.from(subContainer.querySelectorAll('label'));
                    var anyMatch = false;
                    labels.forEach(function(label) {
                        if (label.textContent.toLowerCase().indexOf(filter) > -1) {
                            label.style.display = "";
                            anyMatch = true;
                        } else {
                            label.style.display = "none";
                        }
                    });
                    group.style.display = anyMatch ? "" : "none";
                }
            });

            // Reorder groups: ให้กลุ่มที่มี category header ตรงกับ filter (lower index) ขึ้นมาก่อน
            groups.sort(function(a, b) {
                var aMain = a.querySelector("label").innerText.toLowerCase();
                var bMain = b.querySelector("label").innerText.toLowerCase();
                var posA = aMain.indexOf(filter);
                var posB = bMain.indexOf(filter);
                if (posA === -1) posA = Infinity;
                if (posB === -1) posB = Infinity;
                return posA - posB;
            });
            var container = document.getElementById('skills_checkbox_list');
            groups.forEach(function(group) {
                container.appendChild(group);
            });
        });

        document.getElementById('hobby_search').addEventListener('keyup', function() {
            var filter = this.value.toLowerCase().trim();
            var groups = Array.from(document.querySelectorAll('#hobby_checkbox_list .group-checkbox'));
            if (filter === "") {
                groups.forEach(function(group) {
                    group.style.display = "";
                });
                return;
            }
            groups.forEach(function(group) {
                var mainLabel = group.querySelector("label").innerText.toLowerCase();
                if (mainLabel.indexOf(filter) > -1) {
                    group.style.display = "";
                    var subContainer = group.querySelector('.subhobbies');
                    if (subContainer) {
                        subContainer.querySelectorAll('label').forEach(function(label) {
                            label.style.display = "";
                        });
                    }
                } else {
                    var subContainer = group.querySelector('.subhobbies');
                    var labels = Array.from(subContainer.querySelectorAll('label'));
                    var anyMatch = false;
                    labels.forEach(function(label) {
                        if (label.textContent.toLowerCase().indexOf(filter) > -1) {
                            label.style.display = "";
                            anyMatch = true;
                        } else {
                            label.style.display = "none";
                        }
                    });
                    group.style.display = anyMatch ? "" : "none";
                }
            });
            groups.sort(function(a, b) {
                var aMain = a.querySelector("label").innerText.toLowerCase();
                var bMain = b.querySelector("label").innerText.toLowerCase();
                var posA = aMain.indexOf(filter);
                var posB = bMain.indexOf(filter);
                if (posA === -1) posA = Infinity;
                if (posB === -1) posB = Infinity;
                return posA - posB;
            });
            var container = document.getElementById('hobby_checkbox_list');
            groups.forEach(function(group) {
                container.appendChild(group);
            });
        });

        // Toggle display ของ subskills เมื่อคลิก main skill
        document.querySelectorAll('.main-skill').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const skillId = this.getAttribute('data-skill-id');
                const subskillsContainer = document.getElementById('subskills-' + skillId);
                if (this.checked) {
                    subskillsContainer.style.display = 'block';
                } else {
                    subskillsContainer.style.display = 'none';
                    subskillsContainer.querySelectorAll('input[type="checkbox"]').forEach(function(subCheckbox) {
                        subCheckbox.checked = false;
                    });
                }
            });
        });

        // Toggle display ของ subhobbies เมื่อคลิก main hobby
        document.querySelectorAll('.main-hobby').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const hobbyId = this.getAttribute('data-hobby-id');
                const subhobbiesContainer = document.getElementById('subhobbies-' + hobbyId);
                if (this.checked) {
                    subhobbiesContainer.style.display = 'block';
                } else {
                    subhobbiesContainer.style.display = 'none';
                    subhobbiesContainer.querySelectorAll('input[type="checkbox"]').forEach(function(subCheckbox) {
                        subCheckbox.checked = false;
                    });
                }
            });
        });
    </script>
    <script>
        // ตัวแปรเก็บไฟล์รูปโปรไฟล์ใหม่ (ถ้ามี)
        let newProfileImageFile = null;

        document.getElementById('profile_image_input').addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            newProfileImageFile = file;
            // แสดงตัวอย่างรูปโปรไฟล์ที่เลือก
            const img = document.getElementById('profile_picture');
            img.src = URL.createObjectURL(file);
        });

        function profilePicClickHandler() {
            document.getElementById('profile_image_input').click();
        }
    </script>
</body>

</html>
<?php $conn->close(); ?>