<?php
// LAMPAPI/CreateConsolidatedPayment.php - New consolidated payment system

// Suppress all errors to prevent JSON corruption
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Clean any output buffer
if (ob_get_level()) {
    ob_end_clean();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    require_once 'StripeConfig.php';
    
    $rawInput = file_get_contents('php://input');
    error_log("CreateConsolidatedPayment - Raw input: " . $rawInput);
    
    $input = json_decode($rawInput, true);
    
    if ($input === null) {
        throw new Exception('Invalid JSON input');
    }
    
    error_log("CreateConsolidatedPayment - Parsed input: " . json_encode($input));
    
    // Check for missing required fields
    $missingFields = [];
    if (!isset($input['requestId'])) $missingFields[] = 'requestId';
    if (!isset($input['requestFee'])) $missingFields[] = 'requestFee';
    if (!isset($input['tipAmount'])) $missingFields[] = 'tipAmount';
    
    // If requestId is 0, we need additional fields to create the request
    if (isset($input['requestId']) && (int)$input['requestId'] == 0) {
        if (!isset($input['partyId'])) $missingFields[] = 'partyId';
        if (!isset($input['songName'])) $missingFields[] = 'songName';
        if (!isset($input['requestedBy'])) $missingFields[] = 'requestedBy';
    }
    
    if (!empty($missingFields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missingFields));
    }
    
    $requestId = (int)$input['requestId'];
    $requestFee = (float)$input['requestFee'];
    $tipAmount = (float)$input['tipAmount'];
    $customerId = isset($input['customerId']) ? (int)$input['customerId'] : null;
    
    // Validate minimum amounts
    if ($tipAmount < 0) {
        throw new Exception('Tip amount cannot be negative');
    }
    
    // Calculate total amount to charge customer
    $totalCharged = $requestFee + $tipAmount;
    
    if ($totalCharged < 0.50) {
        throw new Exception('Total amount must be at least $0.50');
    }
    
    // OFFICIAL CROWDCONNECT FEE STRUCTURE
    // 1. Platform Revenue: 5% of total transaction amount
    $platformFee = round($totalCharged * 0.05, 2);
    
    // 2. Stripe Processing Fee: 2.9% + $0.30 per transaction
    $stripeFee = round(($totalCharged * 0.029) + 0.30, 2);
    
    // 3. Total fees deducted from customer payment
    $totalProcessingFee = $stripeFee + $platformFee;
    
    // 4. DJ Net Earnings: Total - Platform(5%) - Stripe(2.9% + $0.30)
    $djNetEarnings = $totalCharged - $totalProcessingFee;
    
    // Platform revenue (5% of total transaction)
    $platformRevenue = $platformFee;
    
    // Database connection
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Handle request creation or retrieval
    if ($requestId == 0) {
        // Create new request record (payment-first flow)
        $partyId = (int)$input['partyId'];
        $songName = $input['songName'];
        $requestedBy = $input['requestedBy'];
        
        // Get DJ ID from party
        $partyStmt = $conn->prepare("SELECT DJId FROM Parties WHERE PartyId = ?");
        $partyStmt->bind_param("i", $partyId);
        $partyStmt->execute();
        $partyResult = $partyStmt->get_result();
        $party = $partyResult->fetch_assoc();
        
        if (!$party) {
            throw new Exception('Party not found');
        }
        
        $djId = $party['DJId'];
        
        // Create the request record
        $insertStmt = $conn->prepare("INSERT INTO Requests (PartyId, DJUserID, SongName, RequestedBy, PaymentStatus) VALUES (?, ?, ?, ?, 'pending')");
        $insertStmt->bind_param("iiss", $partyId, $djId, $songName, $requestedBy);
        
        if (!$insertStmt->execute()) {
            throw new Exception("Failed to create request record: " . $insertStmt->error);
        }
        
        $requestId = $conn->insert_id;
        $insertStmt->close();
        $partyStmt->close();
        
        $request = [
            'RequestId' => $requestId,
            'DJUserID' => $djId,
            'SongName' => $songName,
            'RequestedBy' => $requestedBy
        ];
    } else {
        // Get existing request info and validate
        $stmt = $conn->prepare("SELECT RequestId, DJUserID, SongName, RequestedBy FROM Requests WHERE RequestId = ?");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        
        if (!$request) {
            throw new Exception('Request not found');
        }
        
        $djId = $request['DJUserID'];
        $stmt->close();
    }
    
    // Create Stripe payment intent
    $stripe = StripeConfig::getStripeClient();
    $amountInCents = (int)round($totalCharged * 100);
    
    $metadata = [
        'request_id' => $requestId,
        'dj_id' => $djId,
        'request_fee' => $requestFee,
        'tip_amount' => $tipAmount,
        'song_name' => $request['SongName']
    ];
    
    if ($customerId) {
        $metadata['customer_id'] = $customerId;
    }
    
    $paymentIntent = $stripe->createPaymentIntent($amountInCents, 'usd', $metadata);
    
    // Update the Requests table with payment information
    $updateStmt = $conn->prepare("
        UPDATE Requests 
        SET 
            PriceOfRequest = ?,
            TipAmount = ?,
            TotalCharged = ?,
            ProcessingFee = ?,
            TotalCollected = ?,
            PlatformRevenue = ?,
            PaymentStatus = 'pending',
            StripePaymentIntentId = ?
        WHERE RequestId = ?
    ");
    
    // Debug the values being bound
    error_log("CreateConsolidatedPayment - Binding values:");
    error_log("RequestFee: $requestFee (" . gettype($requestFee) . ")");
    error_log("TipAmount: $tipAmount (" . gettype($tipAmount) . ")");
    error_log("TotalCharged: $totalCharged (" . gettype($totalCharged) . ")");
    error_log("TotalProcessingFee: $totalProcessingFee (" . gettype($totalProcessingFee) . ")");
    error_log("DjNetEarnings: $djNetEarnings (" . gettype($djNetEarnings) . ")");
    error_log("PlatformRevenue: $platformRevenue (" . gettype($platformRevenue) . ")");
    error_log("PaymentIntentId: " . $paymentIntent['id'] . " (" . gettype($paymentIntent['id']) . ")");
    error_log("RequestId: $requestId (" . gettype($requestId) . ")");
    
    $updateStmt->bind_param("ddddddsi", 
        $requestFee, 
        $tipAmount, 
        $totalCharged, 
        $totalProcessingFee, 
        $djNetEarnings, 
        $platformRevenue, 
        $paymentIntent['id'], 
        $requestId
    );
    
    if (!$updateStmt->execute()) {
        error_log("CreateConsolidatedPayment - UPDATE failed: " . $updateStmt->error);
        throw new Exception("Failed to update request with payment info: " . $updateStmt->error);
    } else {
        error_log("CreateConsolidatedPayment - UPDATE successful for RequestId: $requestId");
    }
    
    $conn->close();
    
    // Return payment intent for frontend
    echo json_encode([
        'success' => true,
        'clientSecret' => $paymentIntent['client_secret'],
        'paymentIntentId' => $paymentIntent['id'],
        'requestId' => $requestId,
        'calculation' => [
            'totalCharged' => $totalCharged,
            'stripeFee' => $stripeFee,
            'platformFee' => $platformFee,
            'totalProcessingFee' => $totalProcessingFee,
            'djNetEarnings' => $djNetEarnings,
            'platformRevenue' => $platformRevenue
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    http_response_code(400);
    error_log("CreateConsolidatedPayment error: " . $e->getMessage());
    error_log("CreateConsolidatedPayment stack trace: " . $e->getTraceAsString());
    echo json_encode(['error' => $e->getMessage()]);
}
?>