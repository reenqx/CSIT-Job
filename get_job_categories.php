<?php
include 'database.php';
header('Content-Type: application/json');

$categoryId = $_GET['category_id'] ?? '';

if ($categoryId) {
    $stmt = $conn->prepare("SELECT job_category_id, job_category_name FROM job_category WHERE job_category_id = ?");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();

    $jobCategories = [];
    while ($row = $result->fetch_assoc()) {
        $jobCategories[] = ['id' => $row['job_category_id'], 'name' => $row['job_category_name']];
    }

    echo json_encode($jobCategories);
} else {
    echo json_encode([]);
}
?>
