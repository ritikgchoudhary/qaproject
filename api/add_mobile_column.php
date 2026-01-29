<?php
include 'config.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN mobile VARCHAR(15) UNIQUE AFTER name");
    echo "Mobile column added successfully.";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') { // Duplicate column name
        echo "Column 'mobile' already exists.";
    } else {
        echo "Error adding column: " . $e->getMessage();
    }
}
?>
