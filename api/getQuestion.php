<?php
include 'config.php';
error_reporting(0); // Suppress warnings for clean JSON output

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

require_once 'utils.php';

$user_id = $_SESSION['user_id'];

// Sync level with current referrals before serving question
autoLevelUp($pdo, $user_id);

// Get User Level
$stmt = $pdo->prepare("SELECT level FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$level = $stmt->fetchColumn() ?: 1;

// Get The n-th Question (One question per level)
// We order by ID to ensure sequence
// LIMIT 1 OFFSET (level - 1)
$offset = $level - 1;
$stmt = $pdo->prepare("
    SELECT * FROM questions 
    ORDER BY id ASC 
    LIMIT 1 OFFSET :offset
");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    echo json_encode(["message" => "No more questions available! You have completed all levels.", "all_completed" => true]);
    exit();
}

// Check if ALREADY Answered CORRECTLY
$stmt = $pdo->prepare("SELECT id FROM answers WHERE user_id = ? AND question_id = ? AND is_correct = 1");
$stmt->execute([$user_id, $question['id']]);
$answered = $stmt->fetchColumn();

if ($answered) {
    // User answered the question for this level.
    // They must withdraw to increase level and get next question.
    echo json_encode([
        "message" => "Level $level Completed! Withdraw your winnings to unlock the next question.", 
        "level_completed" => true
    ]);
} else {
    // Return Question
    echo json_encode([
        "id" => $question['id'],
        "question" => $question['question'],
        "image_url" => $question['image_url'],
        "options" => [
            $question['option_a'], 
            $question['option_b'], 
            $question['option_c'], 
            $question['option_d']
        ],
        "level" => $level
    ]);
}
?>
