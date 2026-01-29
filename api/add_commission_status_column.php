<?php
// Migration script to add status column to agent_commissions
$host = '72.60.96.75';
$db_name = 'qa_platform';
$username = 'qa_platform';
$password = 'qa_platform';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if status column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM agent_commissions LIKE 'status'");
    $column_exists = $stmt->rowCount() > 0;
    
    if (!$column_exists) {
        // Add status column
        $pdo->exec("ALTER TABLE agent_commissions ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved'");
        echo "✅ Successfully added status column to agent_commissions table.\n";
        
        // Mark all existing commissions as approved
        $pdo->exec("UPDATE agent_commissions SET status = 'approved' WHERE status IS NULL");
        echo "✅ Marked all existing commissions as approved.\n";
    } else {
        echo "ℹ️ Column status already exists.\n";
    }
    
    // Check if adjusted_amount column exists (for admin adjustments)
    $stmt = $pdo->query("SHOW COLUMNS FROM agent_commissions LIKE 'adjusted_amount'");
    $adjusted_exists = $stmt->rowCount() > 0;
    
    if (!$adjusted_exists) {
        $pdo->exec("ALTER TABLE agent_commissions ADD COLUMN adjusted_amount DECIMAL(10,2) DEFAULT NULL");
        echo "✅ Successfully added adjusted_amount column.\n";
    } else {
        echo "ℹ️ Column adjusted_amount already exists.\n";
    }
    
    // Check if admin_notes column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM agent_commissions LIKE 'admin_notes'");
    $notes_exists = $stmt->rowCount() > 0;
    
    if (!$notes_exists) {
        $pdo->exec("ALTER TABLE agent_commissions ADD COLUMN admin_notes TEXT DEFAULT NULL");
        echo "✅ Successfully added admin_notes column.\n";
    } else {
        echo "ℹ️ Column admin_notes already exists.\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
