<?php
// Fix path if run from CLI vs Browser
if (file_exists('config.php')) {
    include 'config.php';
} else {
    include __DIR__ . '/config.php';
}

$output = "Debug Questions Data (Plain Text)\n";
$output .= "=================================\n";

$stmt = $pdo->query("SELECT * FROM questions ORDER BY id ASC LIMIT 50");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $output .= "ID: " . $row['id'] . "\n";
    $output .= "Q: " . $row['question'] . "\n";
    $output .= "A: " . $row['option_a'] . "\n";
    $output .= "B: " . $row['option_b'] . "\n";
    $output .= "C: " . $row['option_c'] . "\n";
    $output .= "D: " . $row['option_d'] . "\n";
    $output .= "Ans: " . $row['answer'] . "\n";

    $match = "NO MATCH";
    $options = [
        trim($row['option_a']), 
        trim($row['option_b']), 
        trim($row['option_c']), 
        trim($row['option_d'])
    ];
    $clean_answer = trim($row['answer']);
    
    foreach ($options as $opt) {
        // Strict check first, then loose
        if ($opt === $clean_answer) {
             $match = "EXACT MATCH";
             break;
        }
        if (strtolower($opt) === strtolower($clean_answer)) {
            $match = "CASE MATCH";
            break;
        }
    }
    
    $output .= "Status: " . $match . "\n";
    $output .= "---------------------------------\n";
}

file_put_contents('debug_log_v2.txt', $output);
echo "Log written to debug_log_v2.txt";
?>
