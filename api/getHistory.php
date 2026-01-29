<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current user level for fallback calculation
$stmt = $pdo->prepare("SELECT level FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_level = $stmt->fetchColumn() ?: 1;

// Fetch last 50 answers with question details
// Try to get actual win amount from transactions first, then calculate based on current level
$stmt = $pdo->prepare("
    SELECT 
        a.id, 
        q.question, 
        q.answer as correct_answer, 
        a.is_correct, 
        a.created_at,
        CASE 
            WHEN a.is_correct = 1 THEN 
                COALESCE(
                    (SELECT amount FROM transactions 
                     WHERE user_id = a.user_id 
                     AND type = 'win' 
                     AND description LIKE CONCAT('%Question #', q.id, '%')
                     AND ABS(TIMESTAMPDIFF(SECOND, created_at, a.created_at)) <= 5
                     ORDER BY created_at DESC LIMIT 1),
                    (100 * POW(2, ? - 1) * 2)
                )
            ELSE 0 
        END as earned_amount
    FROM answers a
    JOIN questions q ON a.question_id = q.id
    WHERE a.user_id = ?
    ORDER BY a.created_at DESC
    LIMIT 50
");

$stmt->execute([$current_level, $user_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["history" => $history]);
?>
