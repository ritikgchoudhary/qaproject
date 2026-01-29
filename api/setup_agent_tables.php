<?php
include 'config.php';

try {
    // 1. Add 'role' column to users
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'user'");
        echo "Added 'role' column to users table.<br>";
    }

    // 2. Create agent_commissions table
    $sql = "CREATE TABLE IF NOT EXISTS agent_commissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        from_user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        level INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (agent_id)
    )";
    $pdo->exec($sql);
    echo "Created/Verified 'agent_commissions' table.<br>";
    
    // 3. Make our main test user an agent for testing
    // Finding user with email starting with 'testrunner_' or just id 1
    $stmt = $pdo->prepare("UPDATE users SET role = 'agent' WHERE id = 1");
    $stmt->execute();
    echo "Set User ID 1 as Agent for testing.<br>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
