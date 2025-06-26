<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost"; // หรือ IP ของเซิร์ฟเวอร์ฐานข้อมูล
$username = "root"; // เปลี่ยนเป็นชื่อผู้ใช้ฐานข้อมูลของคุณ
$password = ""; // เปลี่ยนเป็นรหัสผ่านฐานข้อมูลของคุณ
$dbname = "ip"; // เปลี่ยนเป็นชื่อฐานข้อมูลของคุณ

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


?>