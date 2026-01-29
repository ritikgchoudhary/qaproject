<?php
include 'config.php';

// Allow public access (or restrict as needed, but usually settings like these are public)
// We just fetch key-value pairs from global_settings

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM global_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Default values if missing
    $defaults = [
        'tutorial_video_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
        'tutorial_title' => 'How It Works',
        'tutorial_desc' => 'Watch the full video to unlock your quiz.',
        'tutorial_btn_text' => 'WATCH TO CONTINUE'
    ];
    
    $response = array_merge($defaults, $settings);
    
    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
}
?>
