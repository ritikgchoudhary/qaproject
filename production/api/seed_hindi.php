<?php
include 'config.php';

$json = file_get_contents('hindi_questions.json');
$questions = json_decode($json, true);

if ($questions) {
    try {
        $stmt = $pdo->prepare("INSERT INTO questions (question, answer, option_a, option_b, option_c, option_d, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $count = 0;
        foreach ($questions as $q) {
            // Check for duplicates
            $check = $pdo->prepare("SELECT count(*) FROM questions WHERE question = ?");
            $check->execute([$q['question']]);
            
            if ($check->fetchColumn() == 0) {
                // Ensure options are shuffled
                $options = $q['options'];
                shuffle($options);

                $stmt->execute([
                    $q['question'],
                    $q['answer'],
                    $options[0],
                    $options[1],
                    $options[2],
                    $options[3],
                    $q['image_url']
                ]);
                $count++;
            }
        }
        echo "Successfully added $count Hindi Image Questions!";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Failed to load JSON file.";
}
?>
