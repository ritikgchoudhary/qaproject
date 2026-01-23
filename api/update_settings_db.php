<?php
require 'config.php';

try {
    $settings = [
        'tutorial_title' => 'How It Works',
        'tutorial_desc' => 'Watch the full video to unlock your quiz.',
        'tutorial_btn_text' => 'WATCH TO CONTINUE'
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO global_settings (setting_key, setting_value) VALUES (?, ?)");

    foreach ($settings as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    
    echo "Settings updated successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
