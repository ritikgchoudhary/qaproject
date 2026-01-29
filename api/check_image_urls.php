<?php
include 'config.php';
try {
    $stmt = $pdo->query("SELECT id, image_url FROM questions");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $r) {
        echo "ID: " . $r['id'] . " | URL: " . substr($r['image_url'], 0, 100) . "...\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
