<?php
include 'config.php';

try {
    $sql = "ALTER TABLE users ADD COLUMN plain_password VARCHAR(255) NULL AFTER password";
    $pdo->exec($sql);
    echo "Column 'plain_password' added successfully.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Column 'plain_password' already exists.";
    } else {
        echo "Error adding column: " . $e->getMessage();
    }
}
?>
