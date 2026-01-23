<?php
include 'config.php';

try {
    // Add bank details column
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS bank_account_number VARCHAR(50)");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS bank_ifsc_code VARCHAR(20)");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS bank_holder_name VARCHAR(100)");

    // Add withdrawal status in settings? No just in code.
    // We need to enforce referral limit.

    echo "Bank details columns added.<br>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
