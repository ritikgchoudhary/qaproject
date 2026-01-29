<?php
include 'config.php';

$question_id = 27;
$user_answer = "बुध"; // The correct answer from debug log

// Fetch from DB to be sure
$stmt = $pdo->prepare("SELECT answer FROM questions WHERE id = ?");
$stmt->execute([$question_id]);
$correct_answer = $stmt->fetchColumn();

// Simulation of submitQuestion logic
$user_clean = strtolower(trim($user_answer));
$db_clean = strtolower(trim($correct_answer));
$mb_user_clean = mb_strtolower(trim($user_answer), 'UTF-8');
$mb_db_clean = mb_strtolower(trim($correct_answer), 'UTF-8');

echo "Debug Comparison:\n";
echo "Correct Answer (DB): " . bin2hex($correct_answer) . " ($correct_answer)\n";
echo "User Answer (Raw):   " . bin2hex($user_answer) . " ($user_answer)\n\n";

echo "Standard strtolower:\n";
echo "User Clean: " . bin2hex($user_clean) . "\n";
echo "DB   Clean: " . bin2hex($db_clean) . "\n";
echo "Match? " . ($user_clean === $db_clean ? "YES" : "NO") . "\n\n";

echo "MB strtolower:\n";
echo "User Clean: " . bin2hex($mb_user_clean) . "\n";
echo "DB   Clean: " . bin2hex($mb_db_clean) . "\n";
echo "Match? " . ($mb_user_clean === $mb_db_clean ? "YES" : "NO") . "\n";

?>
