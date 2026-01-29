<?php
include 'config.php';

echo "Testing Hindi Match Logic...\n";

// Fetch a question with Hindi text if possible
$stmt = $pdo->query("SELECT * FROM questions LIMIT 1");
$q = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$q) die("No questions found.");

echo "Question: " . $q['question'] . "\n";
echo "Correct Answer (DB): [" . $q['answer'] . "]\n";
echo "Option A (DB): [" . $q['option_a'] . "]\n";

$db_ans = strtolower(trim($q['answer']));
// Simulate user picking the exact string from Option A if it matches
$simulated_input = strtolower(trim($q['option_a'])); // Assume Option A is correct for test
// Actually let's find the matching option
$matching_opt = '';
if (strtolower(trim($q['option_a'])) === $db_ans) $matching_opt = $q['option_a'];
if (strtolower(trim($q['option_b'])) === $db_ans) $matching_opt = $q['option_b'];
if (strtolower(trim($q['option_c'])) === $db_ans) $matching_opt = $q['option_c'];
if (strtolower(trim($q['option_d'])) === $db_ans) $matching_opt = $q['option_d'];

if ($matching_opt !== '') {
    echo "Found matching option text: [" . $matching_opt . "]\n";
    $input_clean = strtolower(trim($matching_opt));
    
    if ($input_clean === $db_ans) {
        echo "SUCCESS: Comparison works.\n";
        echo "Hex DB: " . bin2hex($db_ans) . "\n";
        echo "Hex In: " . bin2hex($input_clean) . "\n";
    } else {
        echo "FAILURE: Strings look same but differ.\n";
        echo "Hex DB: " . bin2hex($db_ans) . "\n";
        echo "Hex In: " . bin2hex($input_clean) . "\n";
    }
} else {
    echo "WARNING: No option matches the answer strictly!\n";
    echo "Hex Answer: " . bin2hex($db_ans) . "\n";
    echo "Hex Opt A : " . bin2hex(strtolower(trim($q['option_a']))) . "\n";
}
?>
