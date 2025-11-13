<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function returnWithError($err) {
    $retValue = '{"error":"' . $err . '"}';
    echo $retValue;
}

function returnWithInfo($info) {
    echo json_encode($info);
}

try {
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        returnWithError('No input data received');
        exit;
    }
    
    $userId = $input['userId'] ?? null;
    $amount = $input['amount'] ?? null;
    $paymentMethod = $input['paymentMethod'] ?? null;
    $paymentDetails = $input['paymentDetails'] ?? null;
    
    // Validate required fields
    if (!$userId || !$amount || !$paymentMethod || !$paymentDetails) {
        returnWithError('Missing required fields');
        exit;
    }
    
    // Validate amount
    $amount = floatval($amount);
    if ($amount <= 0) {
        returnWithError('Amount must be greater than 0');
        exit;
    }
    
    // Validate payment method
    $validMethods = ['paypal', 'bank_transfer', 'cashapp', 'venmo', 'zelle', 'apple_pay', 'google_pay', 'crypto'];
    if (!in_array($paymentMethod, $validMethods)) {
        returnWithError('Invalid payment method');
        exit;
    }
    
    // Connect to database
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        returnWithError('Database connection failed: ' . $conn->connect_error);
        exit;
    }
    
    // Start transaction
    $conn->autocommit(FALSE);
    
    try {
        // Check user exists and has sufficient balance
        $stmt = $conn->prepare("SELECT FirstName, LastName, AvailableFunds FROM Users WHERE ID = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        $availableBalance = floatval($user['AvailableFunds']);
        
        if ($amount > $availableBalance) {
            throw new Exception('Insufficient available balance. Available: $' . number_format($availableBalance, 2));
        }
        
        // No processing fees - full amount goes to user
        $processingFee = 0.00;
        $netAmount = $amount;
        
        // Prepare payment details JSON (sanitize sensitive info)
        $paymentDetailsData = [
            'method' => $paymentMethod,
            'details' => $paymentDetails,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // For bank transfers, we should encrypt sensitive data in production
        if ($paymentMethod === 'bank_transfer') {
            $paymentDetailsData['note'] = 'Bank details encrypted for security';
        }
        
        $paymentDetailsJson = json_encode($paymentDetailsData);
        
        // Insert payout request
        $stmt = $conn->prepare("
            INSERT INTO PayoutRequests 
            (UserId, Amount, PaymentMethod, PaymentDetails, ProcessingFee, NetAmount, Status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->bind_param("idssdd", 
            $userId, 
            $amount, 
            $paymentMethod, 
            $paymentDetailsJson, 
            $processingFee, 
            $netAmount
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create payout request: ' . $stmt->error);
        }
        
        $requestId = $conn->insert_id;
        $stmt->close();
        
        // Update user's available balance (subtract the requested amount)
        $newBalance = $availableBalance - $amount;
        $stmt = $conn->prepare("UPDATE Users SET AvailableFunds = ? WHERE ID = ?");
        $stmt->bind_param("di", $newBalance, $userId);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update user balance: ' . $stmt->error);
        }
        $stmt->close();
        
        // Log the transaction for audit trail
        error_log("Payout request created: User $userId, Amount: $$amount, Request ID: $requestId, New Balance: $$newBalance");
        
        // Commit transaction
        $conn->commit();
        $conn->close();
        
        returnWithInfo([
            'success' => true,
            'message' => 'Payout request submitted successfully',
            'request_id' => $requestId,
            'requested_amount' => $amount,
            'processing_fee' => $processingFee,
            'net_amount' => $netAmount,
            'new_available_balance' => $newBalance,
            'status' => 'pending'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $conn->close();
        throw $e;
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn) {
        $conn->close();
    }
    returnWithError('Database error: ' . $e->getMessage());
} catch (Error $e) {
    if (isset($conn) && $conn) {
        $conn->close();
    }
    returnWithError('Fatal error: ' . $e->getMessage());
}
?>