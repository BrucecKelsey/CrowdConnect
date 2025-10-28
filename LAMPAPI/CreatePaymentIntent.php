<?php
require_once 'StripeConfig.php';
require_once 'Database.php';

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
    
    // Get DJ Stripe account ID
    $conn = new Database();
    $pdo = $conn->getConnection();
    
    $stmt = $pdo->prepare("SELECT StripeAccountId, FirstName, LastName FROM Users WHERE ID = ?");
    $stmt->execute([$djId]);
    $dj = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dj) {
        throw new Exception('DJ not found');
    }
    
    if (!$dj['StripeAccountId']) {
        throw new Exception('DJ has not completed Stripe onboarding');
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
    $stmt = $pdo->prepare("
        INSERT INTO Tips (RequestID, DJUserID, CustomerUserID, Amount, StripePaymentIntentId, Status, CreatedAt) 
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$requestId, $djId, $customerId, $amount / 100, $paymentIntent['id']]);
    
    echo json_encode([
        'success' => true,
        'clientSecret' => $paymentIntent['client_secret'],
        'paymentIntentId' => $paymentIntent['id']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>