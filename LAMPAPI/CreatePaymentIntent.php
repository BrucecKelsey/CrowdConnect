<?php
// Set CORS headers first
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Initialize error handling
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Try to load StripeConfig
    if (!file_exists(__DIR__ . '/StripeConfig.php')) {
        throw new Exception('StripeConfig.php file not found in ' . __DIR__);
    }
    require_once 'StripeConfig.php';

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['amount']) || !isset($input['djId'])) {
        throw new Exception('Missing required fields: ' . json_encode(array_keys($input ?: [])));
    }
    
    $amount = (int)$input['amount']; // Amount in cents
    $djId = (int)$input['djId'];
    $requestId = isset($input['requestId']) ? (int)$input['requestId'] : 0; // 0 for payment-first flow
    $customerId = isset($input['customerId']) ? (int)$input['customerId'] : null;
    
    if ($amount < 50) { // Minimum $0.50
        throw new Exception('Amount must be at least $0.50');
    }
    
    // Get DJ information
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $stmt = $conn->prepare("SELECT FirstName, LastName FROM Users WHERE ID = ?");
    $stmt->bind_param("i", $djId);
    $stmt->execute();
    $result = $stmt->get_result();
    $dj = $result->fetch_assoc();
    
    if (!$dj) {
        throw new Exception('DJ not found');
    }
    
    // Create payment intent
    $stripe = StripeConfig::getStripeClient();
    
    $metadata = [
        'dj_id' => $djId,
        'request_id' => $requestId,
        'dj_name' => $dj['FirstName'] . ' ' . $dj['LastName']
    ];
    
    if ($customerId) {
        $metadata['customer_id'] = $customerId;
    }
    
    $paymentIntent = $stripe->createPaymentIntent($amount, 'usd', $metadata);
    
    // Store tip record in database (RequestId will be updated later in payment-first flow)
    $requestIdToStore = $requestId > 0 ? $requestId : null;
    $tipAmount = $amount / 100; // Convert cents to dollars
    
    $stmt = $conn->prepare("INSERT INTO Tips (RequestId, DJUserID, CustomerUserID, TipAmount, StripePaymentIntentId, Status, Timestamp) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->bind_param("iiisd", $requestIdToStore, $djId, $customerId, $tipAmount, $paymentIntent['id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert tip record: " . $stmt->error);
    }
    
    $tipId = $conn->insert_id;
    error_log("Tip record created with ID: " . $tipId . " for PaymentIntent: " . $paymentIntent['id']);
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'clientSecret' => $paymentIntent['client_secret'],
        'paymentIntentId' => $paymentIntent['id']
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    if (isset($conn)) {
        $conn->close();
    }
    http_response_code(500);
    echo json_encode([
        'error' => 'Fatal error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>