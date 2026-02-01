<?php
header('Content-Type: application/json');
include 'config.php';

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM global_settings WHERE setting_key IN ('simplypay_enabled', 'watchpay_enabled', 'silkpay_enabled', 'custom_qr_enabled')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Default all enabled if not set
    $defaults = [
        'simplypay_enabled' => '1',
        'watchpay_enabled' => '1',
        'silkpay_enabled' => '1',
        'custom_qr_enabled' => '1'
    ];
    
    $result = array_merge($defaults, $settings);
    
    echo json_encode([
        'success' => true,
        'methods' => [
            'SIMPLYPAY' => ($result['simplypay_enabled'] ?? '1') === '1',
            'WATCHPAY' => ($result['watchpay_enabled'] ?? '1') === '1',
            'SILKPAY' => ($result['silkpay_enabled'] ?? '1') === '1',
            'CUSTOM_QR' => ($result['custom_qr_enabled'] ?? '1') === '1'
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'methods' => [
            'SIMPLYPAY' => true,
            'WATCHPAY' => true,
            'SILKPAY' => true,
            'CUSTOM_QR' => true
        ]
    ]);
}
?>
