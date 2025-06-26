<?php
// เชื่อมต่อฐานข้อมูล
$conn = new mysqli("localhost", "root", "", "test2");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = intval($_POST["user_id"]);
    $skills = isset($_POST["skills"]) ? $_POST["skills"] : [];
    
    // ลบทักษะเก่าทั้งหมดของ user นี้
    $sql_delete = "DELETE FROM user_skills WHERE user_id = ?";
    $stmt = $conn->prepare($sql_delete);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // เพิ่มทักษะใหม่ที่ถูกเลือก
    if (!empty($skills)) {
        $sql_insert = "INSERT INTO user_skills (user_id, skill_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql_insert);
        foreach ($skills as $skill_id) {
            $stmt->bind_param("ii", $user_id, $skill_id);
            $stmt->execute();
        }
        $stmt->close();
    }
    
    echo "Skills updated successfully!";
    header("Location: t.php");
}

$conn->close();
?>