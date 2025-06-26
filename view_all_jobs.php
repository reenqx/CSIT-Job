<?php
session_start();
include 'database.php';
$user_id = $_SESSION['user_id']??null;
$name = $_SESSION['name']??null;
$subcategory_id = isset($_GET['job_subcategory_id']) ? intval($_GET['job_subcategory_id']) : 0;
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'desc'; // เรียงจากใหม่ไปเก่าเป็นค่าเริ่มต้น
$filter_status = isset($_GET['status']) ? intval($_GET['status']) : 0; // 0 หมายถึงไม่กรอง

$sql_category = "SELECT job_subcategory_name FROM job_subcategory WHERE job_subcategory_id = ?";
$stmt = $conn->prepare($sql_category);
$stmt->bind_param("i", $subcategory_id);
$stmt->execute();
$stmt->bind_result($category_name);
$stmt->fetch();
$stmt->close();

$sql_jobs = "SELECT post_job.post_job_id as id, post_job.job_status_id, post_job.title, post_job.image, teacher.teach_name AS teacher
            FROM post_job
            LEFT JOIN teacher ON post_job.teacher_id = teacher.teacher_id
            WHERE post_job.job_subcategory_id = ?";

if ($filter_status > 0) {
    $sql_jobs .= " AND post_job.reward_type_id = ?";
}

$sql_jobs .= " ORDER BY post_job.post_job_id " . ($sort_order == 'asc' ? 'ASC' : 'DESC');

$stmt = $conn->prepare($sql_jobs);

if ($filter_status > 0) {
    $stmt->bind_param("ii", $subcategory_id, $filter_status);
} else {
    $stmt->bind_param("i", $subcategory_id);
}

$stmt->execute();
$result = $stmt->get_result();

$jobs = [];
while ($row = $result->fetch_assoc()) {
    if ($row["job_status_id"] == 1 || $row["job_status_id"] == 2) {
        $jobs[] = $row;
    }
}
$sql = "SELECT job_category_id AS id, job_category_name FROM job_category";
$result = $conn->query($sql);
$categories = [];
$otherCategories = [];
while ($row = $result->fetch_assoc()) {
    // เปรียบเทียบโดยใช้ strtolower และ trim เพื่อลดความผิดพลาด
    if (strtolower(trim($row['job_category_name'])) === "other") {
        $otherCategories[] = $row;
    } else {
        $categories[] = $row;
    }
}
// รวม category ปกติ แล้วค่อย merge กับ Other ให้อยู่ท้ายสุด
$orderedCategories = array_merge($categories, $otherCategories);

// ดึงข้อมูล subcategories
$subcategory = [];
$sql = "SELECT job_subcategory_id, job_subcategory_name, job_category_id FROM job_subcategory";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $subcategory[$row['job_category_id']][] = $row;
}


$sql = "SELECT * FROM reward_type";
$reward = $conn->query($sql);

?>

<!-- index.html -->
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSIT Job Board</title>
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/filter.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/view_all_jobs.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/header-footerstyle.css">
    <script>
        function applyFilter() {
            let sort = document.getElementById('sort').value;
            let status = document.getElementById('status').value;
            window.location.href = `?category_id=<?php echo $category_id; ?>&sort=${sort}&status=${status}`;
        }
    </script>
    <style>
        /* ปรับแต่งตัวกรอง */
        .filters {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .filters label {
            font-weight: bold;
            color: #333;
        }

        .filters select {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }

        .filters select:hover {
            border-color: #007bff;
        }

        .back-head{
        font-size: 30px;
        position: absolute;
        top: 70px;
       }
    </style>
</head>

<body>
    <!-- Header -->
     <!-- Header -->
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
        <a href="hometest.php"> <i class="bi bi-chevron-left"></i></a>
    </nav>
    <!-- Navbar Placeholder -->
    <?php include 'navbar.php' ?>



    <div id="contentWrapper" class="content-wrapper"></div>

    <main class="main-content">
        <div class="content">
            <h1 class="category-head">งานทั้งหมดในหมวด <?php echo htmlspecialchars($category_name); ?></h1>
            <div class="filters">
                <label>เรียงตาม:</label>
                <select id="sort" onchange="applyFilter()">
                    <option value="desc" <?php echo $sort_order == 'desc' ? 'selected' : ''; ?>>ใหม่ → เก่า</option>
                    <option value="asc" <?php echo $sort_order == 'asc' ? 'selected' : ''; ?>>เก่า → ใหม่</option>
                </select>
                <label>ประเภทผลตอบแทน:</label>
                <select id="status" onchange="applyFilter()">
                    <option value="0">ทั้งหมด</option>
                    <?php
                    while ($row = $reward->fetch_assoc()) {
                        echo '<option value="' . $row['reward_type_id'] . '" ' . ($filter_status == $row['reward_type_id'] ? 'selected' : '') . '>' . $row['reward_type_name_th'] . '</option>';
                    }
                    
                    ?>
                </select>
            </div>
            <?php if (empty($jobs)): ?>
                <p class="no-jobs">ไม่มีการประกาศงานประเภทนี้</p>
            <?php else: ?>
                <div class="job-grid">
                    <?php foreach ($jobs as $job): ?>
                        <a href="joinustest.php?id=<?php echo htmlspecialchars($job['id']); ?>&ip=<?php echo $_SERVER['REMOTE_ADDR']; ?>">
                            <div class="job-card">
                                <img class="job-image" src="<?php echo isset($job["image"]) ? htmlspecialchars($job["image"]) : "images/default.jpg"; ?>" alt="Job Image">
                                <div class="job-info">
                                    <div class="job-title"><?php echo htmlspecialchars($job["title"]); ?></div>
                                    <div class="job-author"><?php echo htmlspecialchars($job["teacher"]); ?></div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- External JS -->
    <script src="js/main.js"></script>
    <script src="js/navbar.js"></script>
    <script>
        function applyFilter() {
            let sort = document.getElementById('sort').value;
            let status = document.getElementById('status').value;
            let subcategoryId = <?php echo json_encode($subcategory_id); ?>; // ป้องกันปัญหาการเรียกใช้ตัวแปร PHP ใน JS

            // ตรวจสอบว่า subcategory_id มีค่าหรือไม่
            if (!subcategoryId || subcategoryId === "null") {
                console.error("Error: subcategory_id is missing or invalid.");
                return;
            }

            // อัปเดต URL ตามค่าที่เลือก
            let urlParams = new URLSearchParams(window.location.search);
            urlParams.set('subcategory_id', subcategoryId);
            urlParams.set('sort', sort);
            urlParams.set('status', status);

            window.location.href = '?' + urlParams.toString();
        }
    </script>
</body>

</html>
<?php
$stmt->close();
$conn->close();
?>