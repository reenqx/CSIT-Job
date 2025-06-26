<?php
include 'database.php';
header('Content-Type: application/json');

$skillId = $_GET['skill_id'] ?? '';

if ($skillId) {
    $stmt = $conn->prepare("SELECT subskill_id, subskill_name FROM subskill WHERE skill_id = ?");
    $stmt->bind_param("i", $skillId);
    $stmt->execute();
    $result = $stmt->get_result();

    $subskills = [];
    while ($row = $result->fetch_assoc()) {
        $subskills[] = [
            'subskill_id' => $row['subskill_id'],
            'subskill_name' => $row['subskill_name']
        ];
        
    }

    echo json_encode($subskills);
} else {
    echo json_encode([]);
}
?>
