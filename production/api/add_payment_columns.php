<?php
require 'config.php';

try {
    // Add bank details columns if they don't exist
    $columns = ['bank_account_number', 'bank_ifsc_code', 'bank_holder_name', 'usdt_address'];
    
    foreach ($columns as $col) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN $col VARCHAR(255) DEFAULT NULL");
            echo "Added $col\n";
        } catch (PDOException $e) {
            // Ignore if column already exists
             echo "Column $col might already exist or error: " . $e->getMessage() . "\n";
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
