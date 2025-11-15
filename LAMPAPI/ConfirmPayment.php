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
    
    error_log("ConfirmPayment.php called with input: " . json_encode($input));
    
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
    
    // Check if this payment is in the Requests table (consolidated system) or needs to be created (payment-first flow)
    $requestStmt = $conn->prepare("SELECT RequestId, DJUserID, PriceOfRequest, TipAmount, TotalCharged, ProcessingFee, TotalCollected, PlatformRevenue FROM Requests WHERE StripePaymentIntentId = ?");
    $requestStmt->bind_param("s", $paymentIntentId);
    $requestStmt->execute();
    $requestResult = $requestStmt->get_result();
    
    if ($request = $requestResult->fetch_assoc()) {
        // Handle consolidated payment system (Requests table)
        error_log("DEBUG: Found request record: " . json_encode($request));
        
        $updateStmt = $conn->prepare("UPDATE Requests SET PaymentStatus = ?, ProcessedAt = NOW() WHERE StripePaymentIntentId = ?");
        $updateStmt->bind_param("ss", $status, $paymentIntentId);
        $updateStmt->execute();
        
        $rowsAffected = $updateStmt->affected_rows;
        error_log("ConfirmPayment (Requests): Updated " . $rowsAffected . " request records to status '" . $status . "' for PaymentIntent: " . $paymentIntentId);
        
        if ($status === 'completed' && $rowsAffected > 0) {
            // Handle earnings using the NEW FEE STRUCTURE
            $totalCollected = (float)$request['TotalCollected'];  // DJ's net earnings (already calculated)
            $totalCharged = (float)$request['TotalCharged'];      // Total amount charged to customer
            
            error_log("DEBUG: TotalCollected = '$totalCollected', TotalCharged = '$totalCharged'");
            error_log("DEBUG: TotalCollected > 0? " . ($totalCollected > 0 ? 'YES' : 'NO'));
            
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
                error_log("ConfirmPayment (Requests): No earnings for RequestId: " . $request['RequestId'] . " - TotalCollected=$totalCollected (should be > 0)");
            }
        }
        
        $updateStmt->close();
        
    } else {
        // Handle payment-first flow: create request record from Stripe metadata
        if ($status === 'completed' && isset($paymentIntent['metadata']['party_id'])) {
            error_log("ConfirmPayment: Creating request record from payment metadata");
            
            // Extract data from Stripe metadata
            $partyId = (int)$paymentIntent['metadata']['party_id'];
            $djId = (int)$paymentIntent['metadata']['dj_id'];
            $songName = $paymentIntent['metadata']['song_name'];
            $requestedBy = $paymentIntent['metadata']['requested_by'];
            $requestFee = (float)$paymentIntent['metadata']['request_fee'];
            $tipAmount = (float)$paymentIntent['metadata']['tip_amount'];
            
            // Calculate fee structure
            $totalCharged = $requestFee + $tipAmount;
            $platformFee = round($totalCharged * 0.05, 2);
            $stripeFee = round(($totalCharged * 0.029) + 0.30, 2);
            $totalProcessingFee = $stripeFee + $platformFee;
            $djNetEarnings = $totalCharged - $totalProcessingFee;
            $platformRevenue = $platformFee;
            
            // Create the request record
            $insertStmt = $conn->prepare("
                INSERT INTO Requests (PartyId, DJUserID, SongName, RequestedBy, PriceOfRequest, TipAmount, TotalCharged, ProcessingFee, TotalCollected, PlatformRevenue, PaymentStatus, StripePaymentIntentId, ProcessedAt) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())
            ");
            
            $insertStmt->bind_param("iissdddddds", $partyId, $djId, $songName, $requestedBy, $requestFee, $tipAmount, $totalCharged, $totalProcessingFee, $djNetEarnings, $platformRevenue, $paymentIntentId);
            
            if ($insertStmt->execute()) {
                $requestId = $conn->insert_id;
                error_log("ConfirmPayment: Created request record with ID: $requestId");
                
                // Create earnings history record
                $chargeId = isset($paymentIntent['charges']['data'][0]['id']) ? $paymentIntent['charges']['data'][0]['id'] : $paymentIntentId;
                $earningsStmt = $conn->prepare("
                    INSERT INTO EarningsHistory (UserId, RequestId, GrossAmount, StripeFeeAmount, DJAmount, NetAmount, StripeChargeId, Status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
                ");
                
                $earningsStmt->bind_param("iidddds", $djId, $requestId, $totalCharged, $stripeFee, $djNetEarnings, $platformRevenue, $chargeId);
                
                if ($earningsStmt->execute()) {
                    error_log("ConfirmPayment: Created earnings history record");
                } else {
                    error_log("ConfirmPayment: Failed to create earnings history: " . $earningsStmt->error);
                }
                $earningsStmt->close();
                
                // Update DJ's total earnings
                $userUpdateStmt = $conn->prepare("UPDATE Users SET TotalEarnings = TotalEarnings + ?, AvailableFunds = AvailableFunds + ? WHERE ID = ?");
                $userUpdateStmt->bind_param("ddi", $djNetEarnings, $djNetEarnings, $djId);
                
                if ($userUpdateStmt->execute()) {
                    error_log("ConfirmPayment: Updated DJ earnings (+$" . $djNetEarnings . ") for User ID: " . $djId);
                } else {
                    error_log("ConfirmPayment: Failed to update user earnings: " . $userUpdateStmt->error);
                }
                $userUpdateStmt->close();
                
            } else {
                error_log("ConfirmPayment: Failed to create request record: " . $insertStmt->error);
            }
            $insertStmt->close();
            
        } else {
            // Try legacy Tips table as fallback
            $tipStmt = $conn->prepare("UPDATE Tips SET Status = ?, Timestamp = NOW() WHERE StripePaymentIntentId = ?");
            $tipStmt->bind_param("ss", $status, $paymentIntentId);
            $tipStmt->execute();
            
            $rowsAffected = $tipStmt->affected_rows;
            error_log("ConfirmPayment (Tips): Updated " . $rowsAffected . " tip records to status '" . $status . "' for PaymentIntent: " . $paymentIntentId);
        }
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
    error_log("ConfirmPayment.php ERROR: " . $e->getMessage());
    error_log("ConfirmPayment.php Stack trace: " . $e->getTraceAsString());
    if (isset($conn)) {
        $conn->close();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>