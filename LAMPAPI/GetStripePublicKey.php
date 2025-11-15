<?php
require_once 'StripeConfig.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Initialize Stripe configuration
    StripeConfig::init();
    
    $publishableKey = StripeConfig::getPublishableKey();
    
    if (empty($publishableKey)) {
        throw new Exception('Stripe publishable key not configured');
    }
    
    echo json_encode([
        'success' => true,
        'publishableKey' => $publishableKey
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('GetStripePublicKey error: ' . $e->getMessage());
    echo json_encode([
        'error' => 'Unable to retrieve Stripe public key: ' . $e->getMessage(),
        'debug' => $e->getMessage()
    ]);
}
?>