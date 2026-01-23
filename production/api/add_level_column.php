<?php
include 'config.php';

try {
    // Add 'level' column to users table if not exists
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS level INT DEFAULT 1");
    echo "Added 'level' column to users table.<br>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
