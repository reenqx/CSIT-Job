<?php
include 'database.php';
header('Content-Type: application/json');

$categoryId = $_GET['category_id'] ?? '';

if ($categoryId) {
    $stmt = $conn->prepare("SELECT job_subcategory_id, job_subcategory_name FROM job_subcategory WHERE job_category_id = ?");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();

    $jobSubcategories = [];
    while ($row = $result->fetch_assoc()) {
        $jobSubcategories[] = [
            'job_subcategory_id' => $row['job_subcategory_id'],
            'job_subcategory_name' => $row['job_subcategory_name']
        ];
        
    }

    echo json_encode($jobSubcategories);
} else {
    echo json_encode([]);
}
?>
