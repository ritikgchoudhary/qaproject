<?php
require 'api/config.php';
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'transactions'");
    if ($stmt->rowCount() == 0) {
        // Create table if not exists
        $sql = "CREATE TABLE transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL, -- 'deposit', 'withdraw', 'win', 'loss', 'admin_adjustment'
            amount DECIMAL(10,2) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $pdo->exec($sql);
        echo "Table created.\n";
    } else {
        echo "Table exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
