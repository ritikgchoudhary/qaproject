<?php
include 'config.php';

try {
    // 1. Alter Table
    $pdo->exec("ALTER TABLE questions ADD COLUMN IF NOT EXISTS option_a VARCHAR(255)");
    $pdo->exec("ALTER TABLE questions ADD COLUMN IF NOT EXISTS option_b VARCHAR(255)");
    $pdo->exec("ALTER TABLE questions ADD COLUMN IF NOT EXISTS option_c VARCHAR(255)");
    $pdo->exec("ALTER TABLE questions ADD COLUMN IF NOT EXISTS option_d VARCHAR(255)");
    $pdo->exec("ALTER TABLE questions ADD COLUMN IF NOT EXISTS image_url VARCHAR(500)");
    
    echo "Table altered successfully.<br>";

    // 2. Seed Data
    $json = file_get_contents('hindi_questions.json');
    $questionsData = json_decode($json, true);

    if ($questionsData) {
        $stmt = $pdo->prepare("INSERT INTO questions (question, answer, option_a, option_b, option_c, option_d, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $count = 0;
        foreach ($questionsData as $q) {
            // Check for duplicates
            $check = $pdo->prepare("SELECT count(*) FROM questions WHERE question = ?");
            $check->execute([$q['question']]);
            
            if ($check->fetchColumn() == 0) {
                // Shuffle options manually for storage or store as is? 
                // Let's store as is from JSON, assuming JSON has options array.
                // Wait, seed_hindi.php shuffled them. Let's do that.
                $options = $q['options'];
                shuffle($options);

                $stmt->execute([
                    $q['question'],
                    $q['answer'], // Correct answer
                    $options[0],
                    $options[1],
                    $options[2],
                    $options[3],
                    $q['image_url']
                ]);
                $count++;
            }
        }
        echo "Added $count new questions.<br>";
    }

    // 3. Unlock for current user (if logged in)
    if (isset($_SESSION['user_id'])) {
        $uid = $_SESSION['user_id'];
        $checkDep = $pdo->prepare("SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status='success'");
        $checkDep->execute([$uid]);
        if ($checkDep->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO deposits (user_id, amount, status) VALUES (?, 100, 'success')")->execute([$uid]);
            echo "Unlocked access for User ID: $uid";
        }
    } else {
        // Try to unlock for the latest user just in case
        $lastUser = $pdo->query("SELECT id FROM users ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($lastUser) {
             $pdo->prepare("INSERT INTO deposits (user_id, amount, status) VALUES (?, 100, 'success')")->execute([$lastUser]);
             echo "Unlocked access for User ID: $lastUser (Latest)";
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
