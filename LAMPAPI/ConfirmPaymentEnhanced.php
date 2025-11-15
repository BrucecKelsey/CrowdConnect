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
    
    // Retrieve the PaymentIntent from Stripe
    $stripe = new \Stripe\StripeClient($stripeSecretKey);
    $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntentId);
    
    if (!$paymentIntent) {
        throw new Exception('Payment intent not found');
    }
    
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $status = ($paymentIntent->status === 'succeeded') ? 'completed' : 'failed';
    
    // Update both Tips table (for backward compatibility) and Requests table
    $stmt = $conn->prepare("UPDATE Tips SET Status = ? WHERE StripePaymentIntentId = ?");
    $stmt->bind_param("ss", $status, $paymentIntentId);
    $stmt->execute();
    $tipsRowsAffected = $stmt->affected_rows;
    $stmt->close();
    
    // Update Requests table with payment details
    $requestStmt = $conn->prepare("
        UPDATE Requests 
        SET PaymentStatus = ?, 
            ProcessedAt = NOW(),
            StripePaymentIntentId = ?
        WHERE StripePaymentIntentId = ?
    ");
    $requestStmt->bind_param("sss", $status, $paymentIntentId, $paymentIntentId);
    $requestStmt->execute();
    $requestsRowsAffected = $requestStmt->affected_rows;
    $requestStmt->close();
    
    // If payment succeeded, process earnings for both Tips and Requests
    if ($status === 'completed') {
        
        // Handle Tips table (legacy support)
        if ($tipsRowsAffected > 0) {
            $tipStmt = $conn->prepare("SELECT TipId, DJUserID, TipAmount FROM Tips WHERE StripePaymentIntentId = ?");
            $tipStmt->bind_param("s", $paymentIntentId);
            $tipStmt->execute();
            $tipResult = $tipStmt->get_result();
            
            if ($tip = $tipResult->fetch_assoc()) {
                $grossAmount = $tip['TipAmount'];
                $processingFeeAmount = round(($grossAmount * 0.029) + 0.30, 2);
                $netAmount = $grossAmount - $processingFeeAmount;
                
                // Get charge ID from Stripe
                $chargeId = isset($paymentIntent['charges']['data'][0]['id']) ? $paymentIntent['charges']['data'][0]['id'] : $paymentIntentId;
                
                // Insert into EarningsHistory
                $earningsStmt = $conn->prepare("
                    INSERT INTO EarningsHistory (UserId, TipId, GrossAmount, StripeFeeAmount, NetAmount, StripeChargeId, Status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'completed')
                ");
                $earningsStmt->bind_param("iiddds", $tip['DJUserID'], $tip['TipId'], $grossAmount, $processingFeeAmount, $netAmount, $chargeId);
                $earningsStmt->execute();
                $earningsStmt->close();
                
                // Update Users earnings
                $userUpdateStmt = $conn->prepare("UPDATE Users SET TotalEarnings = TotalEarnings + ?, AvailableFunds = AvailableFunds + ? WHERE ID = ?");
                $userUpdateStmt->bind_param("ddi", $grossAmount, $netAmount, $tip['DJUserID']);
                $userUpdateStmt->execute();
                $userUpdateStmt->close();
                
                error_log("ConfirmPayment: Processed tip payment - User: " . $tip['DJUserID'] . ", Gross: $" . $grossAmount . ", Net: $" . $netAmount);
            }
            $tipStmt->close();
        }
        
        // Handle Requests table (new consolidated system)
        if ($requestsRowsAffected > 0) {
            $requestStmt = $conn->prepare("
                SELECT RequestId, DJUserID, PriceOfRequest, TipAmount, TotalCharged 
                FROM Requests 
                WHERE StripePaymentIntentId = ?
            ");
            $requestStmt->bind_param("s", $paymentIntentId);
            $requestStmt->execute();
            $requestResult = $requestStmt->get_result();
            
            if ($request = $requestResult->fetch_assoc()) {
                $requestFee = floatval($request['PriceOfRequest']);
                $tipAmount = floatval($request['TipAmount']);
                $totalCharged = floatval($request['TotalCharged']);
                
                // Calculate fees (only on tip portion, not request fee)
                $processingFee = $tipAmount > 0 ? round(($tipAmount * 0.029) + 0.30, 2) : 0.00;
                $djEarnings = $tipAmount - $processingFee; // DJ gets tip minus processing fee
                $platformRevenue = $requestFee + $processingFee; // Platform gets request fee + processing fee profit
                
                // Update the request with calculated values
                $updateRequestStmt = $conn->prepare("
                    UPDATE Requests 
                    SET ProcessingFee = ?, 
                        TotalCollected = ?, 
                        PlatformRevenue = ?
                    WHERE RequestId = ?
                ");
                $updateRequestStmt->bind_param("dddi", $processingFee, $djEarnings, $platformRevenue, $request['RequestId']);
                $updateRequestStmt->execute();
                $updateRequestStmt->close();
                
                // Update Users earnings (only if there's a tip)
                if ($tipAmount > 0 && $djEarnings > 0) {
                    $userUpdateStmt = $conn->prepare("
                        UPDATE Users 
                        SET TotalEarnings = TotalEarnings + ?, 
                            AvailableFunds = AvailableFunds + ? 
                        WHERE ID = ?
                    ");
                    $userUpdateStmt->bind_param("ddi", $tipAmount, $djEarnings, $request['DJUserID']);
                    $userUpdateStmt->execute();
                    $userUpdateStmt->close();
                }
                
                // Insert into EarningsHistory (only if there's earnings for DJ)
                if ($djEarnings > 0) {
                    $chargeId = isset($paymentIntent['charges']['data'][0]['id']) ? $paymentIntent['charges']['data'][0]['id'] : $paymentIntentId;
                    
                    $earningsStmt = $conn->prepare("
                        INSERT INTO EarningsHistory 
                        (UserId, RequestId, GrossAmount, StripeFeeAmount, NetAmount, StripeChargeId, Status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'completed')
                    ");
                    $earningsStmt->bind_param("iiddds", 
                        $request['DJUserID'], 
                        $request['RequestId'], 
                        $tipAmount, 
                        $processingFee, 
                        $djEarnings, 
                        $chargeId
                    );
                    $earningsStmt->execute();
                    $earningsStmt->close();
                }
                
                error_log("ConfirmPayment: Processed request payment - RequestId: " . $request['RequestId'] . 
                         ", Request Fee: $" . $requestFee . 
                         ", Tip: $" . $tipAmount . 
                         ", DJ Earnings: $" . $djEarnings . 
                         ", Platform Revenue: $" . $platformRevenue);
            }
            $requestStmt->close();
        }
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