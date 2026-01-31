<?php
include 'config.php';

echo "Fixing Image URLs...\n";

// 1. Prepend domain to any relative URLs starting with /wikipedia
$sql = "UPDATE questions SET image_url = CONCAT('https://upload.wikimedia.org', image_url) WHERE image_url LIKE '/wikipedia%'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
echo "Updated relative URLs: " . $stmt->rowCount() . "\n";

// 2. Trim whitespace
$sql = "UPDATE questions SET image_url = TRIM(image_url)";
$pdo->exec($sql);
echo "Trimmed URLs.\n";

// 3. verifying
$stmt = $pdo->query("SELECT id, image_url FROM questions");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $r) {
    if (strpos($r['image_url'], 'http') !== 0) {
        echo "WARNING: ID " . $r['id'] . " still has relative URL: " . $r['image_url'] . "\n";
    }
}
echo "Done.\n";
?>
