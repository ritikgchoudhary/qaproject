<?php
// Migration script to add direct_commission_percentage column
$host = '72.60.96.75';
$db_name = 'qa_platform';
$username = 'qa_platform';
$password = 'qa_platform';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'direct_commission_percentage'");
    $column_exists = $stmt->rowCount() > 0;
    
    if (!$column_exists) {
        // Add column for direct commission percentage
        $pdo->exec("ALTER TABLE users ADD COLUMN direct_commission_percentage DECIMAL(5,2) DEFAULT NULL");
        echo "✅ Successfully added direct_commission_percentage column to users table.\n";
    } else {
        echo "ℹ️ Column direct_commission_percentage already exists.\n";
    }
    
    // Set default 50% for existing agents
    $stmt = $pdo->prepare("UPDATE users SET direct_commission_percentage = 50.00 WHERE role = 'agent' AND (direct_commission_percentage IS NULL OR direct_commission_percentage = 0)");
    $stmt->execute();
    $updated = $stmt->rowCount();
    echo "✅ Set default 50% for $updated existing agents.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
