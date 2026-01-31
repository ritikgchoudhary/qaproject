<?php
include 'config.php';

try {
    // Create master_qr_settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS master_qr_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        qr_image_path VARCHAR(255) DEFAULT NULL,
        is_enabled TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by INT DEFAULT NULL
    )");
    
    // Insert default row if not exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM master_qr_settings");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO master_qr_settings (is_enabled) VALUES (0)");
    }
    
    echo "Master QR table created/verified successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
