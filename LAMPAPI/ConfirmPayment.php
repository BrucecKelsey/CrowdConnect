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
    
    // Update payment record in database
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $status = $paymentIntent['status'] === 'succeeded' ? 'completed' : 'failed';
    
    // Check if this payment is in the Requests table (consolidated system) or Tips table (legacy)
    $requestStmt = $conn->prepare("SELECT RequestId, DJUserID, PriceOfRequest, TipAmount, TotalCharged, ProcessingFee FROM Requests WHERE StripePaymentIntentId = ?");
    $requestStmt->bind_param("s", $paymentIntentId);
    $requestStmt->execute();
    $requestResult = $requestStmt->get_result();
    
    if ($request = $requestResult->fetch_assoc()) {
        // Handle consolidated payment system (Requests table)
        $updateStmt = $conn->prepare("UPDATE Requests SET PaymentStatus = ?, ProcessedAt = NOW() WHERE StripePaymentIntentId = ?");
        $updateStmt->bind_param("ss", $status, $paymentIntentId);
        $updateStmt->execute();
        
        $rowsAffected = $updateStmt->affected_rows;
        error_log("ConfirmPayment (Requests): Updated " . $rowsAffected . " request records to status '" . $status . "' for PaymentIntent: " . $paymentIntentId);
        
        if ($status === 'completed' && $rowsAffected > 0) {
            // Handle earnings - only tips go to DJ, request fees go to platform
            $tipAmount = $request['TipAmount'];
            
            if ($tipAmount > 0) {
                // Calculate DJ's net earnings from tips (processing fee already stored)
                $processingFee = $request['ProcessingFee'];
                $djNetAmount = $tipAmount - $processingFee;
                
                // Get the charge ID from Stripe payment intent
                $chargeId = isset($paymentIntent['charges']['data'][0]['id']) ? $paymentIntent['charges']['data'][0]['id'] : $paymentIntentId;
                
                // Insert into EarningsHistory for the tip portion
                $earningsStmt = $conn->prepare("
                    INSERT INTO EarningsHistory (UserId, RequestId, GrossAmount, StripeFeeAmount, NetAmount, StripeChargeId, Status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'completed')
                ");
                $earningsStmt->bind_param("iiddds", $request['DJUserID'], $request['RequestId'], $tipAmount, $processingFee, $djNetAmount, $chargeId);
                
                if ($earningsStmt->execute()) {
                    error_log("ConfirmPayment (Requests): Created earnings history record for RequestId: " . $request['RequestId']);
                } else {
                    error_log("ConfirmPayment (Requests): Failed to create earnings history: " . $earningsStmt->error);
                }
                $earningsStmt->close();
                
                // Update Users.TotalEarnings (gross tip) and AvailableFunds (net after fees)
                $userUpdateStmt = $conn->prepare("UPDATE Users SET TotalEarnings = TotalEarnings + ?, AvailableFunds = AvailableFunds + ? WHERE ID = ?");
                $userUpdateStmt->bind_param("ddi", $tipAmount, $djNetAmount, $request['DJUserID']);
                
                if ($userUpdateStmt->execute()) {
                    error_log("ConfirmPayment (Requests): Updated TotalEarnings (+$" . $tipAmount . " gross) and AvailableFunds (+$" . $djNetAmount . " net) for User ID: " . $request['DJUserID'] . " - Processing fee: $" . $processingFee . "");
                } else {
                    error_log("ConfirmPayment (Requests): Failed to update user earnings: " . $userUpdateStmt->error);
                }
                $userUpdateStmt->close();
            } else {
                error_log("ConfirmPayment (Requests): No tip amount for RequestId: " . $request['RequestId'] . " - request fee goes to platform");
            }
        }
        
        $updateStmt->close();
        
    } else {
        // Handle legacy Tips table
        $tipStmt = $conn->prepare("UPDATE Tips SET Status = ?, Timestamp = NOW() WHERE StripePaymentIntentId = ?");
        $tipStmt->bind_param("ss", $status, $paymentIntentId);
        $tipStmt->execute();
        
        $rowsAffected = $tipStmt->affected_rows;
        error_log("ConfirmPayment (Tips): Updated " . $rowsAffected . " tip records to status '" . $status . "' for PaymentIntent: " . $paymentIntentId);
        
        // If payment succeeded, update Users.TotalEarnings and create earnings history record
        if ($status === 'completed' && $rowsAffected > 0) {
            // Get the tip details for earnings history
            $tipDetailsStmt = $conn->prepare("SELECT TipId, DJUserID, TipAmount FROM Tips WHERE StripePaymentIntentId = ?");
            $tipDetailsStmt->bind_param("s", $paymentIntentId);
            $tipDetailsStmt->execute();
            $tipDetailsResult = $tipDetailsStmt->get_result();
            
            if ($tip = $tipDetailsResult->fetch_assoc()) {
                $grossAmount = $tip['TipAmount'];
                $processingFeeAmount = round(($grossAmount * 0.075) + 0.30, 2); // Processing fee: 7.5% + $0.30
                $netAmount = $grossAmount - $processingFeeAmount;
                
                // Get the charge ID from Stripe payment intent
                $chargeId = isset($paymentIntent['charges']['data'][0]['id']) ? $paymentIntent['charges']['data'][0]['id'] : $paymentIntentId;
                
                // Insert into EarningsHistory
                $earningsStmt = $conn->prepare("
                    INSERT INTO EarningsHistory (UserId, TipId, GrossAmount, StripeFeeAmount, NetAmount, StripeChargeId, Status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'completed')
                ");
                $earningsStmt->bind_param("iiddds", $tip['DJUserID'], $tip['TipId'], $grossAmount, $processingFeeAmount, $netAmount, $chargeId);
                
                if ($earningsStmt->execute()) {
                    error_log("ConfirmPayment (Tips): Created earnings history record for TipId: " . $tip['TipId']);
                } else {
                    error_log("ConfirmPayment (Tips): Failed to create earnings history: " . $earningsStmt->error);
                }
                $earningsStmt->close();
                
                // Update Users.TotalEarnings (gross tip amount) and AvailableFunds (net amount after fees)
                $userUpdateStmt = $conn->prepare("UPDATE Users SET TotalEarnings = TotalEarnings + ?, AvailableFunds = AvailableFunds + ? WHERE ID = ?");
                $userUpdateStmt->bind_param("ddi", $grossAmount, $netAmount, $tip['DJUserID']);
                
                if ($userUpdateStmt->execute()) {
                    error_log("ConfirmPayment (Tips): Updated TotalEarnings (+$" . $grossAmount . " gross) and AvailableFunds (+$" . $netAmount . " net) for User ID: " . $tip['DJUserID'] . " - Processing fee: $" . $processingFeeAmount . "");
                } else {
                    error_log("ConfirmPayment (Tips): Failed to update user earnings: " . $userUpdateStmt->error);
                }
                $userUpdateStmt->close();
            }
            $tipDetailsStmt->close();
        }
        
        $tipStmt->close();
    }
    
    $requestStmt->close();
    
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