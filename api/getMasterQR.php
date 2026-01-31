<?php
header('Content-Type: application/json');
include 'config.php';

try {
    $stmt = $pdo->query("SELECT qr_image_path, is_enabled FROM master_qr_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        echo json_encode([
            'success' => false,
            'enabled' => false,
            'qr_image' => null,
            'message' => 'Master QR not configured'
        ]);
        exit();
    }

    if (!$settings['is_enabled']) {
        echo json_encode([
            'success' => false,
            'enabled' => false,
            'qr_image' => $settings['qr_image_path'] ? $settings['qr_image_path'] : null,
            'message' => 'Master QR is disabled'
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'enabled' => true,
        'qr_image' => $settings['qr_image_path'] ? $settings['qr_image_path'] : null
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
