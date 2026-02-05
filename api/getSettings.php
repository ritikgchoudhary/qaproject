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
    
    // Convert logo URL to current domain if it contains old domain
    if (isset($response['site_logo']) && $response['site_logo']) {
        $currentBaseUrl = getBaseUrl();
        $logoUrl = $response['site_logo'];
        
        // If it's already a full URL, extract the path and rebuild with current domain
        if (preg_match('#^https?://[^/]+/(.+)$#', $logoUrl, $matches)) {
            $path = $matches[1];
            // Ensure path doesn't start with / (already included in base URL)
            $path = ltrim($path, '/');
            $response['site_logo'] = $currentBaseUrl . '/' . $path;
        } elseif (preg_match('#^/(.+)$#', $logoUrl, $matches)) {
            // If it's a relative path starting with /
            $response['site_logo'] = $currentBaseUrl . $logoUrl;
        } elseif (!preg_match('#^https?://#', $logoUrl)) {
            // If it's a relative path without leading /
            $response['site_logo'] = $currentBaseUrl . '/' . $logoUrl;
        }
    }
    
    // Convert tutorial video URL to current domain if it contains old domain
    if (isset($response['tutorial_video_url']) && $response['tutorial_video_url']) {
        $videoUrl = $response['tutorial_video_url'];
        // Only convert if it's not an external URL (like YouTube, Vimeo, etc.)
        if (preg_match('#^https?://iquizz\.in/(.+)$#', $videoUrl, $matches)) {
            // Replace old domain with current domain
            $currentBaseUrl = getBaseUrl();
            $response['tutorial_video_url'] = $currentBaseUrl . '/' . $matches[1];
        } elseif (!preg_match('#^https?://#', $videoUrl)) {
            // If it's a relative path, prepend current domain
            $currentBaseUrl = getBaseUrl();
            $response['tutorial_video_url'] = $currentBaseUrl . '/' . ltrim($videoUrl, '/');
        }
    }
    
    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
}
?>
