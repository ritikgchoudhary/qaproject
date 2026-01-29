<?php
require 'config.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Admins table created successfully.\n";

    // Insert a default master admin if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pass = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES ('admin', ?)");
        $stmt->execute([$pass]);
        echo "Default admin created (User: admin, Pass: admin123)\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
