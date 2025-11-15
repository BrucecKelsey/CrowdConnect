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
            // Handle earnings using the NEW FEE STRUCTURE
            $totalCollected = (float)$request['TotalCollected'];  // DJ's net earnings (already calculated)
            $totalCharged = (float)$request['TotalCharged'];      // Total amount charged to customer
            
            if ($totalCollected > 0) {
                // Get the charge ID from Stripe payment intent
                $chargeId = isset($paymentIntent['charges']['data'][0]['id']) ? $paymentIntent['charges']['data'][0]['id'] : $paymentIntentId;
                
                // Insert into EarningsHistory with new structure
                $earningsStmt = $conn->prepare("
                    INSERT INTO EarningsHistory (UserId, RequestId, GrossAmount, StripeFeeAmount, DJAmount, NetAmount, StripeChargeId, Status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
                ");
                
                $grossAmount = $totalCharged;  // Total amount customer paid
                $platformRevenue = (float)$request['PlatformRevenue'];  // Platform's 5% cut
                $djAmount = $totalCollected;  // DJ's earnings
                
                // Calculate Stripe fee (Total - DJ - Platform = Stripe fee)
                $stripeFee = $grossAmount - $djAmount - $platformRevenue;
                
                $earningsStmt->bind_param("iidddds", $request['DJUserID'], $request['RequestId'], $grossAmount, $stripeFee, $djAmount, $platformRevenue, $chargeId);
                
                if ($earningsStmt->execute()) {
                    error_log("ConfirmPayment (Requests): Created earnings history record for RequestId: " . $request['RequestId']);
                } else {
                    error_log("ConfirmPayment (Requests): Failed to create earnings history: " . $earningsStmt->error);
                }
                $earningsStmt->close();
                
                // Update Users earnings - NEW FEE STRUCTURE
                // TotalEarnings = cumulative gross earnings, AvailableFunds = cumulative net earnings
                $userUpdateStmt = $conn->prepare("UPDATE Users SET TotalEarnings = TotalEarnings + ?, AvailableFunds = AvailableFunds + ? WHERE ID = ?");
                $userUpdateStmt->bind_param("ddi", $totalCollected, $totalCollected, $request['DJUserID']);
                
                if ($userUpdateStmt->execute()) {
                    error_log("ConfirmPayment (Requests): Updated TotalEarnings and AvailableFunds (+$" . $totalCollected . ") for User ID: " . $request['DJUserID'] . " - Customer paid: $" . $totalCharged . "");
                } else {
                    error_log("ConfirmPayment (Requests): Failed to update user earnings: " . $userUpdateStmt->error);
                }
                $userUpdateStmt->close();
            } else {
                error_log("ConfirmPayment (Requests): No earnings for RequestId: " . $request['RequestId'] . " - free request");
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
                
                // OFFICIAL CROWDCONNECT FEE STRUCTURE
                // Platform Revenue: 5% of total transaction
                $platformRevenue = round($grossAmount * 0.05, 2);
                
                // Stripe Processing Fee: 2.9% + $0.30
                $stripeFee = round(($grossAmount * 0.029) + 0.30, 2);
                
                // DJ Earnings: Total - Platform(5%) - Stripe(2.9% + $0.30)
                $djAmount = $grossAmount - $platformRevenue - $stripeFee;
                
                // Get the charge ID from Stripe payment intent
                $chargeId = isset($paymentIntent['charges']['data'][0]['id']) ? $paymentIntent['charges']['data'][0]['id'] : $paymentIntentId;
                
                // Insert into EarningsHistory with official fee structure
                $earningsStmt = $conn->prepare("
                    INSERT INTO EarningsHistory (UserId, TipId, GrossAmount, StripeFeeAmount, DJAmount, NetAmount, StripeChargeId, Status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
                ");
                $earningsStmt->bind_param("iidddds", $tip['DJUserID'], $tip['TipId'], $grossAmount, $stripeFee, $djAmount, $platformRevenue, $chargeId);
                
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