<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch last 50 answers with question details
$stmt = $pdo->prepare("
    SELECT 
        a.id, 
        q.question, 
        q.answer as correct_answer, 
        a.is_correct, 
        a.created_at,
        CASE WHEN a.is_correct = 1 THEN 200 ELSE 0 END as earned_amount
    FROM answers a
    JOIN questions q ON a.question_id = q.id
    WHERE a.user_id = ?
    ORDER BY a.created_at DESC
    LIMIT 50
");

$stmt->execute([$user_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["history" => $history]);
?>
