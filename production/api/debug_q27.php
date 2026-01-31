<?php
include 'config.php';
$stmt = $pdo->prepare('SELECT * FROM questions WHERE id = 27');
$stmt->execute();
$q = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Question: " . $q['question'] . "\n";
echo "Answer: " . $q['answer'] . "\n";
echo "Hex Answer: " . bin2hex($q['answer']) . "\n";
?>
