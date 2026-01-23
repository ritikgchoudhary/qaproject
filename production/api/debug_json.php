<?php
include 'config.php';
// Simulate User ID 1 (Assuming there is a user with ID 1, likely the one at Level 1)
$_SESSION['user_id'] = 1;

// Get User Level
$stmt = $pdo->prepare("SELECT level FROM users WHERE id = ?");
$stmt->execute([1]);
$level = $stmt->fetchColumn() ?: 1;

echo "User Level: $level\n";

$offset = $level - 1;
$stmt = $pdo->prepare("SELECT * FROM questions ORDER BY id ASC LIMIT 1 OFFSET :offset");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    echo "No question found.\n";
} else {
    echo "Image URL from DB: [" . $question['image_url'] . "]\n";
    
    $json = json_encode([
        "id" => $question['id'],
        "image_url" => $question['image_url']
    ]);
    echo "JSON Output: " . $json . "\n";
}
?>
