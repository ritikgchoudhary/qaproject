<?php
include 'config.php';

echo "Checking Database Integrity...\n";

$stmt = $pdo->query("SELECT * FROM questions");
$count = 0;
$mismatch = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $count++;
    $answer = trim($row['answer']);
    $answer_clean = mb_strtolower($answer, 'UTF-8');
    
    $found = false;
    $options = ['option_a', 'option_b', 'option_c', 'option_d'];
    foreach ($options as $opt_col) {
        $opt = trim($row[$opt_col]);
        $opt_clean = mb_strtolower($opt, 'UTF-8');
        
        if ($opt === $answer || $opt_clean === $answer_clean) {
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $mismatch++;
        echo "MISMATCH ID: " . $row['id'] . "\n";
        echo "Question: " . $row['question'] . "\n";
        echo "DB Answer: '$answer'\n";
        echo "Options: \n";
        foreach ($options as $opt_col) {
            echo " - $opt_col: '" . trim($row[$opt_col]) . "'\n";
        }
        echo "--------------------------\n";
    }
}

echo "Checked $count questions.\n";
echo "Found $mismatch mismatches.\n";
?>
