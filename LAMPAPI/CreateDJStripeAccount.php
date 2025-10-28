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
    
    if (!isset($input['djId'])) {
        throw new Exception('Missing DJ ID');
    }
    
    $djId = (int)$input['djId'];
    
    // Check if DJ already has a Stripe account
    $conn = new Database();
    $pdo = $conn->getConnection();
    
    $stmt = $pdo->prepare("SELECT StripeAccountId, FirstName, LastName, Email FROM Users WHERE ID = ?");
    $stmt->execute([$djId]);
    $dj = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dj) {
        throw new Exception('DJ not found');
    }
    
    $stripe = StripeConfig::getStripeClient();
    
    if ($dj['StripeAccountId']) {
        // Account exists, return status
        $account = $stripe->retrieveAccount($dj['StripeAccountId']);
        
        echo json_encode([
            'success' => true,
            'accountExists' => true,
            'accountId' => $dj['StripeAccountId'],
            'detailsSubmitted' => $account['details_submitted'],
            'chargesEnabled' => $account['charges_enabled']
        ]);
    } else {
        // Create new Stripe Express account
        $accountData = [
            'type' => 'express',
            'country' => 'US',
            'email' => $dj['Email'],
            'capabilities' => json_encode([
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true]
            ])
        ];
        
        $account = $stripe->createAccount($accountData);
        
        // Save account ID to database
        $stmt = $pdo->prepare("UPDATE Users SET StripeAccountId = ? WHERE ID = ?");
        $stmt->execute([$account['id'], $djId]);
        
        // Create account link for onboarding
        $returnUrl = 'http://localhost/CrowdConnect/dj-dashboard.html?stripe=success';
        $refreshUrl = 'http://localhost/CrowdConnect/dj-dashboard.html?stripe=reauth';
        
        $accountLink = $stripe->createAccountLink($account['id'], $returnUrl, $refreshUrl);
        
        echo json_encode([
            'success' => true,
            'accountExists' => false,
            'accountId' => $account['id'],
            'onboardingUrl' => $accountLink['url']
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>