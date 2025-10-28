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
    
    if (!isset($input['paymentIntentId'])) {
        throw new Exception('Missing payment intent ID');
    }
    
    $paymentIntentId = $input['paymentIntentId'];
    
    // Retrieve payment intent from Stripe
    $stripe = StripeConfig::getStripeClient();
    $paymentIntent = $stripe->retrievePaymentIntent($paymentIntentId);
    
    // Update tip record in database
    $conn = new Database();
    $pdo = $conn->getConnection();
    
    $status = $paymentIntent['status'] === 'succeeded' ? 'completed' : 'failed';
    
    $stmt = $pdo->prepare("
        UPDATE Tips 
        SET Status = ?, CompletedAt = NOW(), StripeChargeId = ?
        WHERE StripePaymentIntentId = ?
    ");
    $stmt->execute([$status, $paymentIntent['latest_charge'] ?? null, $paymentIntentId]);
    
    if ($status === 'completed') {
        // Get tip details for response
        $stmt = $pdo->prepare("
            SELECT t.*, u.FirstName, u.LastName, r.SongName 
            FROM Tips t 
            JOIN Users u ON t.DJUserID = u.ID 
            JOIN Requests r ON t.RequestID = r.ID 
            WHERE t.StripePaymentIntentId = ?
        ");
        $stmt->execute([$paymentIntentId]);
        $tip = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'status' => 'completed',
            'tip' => [
                'amount' => $tip['Amount'],
                'djName' => $tip['FirstName'] . ' ' . $tip['LastName'],
                'songName' => $tip['SongName']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'status' => 'failed',
            'error' => 'Payment was not successful'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>