<?php
include 'config.php';

try {
    // Add tree_bonus_distributed to users
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS tree_bonus_distributed TINYINT(1) DEFAULT 0");
    
    // Add type to agent_commissions
    $pdo->exec("ALTER TABLE agent_commissions ADD COLUMN IF NOT EXISTS commission_type VARCHAR(20) DEFAULT 'level'");
    
    echo "Migration Successfull!\n";
} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}
?>
