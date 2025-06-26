<?php
session_start();
include 'database.php';
$user_id = $_SESSION['user_id'] ?? 0;
$post_job_id = isset($_GET['post_job_id']) ? intval($_GET['post_job_id']) : null;
$sqlJobs = "SELECT pj.title AS title, rt.reward_type_name, st.stu_name, acs.salary
            FROM accepted_student acs
            JOIN post_job pj ON pj.post_job_id = acs.post_job_id
            JOIN reward_type rt ON rt.reward_type_id = pj.reward_type_id
            JOIN student st ON st.student_id = acs.student_id
            WHERE pj.post_job_id = ?";  // ระบุ alias 'pj' เพื่อความชัดเจน
$stmtJ = $conn->prepare($sqlJobs);
$stmtJ->bind_param("i", $post_job_id);
$stmtJ->execute();
$resJobs = $stmtJ->get_result();
$jobs = [];
while ($rowJob = $resJobs->fetch_assoc()) {
    $jobs[] = $rowJob;
}
$stmtJ->close();



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">
    <!-- CSS ของคุณ -->
    <link rel="stylesheet" href="css/header-footerstyle.css">
    <link rel="stylesheet" href="css/viewclosejob.css">
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
    <nav class="back-head">
        <a href="javascript:history.back()"> <i class="bi bi-chevron-left"></i></a>
    </nav>
    <div class="content">
    <div class="header-text">
            <span>รายละเอียดผลตอบแทนงานที่เสร็จสิ้น</span>
    </div>
    <div class="job-name">
    <?php echo "งาน : " . $jobs[0]['title']; ?>
    </div>
    <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th>ลำดับ</th>
                <th>ชื่อนิสิต</th>
                <th>ประเภทผลตอบแทน</th>
                <th>จำนวน</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $i = 1;
            foreach ($jobs as $job):
            ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo htmlspecialchars($job['stu_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($job['reward_type_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($job['salary'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>









</body>

</html>