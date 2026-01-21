<?php
include 'config.php';
try {
    $stmt = $pdo->query("DESCRIBE questions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns:\n" . implode("\n", $columns) . "\n";
} catch (PDOException $e) {
    echo "Error describing table: " . $e->getMessage();
}
?>
