<?php
require_once 'StripeConfig.php';
require_once 'Database.php';

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
    if (!isset($_GET['djId'])) {
        throw new Exception('Missing DJ ID');
    }
    
    $djId = (int)$_GET['djId'];
    
    // Get DJ's Stripe account ID from database
    $conn = new Database();
    $pdo = $conn->getConnection();
    
    $stmt = $pdo->prepare("SELECT StripeAccountId FROM Users WHERE ID = ?");
    $stmt->execute([$djId]);
    $dj = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dj || !$dj['StripeAccountId']) {
        echo json_encode([
            'success' => true,
            'accountExists' => false,
            'detailsSubmitted' => false,
            'chargesEnabled' => false
        ]);
        exit;
    }
    
    // Get account status from Stripe
    $stripe = StripeConfig::getStripeClient();
    $account = $stripe->retrieveAccount($dj['StripeAccountId']);
    
    echo json_encode([
        'success' => true,
        'accountExists' => true,
        'accountId' => $dj['StripeAccountId'],
        'detailsSubmitted' => $account['details_submitted'],
        'chargesEnabled' => $account['charges_enabled'],
        'payoutsEnabled' => $account['payouts_enabled'] ?? false
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>