<?php
session_start();
include 'database.php';
$user_id = $_SESSION['user_id'] ?? 0;

// ตรวจสอบว่ามีการส่ง POST หรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. หากมีการส่งไฟล์รูป (Profile Image Upload)
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'profile/'; // โฟลเดอร์เป้าหมาย
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $_FILES['profile_image']['tmp_name'];
        $fileName = basename($_FILES['profile_image']['name']);
        $fileNameNew = uniqid('profile_', true) . "_" . $fileName;
        $fileDest = $uploadDir . $fileNameNew;

        if (move_uploaded_file($fileTmpPath, $fileDest)) {
            $sqlUpdateProfile = "UPDATE teacher SET profile = ? WHERE teacher_id = ?";
            $stmtProfile = $conn->prepare($sqlUpdateProfile);
            $stmtProfile->bind_param("ss", $fileDest, $user_id);
            if ($stmtProfile->execute()) {
                echo "success";
            } else {
                echo "db_error";
            }
            $stmtProfile->close();
        } else {
            echo "upload_failed";
        }
        $conn->close();
        exit();
    }
    // 2. หากไม่มีการส่งไฟล์ ให้ถือว่าเป็นการอัปเดตข้อมูลติดต่อ (Contact Update)
    else {
        $phone_number = $_POST['phone_number'] ?? '';
        $email = $_POST['email'] ?? '';
        $sqlTeacher = "UPDATE teacher SET teach_phone_number = ?, teach_email = ? WHERE teacher_id = ?";
        $stmtT = $conn->prepare($sqlTeacher);
        $stmtT->bind_param("sss", $phone_number, $email, $user_id);
        if (!$stmtT->execute()) {
            echo "error_teachers";
            exit();
        }
        $stmtT->close();
        $conn->close();
        echo "success";
        exit();
    }
}

// ตรวจสอบการอัปเดตสถานะการแจ้งเตือน (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['notification_id'])) {
    $notification_id = intval($_GET['notification_id']); // ป้องกัน SQL Injection
    if ($notification_id > 0 && $user_id > 0) {
        $sql = "UPDATE notification SET status = 'read' WHERE notification_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $notification_id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Notification updated"]);
        } else {
            echo json_encode(["success" => false, "error" => "Database update failed"]);
        }
        $stmt->close();
    } else {
        echo json_encode(["success" => false, "error" => "Invalid ID or user"]);
    }
    exit();
}

// --- ส่วนของการดึงข้อมูลแจ้งเตือน, ข้อมูลอาจารย์ (Contact) และ Job ของอาจารย์ --- //

// ดึงข้อมูลแจ้งเตือน
// ดึงข้อมูลแจ้งเตือน
$sql = "SELECT 
            notification.notification_id AS notification_id, 
            notification.message, 
            notification.created_at, 
            notification.status, 
            CASE 
                WHEN notification.reference_table = 'job_application' THEN job_post.title
                WHEN notification.reference_table = 'report' THEN report_post.title
                ELSE ''
            END AS title,
            job_application.job_application_id, 
            report.report_id, 
            notification.reference_id,  
            notification.reference_table  
        FROM notification
        LEFT JOIN job_application 
            ON notification.reference_id = job_application.job_application_id
        LEFT JOIN post_job AS job_post 
            ON job_application.post_job_id = job_post.post_job_id
        LEFT JOIN report 
            ON notification.reference_id = report.report_id
        LEFT JOIN post_job AS report_post 
            ON report.post_job_id = report_post.post_job_id
        WHERE notification.user_id = ?
        ORDER BY notification.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['notification_id'],
        'title' => $row['title'], // แก้จาก $row['job_title'] เป็น $row['title']
        'message' => $row['message'],
        'time' => $row['created_at'],
        'job_app_id' => $row['job_application_id'],
        'report_id' => $row['report_id'],
        'status' => strtolower($row['status']),
        'reference_id' => $row['reference_id'],
        'reference_table' => $row['reference_table']
    ];
}
$stmt->close();


// ดึงจำนวนแจ้งเตือนที่ยังไม่ได้อ่าน
$sql = "SELECT COUNT(*) AS unread_count FROM notification WHERE status = 'unread' AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$unread_count = $row ? $row['unread_count'] : 0;
$stmt->close();

// ตรวจสอบการร้องขอข้อมูลแจ้งเตือนแบบ AJAX
if (isset($_GET['fetch_notifications'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);
    exit();
}


// ดึงข้อมูลอาจารย์ (Contact)
$sqlTeacher = "SELECT 
                  t.teacher_id,
                  t.teach_name,
                  t.teach_email,
                  t.profile,
                  t.major_id,
                  t.teach_phone_number,
                  m.major_id,
                  m.major_name
               FROM teacher t
               JOIN major m ON t.major_id = m.major_id
               WHERE t.teacher_id = ?";
$stmtT = $conn->prepare($sqlTeacher);
$stmtT->bind_param("s", $user_id);
$stmtT->execute();
$resT = $stmtT->get_result();
$teacher = $resT->fetch_assoc();
$stmtT->close();

// ดึง job ของอาจารย์
$sqlJobs = "SELECT * FROM post_job WHERE teacher_id = ? ORDER BY created_at DESC";
$stmtJ = $conn->prepare($sqlJobs);
$stmtJ->bind_param("s", $user_id);
$stmtJ->execute();
$resJobs = $stmtJ->get_result();
$jobs = [];
while ($rowJob = $resJobs->fetch_assoc()) {
    $jobs[] = $rowJob;
}
$stmtJ->close();

$sqlstatus = "SELECT * FROM job_status";  // ตรวจสอบชื่อตารางให้ถูกต้อง
$stmtS = $conn->prepare($sqlstatus);
if (!$stmtS) {
    die("Prepare failed: " . $conn->error);
}

$stmtS->execute();
$resultStatus = $stmtS->get_result();



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Teacher Profile</title>
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">
    <!-- CSS ของคุณ -->
    <link rel="stylesheet" href="css/header-footerstyle.css">
    <link rel="stylesheet" href="css/teacherprofilestyle.css">
    <style>
        .container-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            justify-items: start;
        }

        .card {
            background-color: #FFFFFF;
            padding: 10px;
            text-align: left;
            border: 1px solid #D1D5DB;
            border-radius: 16px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            width: 100%;
            /* ให้ card ใช้พื้นที่เต็มคอลัมน์ที่ได้ */
            max-width: 300px;
            /* กำหนดความกว้างสูงสุดให้เท่ากัน */
        }

        .card-top {
            background-color: #E5E7EB;
            height: 200px;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            /* ป้องกันเนื้อหาล้นออกมา */
        }

        .job-filter {
            margin: 20px 0;
            text-align: center;
        }

        .job-filter .filter-btn {
            margin: 0 5px;
            padding: 10px 20px;
            background-color: #f1f1f1;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .job-filter .filter-btn:hover {
            background-color: #ddd;
        }

        .job-filter .filter-btn.active {
            background-color: #FF7C00;
            color: #fff;
        }

        /* จุดแดงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงง */
        .unread-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            background-color: red;
            border-radius: 50%;
            margin-left: 10px;
        }
    </style>
</head>

<body>
    <div class="profile-container">
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

        <!-- Profile Header -->
        <div class="header">
            <a href="hometest.php"><i class="bi bi-chevron-left text-white h4"></i></a>
            <div class="profile">
                <img class="profile-pic"
                    id="profile_picture"
                    src="<?php echo htmlspecialchars($teacher['profile']); ?>"
                    alt="Profile Picture"
                    style="cursor: pointer;"
                    onclick="handleProfileClick();">

                <!-- input file แบบซ่อน -->
                <input type="file" id="profile_image_input" style="display:none;" accept="image/*">
                <div class="detail-name">
                    <div class="name"><?php echo $teacher['teach_name']; ?></div>
                    <div class="sub-title">
                        อาจารย์ภาควิชา <br>
                        <?php echo htmlspecialchars($teacher['major_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Profile Header -->

        <!-- Content -->
        <div class="content">
            <div class="detail-head">
                <div class="review">
                    <div class="review-detail">
                        <!-- คะแนน/รีวิว -->
                    </div>
                </div>
                <div>
                    <!-- Notification Button -->
                    <button class="notification-btn">
                        <i class="bi bi-bell"></i>
                        <span class="notification-badge" <?php echo ($unread_count == 0) ? 'style="display:none;"' : ''; ?>>
                            <?php echo $unread_count; ?>
                        </span>
                    </button>
                    <!-- Notifications Card -->
                    <div class="notifications-card" id="notifications">
                        <div class="headerNoti">
                            <h1 class="page-title">Notifications</h1>
                            <span class="notification-count" <?php echo ($unread_count == 0) ? 'style="display:none;"' : ''; ?>>
                                <?php echo $unread_count; ?> new
                            </span>
                            <button class="close-button" id="close-notifications">&times;</button>
                        </div>
                        <!-- Tabs -->
                        <div class="tabs">
                            <div class="tab active" data-filter="all">All</div>
                            <div class="tab" data-filter="unread">Unread</div>
                        </div>
                        <!-- Notification List -->
                        <div class="notification-list" id="notification-list">
                            <?php foreach ($notifications as $notification) {
                                $link = "#";
                                if ($notification['reference_table'] == 'job_application') {
                                    $link = "viewapply2.php?job_application_id=" . $notification['job_app_id'];
                                } else {
                                    $link = "viewnoti_reports.php?report_id=" . $notification['report_id'] . "&notification_id=" . $notification['id'];
                                }
                            ?>
                                <a href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>"
                                    class="notification-item"
                                    data-id="<?php echo $notification['id']; ?>"
                                    data-status="<?php echo $notification['status']; ?>">
                                    <div class="notification-content">
                                        <h3 class="notification-title">
                                            <?php echo htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8'); ?>
                                        </h3>
                                        <p class="notification-message">
                                            <?php echo htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <span class="notification-time">
                                            <?php echo htmlspecialchars($notification['time'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                    <!--จุดแดงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงงง -->
                                    <?php if ($notification['status'] === 'unread') { ?>
                                        <span class="unread-dot"></span>
                                    <?php } ?>

                                </a>
                            <?php } ?>
                        </div>

                    </div>
                    <!-- Add Job -->
                    <a href="jobpost2.php">
                        <button class="addJob-button">Add Job</button>
                    </a>
                    <!-- Edit Button -->
                    <button class="edit-button" onclick="toggleEdit()">Edit</button>
                </div>
            </div>
        </div>

        <!-- Contact Section -->
        <div class="container-content">
            <div class="container">
                <h3>Contact</h3>
                <section class="Contact">
                    <!-- Display Mode -->
                    <div id="contact_display">
                        <p>เบอร์โทร : <?php echo htmlspecialchars($teacher['teach_phone_number'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p>อีเมล : <?php echo htmlspecialchars($teacher['teach_email'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <!-- Edit Mode -->
                    <div id="contact_edit" style="display:none;">
                        <label for="phone_number_input">เบอร์โทร :</label>
                        <input type="text" id="phone_number_input" value="<?php echo htmlspecialchars($teacher['teach_phone_number'], ENT_QUOTES, 'UTF-8'); ?>">
                        <br><br>
                        <label for="email_input">อีเมล :</label>
                        <input type="email" id="email_input" value="<?php echo htmlspecialchars($teacher['teach_email'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </section>
            </div>
            <!-- Save Button -->
            <div class="container">
                <button class="save-button" style="display:none;" onclick="saveChanges()">Save</button>
            </div>
            <!-- Job Section -->
            <div class="container">
                <div class="menu-review">
                    <h3>Job</h3>
                    <a href="reviewst.php?teacher_id=<?php echo urlencode($teacher['teacher_id']); ?>" class="btn-review">review</a>
                </div>
                <div class="content">
                    <!-- ตัวกรองงาน -->
                    <div class="job-filter">
                        <button class="filter-btn active" data-filter="all">ทั้งหมด</button>
                        <?php
                        $allowed_status_ids = [1, 2, 4];
                        while ($rowStatus = $resultStatus->fetch_assoc()) {
                            if (in_array($rowStatus['job_status_id'], $allowed_status_ids)) {
                                echo '<button class="filter-btn" data-filter="' .
                                    htmlspecialchars($rowStatus['job_status_id'], ENT_QUOTES, 'UTF-8') . '">' .
                                    htmlspecialchars($rowStatus['job_status_th'], ENT_QUOTES, 'UTF-8') .
                                    '</button>';
                            }
                        }
                        ?>

                    </div>


                    <!-- ส่วนแสดงงาน -->
                    <div class="grid" id="job_container">
                        <?php foreach ($jobs as $job) { ?>
                            <?php
                            // ถ้าเป็นงานสถานะ 3 (Deleted) ให้ข้าม ไม่ต้องแสดง
                            if ($job['job_status_id'] == 3) {
                                continue;
                            }

                            // job_status_id = 2 หมายถึง "ปิด" เลยเปลี่ยนลิงก์ให้เป็น viewclosejob.php
                            $link = ($job['job_status_id'] == 2)
                                ? "viewclosejob.php?post_job_id=" . $job['post_job_id']
                                : "viewapply.php?post_job_id=" . $job['post_job_id'];
                            ?>

                            <div class="card"
                                id="<?php echo $job['post_job_id']; ?>"
                                data-status="<?php echo $job['job_status_id']; ?>"
                                onclick="window.location='<?php echo $link; ?>'">
                                <div class="job_display" id="job_display_<?php echo $job['post_job_id']; ?>">
                                    <div class="card-top">
                                        <img src="<?php echo htmlspecialchars($job['image'], ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="Job Image" class="job-image">
                                    </div>
                                    <div class="card-body">
                                        <h3><?php echo htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="job-description">
                                            <?php
                                            $description = htmlspecialchars($job['description'], ENT_QUOTES, 'UTF-8');
                                            echo (strlen($description) > 100) ? substr($description, 0, 95) . '...' : $description;
                                            ?>
                                        </p>
                                        <p><strong>รับจำนวน:</strong> <?php echo htmlspecialchars($job['number_student'], ENT_QUOTES, 'UTF-8'); ?> คน</p>
                                        <p><strong>ประกาศเมื่อ:</strong> <?php echo htmlspecialchars($job['created_at'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>


                </div>
            </div>
        </div>
    </div>
    <!-- Footer -->
    <footer class="footer">
        <p>© CSIT - Computer Science and Information Technology</p>
    </footer>
    </div>

    <!-- JavaScript สำหรับ Notifications -->
    <script>
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
    </script>
    <!-- JavaScript สำหรับ Tabs & Notifications -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const tabs = document.querySelectorAll(".tab");
            const notificationsCard = document.getElementById("notifications");
            let currentFilter = "all";

            function fetchNotifications(filterType) {
                fetch("teacher_profile.php?fetch_notifications=1")
                    .then(response => response.json())
                    .then(data => {
                        updateNotifications(data.notifications, filterType);
                        updateUnreadCount(data.unread_count);
                    })
                    .catch(error => console.error("Error fetching notifications:", error));
            }

            function updateNotifications(notifications, filterType) {
                const notificationList = document.getElementById("notification-list");
                notificationList.innerHTML = "";
                let unreadCount = 0;
                notifications.forEach(notification => {
                    // เงื่อนไขกรอง: แสดงทุกอันถ้า filter เป็น all, หรือเฉพาะ unread ถ้า filter เป็น unread
                    if (filterType === "all" || (filterType === "unread" && notification.status === "unread")) {
                        const notificationItem = document.createElement("a");
                        notificationItem.classList.add("notification-item");
                        notificationItem.setAttribute("data-status", notification.status);
                        notificationItem.setAttribute("data-id", notification.id);
                        let link = "#";
                        if (notification.reference_table === "job_application") {
                            link = `viewapply2.php?job_application_id=${notification.job_app_id}`;
                        } else if (notification.reference_table === "report") {
                            link = `viewnoti_reports.php?report_id=${notification.report_id}&notification_id=${notification.id}`;
                        }
                        notificationItem.href = link;

                        // สร้าง HTML สำหรับการแจ้งเตือน
                        let innerHTML = `
                    <div class="notification-content">
                        <h3 class="notification-title">${notification.title}</h3>
                        <p class="notification-message">${notification.message}</p>
                        <span class="notification-time">${notification.time}</span>
                    </div>
                `;
                        // ถ้ายังไม่อ่าน ให้เพิ่ม indicator จุดแดง
                        if (notification.status === "unread") {
                            innerHTML += '<span class="unread-dot"></span>';
                            unreadCount++;
                        }
                        notificationItem.innerHTML = innerHTML;

                        // ตั้ง event ให้คลิกแล้วเรียก markAsRead
                        notificationItem.addEventListener("click", function(event) {
                            event.preventDefault(); // ป้องกันการคลิกไปยังลิงก์
                            event.stopPropagation();
                            markAsRead(notification.id, notificationItem);
                            window.location.href = link;
                        });

                        notificationList.appendChild(notificationItem);
                    }
                });
                updateUnreadCount(unreadCount);
            }

            function markAsRead(notificationId, notificationItem) {
                // ตรวจสอบว่าการแจ้งเตือนนี้ยังเป็น unread อยู่หรือไม่
                const wasUnread = notificationItem.getAttribute("data-status") === "unread";

                // ลบ indicator จุดแดง (unread dot) ถ้ามีอยู่
                const redDot = notificationItem.querySelector('.unread-dot');
                if (redDot) {
                    redDot.remove();
                }

                // เปลี่ยนสถานะของ notification เป็น read
                notificationItem.dataset.status = "read";
                notificationItem.classList.remove("unread");
                notificationItem.classList.add("read");

                // หากเป็น notification ที่ยังไม่อ่าน ให้ลดตัวเลขแจ้งเตือน
                if (wasUnread) {
                    let unreadBadge = document.querySelector(".notification-badge");
                    let unreadCount = parseInt(unreadBadge.innerText) || 0;
                    if (unreadCount > 0) {
                        unreadCount--;
                        updateUnreadCount(unreadCount);
                    }
                }

                // เรียก API เพื่ออัปเดตสถานะในฐานข้อมูล
                fetch(`teacher_profile.php?notification_id=${notificationId}`, {
                        method: 'GET'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            console.error("Failed to update notification:", data.error);
                        }
                    })
                    .catch(error => console.error("Error updating notification:", error));
            }


            function updateUnreadCount(count) {
                let notificationBadge = document.querySelector(".notification-badge");
                let notificationCount = document.querySelector(".notification-count");
                if (notificationBadge && notificationCount) {
                    if (count > 0) {
                        notificationBadge.innerText = count;
                        notificationBadge.style.display = "inline-block";
                        notificationCount.innerText = `${count} new`;
                        notificationCount.style.display = "inline-block";
                    } else {
                        notificationBadge.style.display = "none";
                        notificationCount.style.display = "none";
                    }
                }
            }

            // ป้องกันการปิดการแจ้งเตือนโดยคลิกภายในกล่อง
            notificationsCard.addEventListener("click", function(event) {
                event.stopPropagation();
            });

            // ตั้ง event ให้กับแท็บตัวกรอง
            tabs.forEach(tab => {
                tab.addEventListener("click", function() {
                    tabs.forEach(t => t.classList.remove("active"));
                    this.classList.add("active");
                    currentFilter = this.getAttribute("data-filter");
                    fetchNotifications(currentFilter);
                });
            });

            // เรียกดึงข้อมูลเริ่มต้น
            fetchNotifications("all");
        });
    </script>
    <!-- JavaScript สำหรับ Edit Mode (Contact) -->
    <script>
        let isEditMode = false; // ใช้เช็คสถานะ edit

        function toggleEdit() {
            const cDisplay = document.getElementById('contact_display');
            const cEdit = document.getElementById('contact_edit');
            const saveBtn = document.querySelector('.save-button');

            isEditMode = !isEditMode; // toggle โหมด edit

            if (isEditMode) {
                cDisplay.style.display = 'none';
                cEdit.style.display = 'block';
                saveBtn.style.display = 'inline-block';
            } else {
                cDisplay.style.display = 'block';
                cEdit.style.display = 'none';
                saveBtn.style.display = 'none';
            }
        }

        function handleProfileClick() {
            if (isEditMode) {
                document.getElementById('profile_image_input').click();
            } else {
                alert("หากต้องการเปลี่ยนรูปโปรไฟล์ กรุณากดปุ่ม Edit ก่อน");
            }
        }

        function saveChanges() {
            try {
                const newPhone = document.getElementById('phone_number_input').value.trim();
                const newEmail = document.getElementById('email_input').value.trim();
                if (!newPhone || !newEmail) {
                    alert("กรุณากรอกข้อมูลให้ครบถ้วน!");
                    return;
                }
                let xhr = new XMLHttpRequest();
                xhr.open("POST", "teacher_profile.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            console.log("Response:", xhr.responseText);
                            if (xhr.responseText.trim() === "success") {
                                document.getElementById('contact_display').innerHTML = `
                                    <p>เบอร์โทร : ${newPhone}</p>
                                    <p>อีเมล : ${newEmail}</p>
                                `;
                                toggleEdit();
                            } else {
                                alert("❌ Update Error: " + xhr.responseText);
                            }
                        } else {
                            alert("❌ Server Error: " + xhr.status);
                        }
                    }
                };
                let postData = "phone_number=" + encodeURIComponent(newPhone) +
                    "&email=" + encodeURIComponent(newEmail);
                xhr.send(postData);
            } catch (error) {
                console.error("❌ Error in saveChanges():", error);
                alert("เกิดข้อผิดพลาด กรุณาลองอีกครั้ง!");
            }
        }
    </script>
    <!-- JavaScript สำหรับอัปโหลดรูป (Profile) -->
    <script>
        document.getElementById('profile_image_input').addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('profile_image', file);
            fetch('teacher_profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.text())
                .then(data => {
                    if (data.trim() === 'success') {
                        const img = document.getElementById('profile_picture');
                        img.src = img.src.split('?')[0] + '?t=' + new Date().getTime();
                        // รีเฟรชหน้าเว็บทันทีหลังอัปโหลดสำเร็จ
                        location.reload();
                    } else {
                        alert("อัปโหลดไม่สำเร็จ: " + data);
                    }
                })
                .catch(err => {
                    alert("เกิดข้อผิดพลาดในการอัปโหลด");
                    console.error(err);
                });
        });




        document.addEventListener("DOMContentLoaded", function() {
            const filterButtons = document.querySelectorAll('.job-filter .filter-btn');

            filterButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    // ลบ active class ออกจากทุกปุ่ม
                    filterButtons.forEach(b => b.classList.remove('active'));
                    // เพิ่ม active ให้กับปุ่มที่ถูกคลิก
                    this.classList.add('active');

                    const filter = this.getAttribute('data-filter');
                    const jobCards = document.querySelectorAll("#job_container .card");
                    jobCards.forEach(card => {
                        if (filter === "all") {
                            card.style.display = "block";
                        } else {
                            const status = card.getAttribute("data-status");
                            card.style.display = (status === filter) ? "block" : "none";
                        }
                    });
                });
            });
        });
    </script>
</body>

</html>
<?php
$stmtS->close();
$conn->close(); ?>