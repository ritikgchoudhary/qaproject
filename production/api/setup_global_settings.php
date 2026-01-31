<?php
require 'config.php';

try {
    // creating a new table to avoid messing with existing 'settings' table if structure is unknown
    $pdo->exec("CREATE TABLE IF NOT EXISTS global_settings (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        setting_key VARCHAR(50) UNIQUE, 
        setting_value TEXT
    )");
    
    // Insert default video URL
    $pdo->exec("INSERT IGNORE INTO global_settings (setting_key, setting_value) VALUES ('tutorial_video_url', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4')");
    
    echo "Global settings table setup complete.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
