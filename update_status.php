<?php
header('Content-Type: application/json');
include 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_id = $_POST['job_id'] ?? 0;
    
    try {
        $stmt = $conn->prepare("UPDATE post_job SET job_status_id = 1 WHERE post_job_id = ?");
        $stmt->bind_param("i", $job_id);
        
        if ($stmt->execute()) {
            echo 'success';
        } else {
            echo 'error: ' . $stmt->error;
        }
        
    } catch (Exception $e) {
        echo 'error: ' . $e->getMessage();
    }
    
    $conn->close();
}
?>