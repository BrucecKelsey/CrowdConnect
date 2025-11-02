<?php
require_once 'StripeConfig.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Initialize Stripe config
    StripeConfig::init();
    
    $publishableKey = StripeConfig::getPublishableKey();
    
    if (!$publishableKey) {
        throw new Exception('Stripe publishable key not configured');
    }
    
    // Validate key format
    if (!preg_match('/^pk_(test_|live_)[a-zA-Z0-9]+$/', $publishableKey)) {
        throw new Exception('Invalid Stripe publishable key format');
    }
    
    echo json_encode([
        'success' => true,
        'publishableKey' => $publishableKey
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>