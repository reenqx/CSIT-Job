<?php
// เริ่มต้น Session
session_start();

// ล้างข้อมูลใน Session
session_unset();
session_destroy();

// Redirect กลับไปหน้า Login
header("Location: hometest.php");
exit();
?>
