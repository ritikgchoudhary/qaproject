<?php
include 'config.php';

echo "Checking for Answer Mismatches...\n\n";

$stmt = $pdo->query("SELECT * FROM questions");
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($questions as $q) {
    $options = [
        trim($q['option_a']),
        trim($q['option_b']),
        trim($q['option_c']),
        trim($q['option_d'])
    ];
    
    $correct = trim($q['answer']);
    
    // Check Case-Insensitive Match
    $found = false;
    foreach($options as $opt) {
        if (strtolower($opt) === strtolower($correct)) {
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "[MISMATCH DETECTED] ID: " . $q['id'] . "\n";
        echo "Question: " . $q['question'] . "\n";
        echo "Stored Answer: '" . $correct . "'\n";
        echo "Options: ['" . implode("', '", $options) . "']\n";
        echo "---------------------------------\n";
    }
}
echo "Check Complete.\n";
?>
