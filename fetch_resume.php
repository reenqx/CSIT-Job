<?php
require 'db_connect.php';

// รับ ID ของใบสมัครจาก URL
$job_application_job_application_id = isset($_GET['job_application_id']) ? intval($_GET['job_application_id']) : 0;

if ($job_application_job_application_id <= 0) {
    http_response_code(400);
    die("Invalid request");
}

// ดึงชื่อไฟล์เรซูเม่จากฐานข้อมูล
$stmt = $conn->prepare("SELECT resume FROM job_application WHERE job_application_id = ?");
$stmt->bind_param("i", $job_application_job_application_id);
$stmt->execute();
$stmt->bind_result($resumeFile);
$stmt->fetch();
$stmt->close();
$conn->close();

if (!$resumeFile) {
    http_response_code(404);
    die("Resume not found");
}

// กำหนดพาธไฟล์เรซูเม่
$filePath = __DIR__ . "/resumes/" . basename($resumeFile);

// ตรวจสอบว่าไฟล์มีอยู่จริง
if (!file_exists($filePath)) {
    http_response_code(404);
    die("File not found");
}

// ตรวจสอบ MIME Type อัตโนมัติ
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// ส่ง header และไฟล์
header("Content-Type: " . $mimeType);
header("Content-Length: " . filesize($filePath));
readfile($filePath);
exit;
?>