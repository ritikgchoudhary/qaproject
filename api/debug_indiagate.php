<?php
include 'config.php';
try {
    $stmt = $pdo->query("SELECT id, question, image_url, LENGTH(image_url) as len FROM questions WHERE question LIKE '%famous monument%' OR question LIKE '%प्रसिद्ध स्मारक%'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $r) {
        echo "ID: " . $r['id'] . "\n";
        echo "Question: " . $r['question'] . "\n";
        echo "Length: " . $r['len'] . "\n";
        echo "URL: " . $r['image_url'] . "\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
