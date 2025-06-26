<?php

session_start();
include 'database.php';
$user_id = $_SESSION['user_id'] ?? 0;

// รับค่า repors_id และ notification_id จาก URL
$report_id = $_GET['report_id'] ?? 4;
$notification_id = $_GET['notification_id'] ?? 122;

if (!$report_id || !$notification_id) {
    die("ไม่พบค่า report_id หรือ notification_id");
}


// เตรียมคำสั่ง SQL เพื่อดึงข้อมูล
$sql = "SELECT 
            r.report_id, 
            pj.post_job_id, 
            pj.title, 
            n.message,
            n.created_at  -- ✅ เพิ่ม created_at
        FROM report r
        JOIN post_job pj ON r.post_job_id = pj.post_job_id
        JOIN notification n ON n.reference_id = r.report_id
        WHERE r.report_id = ? AND n.notification_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $report_id, $notification_id);
$stmt->execute();
$result = $stmt->get_result();

$report = [];

while ($row = $result->fetch_assoc()) {
    $report[] = $row;
}

$stmt->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดการรีพอร์ต</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="css/header-footerstyle.css">
    <style>
        html,
        body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        .container {
            flex: 1;
            /* ให้เนื้อหาขยายเต็มที่และดัน footer ลงไปด้านล่าง */
            display: flex;
            flex-direction: column;
        }
        .back-arrow {
    display: inline-flex;
    width: 10px;
    height: 10px;
    margin-right: 10px; /* ระยะห่างระหว่างลูกศรและข้อความ */
    border-left: 2px solid #333; /* สร้างเส้นเฉียง */
    border-bottom: 2px solid #333;
    transform: rotate(45deg); /* หมุนเส้นให้เป็นลูกศร */
    cursor: pointer;   
    position: absolute; /* ใช้ position เพื่อกำหนดตำแหน่ง */
    top: 10%; /* ระยะห่างจากขอบด้านบน */
    left: 20px; /* ระยะห่างจากขอบด้านซ้าย */     
}

.back-arrow:hover {
    border-color: #555; /* เปลี่ยนสีเมื่อวางเมาส์ */
}
    </style>
</head>

<body>
    <!-- ✅ Header -->
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
    <a href="javascript:window.history.back();" class="back-arrow">
    <i class="bi bi-arrow-left"></i>
</a>

      <div class="container mt-5">
        <h2>รายละเอียดการรีพอร์ต</h2>

        <?php if (count($report) > 0): ?>
            <ul class="list-group mt-3">
                <?php foreach ($report as $report): ?>
                    <li class="list-group-item">
                        <h5>
                            <a href="joinustest.php?id=<?php echo $report['post_job_id']; ?>" class="text-primary">
                                <?php echo htmlspecialchars($report['title']); ?>
                            </a>
                        </h5>
                        <p class="text-muted">ข้อความ: <?php echo htmlspecialchars($report['message']); ?></p>
                        <p class="text-muted">วันที่แจ้งเตือน: <?php echo htmlspecialchars($report['created_at']); ?></p> <!-- ✅ เพิ่มวันที่ -->
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="alert alert-warning">ไม่พบข้อมูลการรีพอร์ต</p>
        <?php endif; ?>

        
    </div>
    <!-- ✅ Footer -->
    <footer class="footer">
        <p>© CSIT - Computer Science and Information Technology</p>
    </footer>
</body>

</html>