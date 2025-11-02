<?php
require_once 'StripeConfig.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['amount']) || !isset($input['djId']) || !isset($input['requestId'])) {
        throw new Exception('Missing required fields');
    }
    
    $amount = (int)$input['amount']; // Amount in cents
    $djId = (int)$input['djId'];
    $requestId = (int)$input['requestId'];
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
    
    // Store tip record in database
    $stmt = $conn->prepare("INSERT INTO Tips (RequestId, DJUserID, CustomerUserID, TipAmount, StripePaymentIntentId, Status, Timestamp) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->bind_param("iiisd", $requestId, $djId, $customerId, $tipAmount, $paymentIntent['id']);
    $tipAmount = $amount / 100;
    $stmt->execute();
    
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
    echo json_encode(['error' => $e->getMessage()]);
}
?>