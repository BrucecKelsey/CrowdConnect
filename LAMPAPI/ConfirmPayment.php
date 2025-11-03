<?php
require_once 'StripeConfig.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $status = $paymentIntent['status'] === 'succeeded' ? 'completed' : 'failed';
    
    $stmt = $conn->prepare("UPDATE Tips SET Status = ?, Timestamp = NOW() WHERE StripePaymentIntentId = ?");
    $stmt->bind_param("ss", $status, $paymentIntentId);
    $stmt->execute();
    
    $rowsAffected = $stmt->affected_rows;
    error_log("ConfirmPayment: Updated " . $rowsAffected . " tip records to status '" . $status . "' for PaymentIntent: " . $paymentIntentId);
    
    // If payment succeeded, update Users.TotalEarnings and create earnings history record
    if ($status === 'completed' && $rowsAffected > 0) {
        // Get the tip details for earnings history
        $tipStmt = $conn->prepare("SELECT TipId, DJUserID, TipAmount FROM Tips WHERE StripePaymentIntentId = ?");
        $tipStmt->bind_param("s", $paymentIntentId);
        $tipStmt->execute();
        $tipResult = $tipStmt->get_result();
        
        if ($tip = $tipResult->fetch_assoc()) {
            $grossAmount = $tip['TipAmount'];
            $stripeFeeAmount = round(($grossAmount * 0.029) + 0.30, 2); // Stripe fee: 2.9% + $0.30
            $netAmount = $grossAmount - $stripeFeeAmount;
            
            // Get the charge ID from Stripe payment intent
            $chargeId = isset($paymentIntent['charges']['data'][0]['id']) ? $paymentIntent['charges']['data'][0]['id'] : $paymentIntentId;
            
            // Insert into EarningsHistory
            $earningsStmt = $conn->prepare("
                INSERT INTO EarningsHistory (UserId, TipId, GrossAmount, StripeFeeAmount, NetAmount, StripeChargeId, Status) 
                VALUES (?, ?, ?, ?, ?, ?, 'completed')
            ");
            $earningsStmt->bind_param("iiddds", $tip['DJUserID'], $tip['TipId'], $grossAmount, $stripeFeeAmount, $netAmount, $chargeId);
            
            if ($earningsStmt->execute()) {
                error_log("ConfirmPayment: Created earnings history record for TipId: " . $tip['TipId']);
            } else {
                error_log("ConfirmPayment: Failed to create earnings history: " . $earningsStmt->error);
            }
            $earningsStmt->close();
            
            // Update Users.TotalEarnings
            $userUpdateStmt = $conn->prepare("UPDATE Users SET TotalEarnings = TotalEarnings + ? WHERE ID = ?");
            $userUpdateStmt->bind_param("di", $grossAmount, $tip['DJUserID']);
            
            if ($userUpdateStmt->execute()) {
                error_log("ConfirmPayment: Updated TotalEarnings for User ID: " . $tip['DJUserID'] . " (+$" . $grossAmount . ")");
            } else {
                error_log("ConfirmPayment: Failed to update TotalEarnings: " . $userUpdateStmt->error);
            }
            $userUpdateStmt->close();
        }
        $tipStmt->close();
    }
    
    if ($status === 'completed') {
        echo json_encode([
            'success' => true,
            'status' => 'completed',
            'message' => 'Payment confirmed successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'status' => 'failed',
            'error' => 'Payment was not successful'
        ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>