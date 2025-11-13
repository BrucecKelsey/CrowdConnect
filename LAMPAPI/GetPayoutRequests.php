<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
    // Get userId from query parameter or POST data
    $userId = $_GET['userId'] ?? null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['userId'] ?? $userId;
    }
    
    if (!$userId) {
        returnWithError('User ID is required');
        exit;
    }
    
    // Connect to database
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        returnWithError('Database connection failed: ' . $conn->connect_error);
        exit;
    }
    
    // Get payout requests for the user (last 50 requests)
    $stmt = $conn->prepare("
        SELECT 
            RequestId,
            Amount,
            PaymentMethod,
            PaymentDetails,
            Status,
            RequestedAt,
            ProcessedAt,
            CompletedAt,
            ProcessingFee,
            NetAmount,
            FailureReason
        FROM PayoutRequests 
        WHERE UserId = ? 
        ORDER BY RequestedAt DESC 
        LIMIT 50
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payoutRequests = [];
    while ($row = $result->fetch_assoc()) {
        // Parse payment details but hide sensitive information
        $paymentDetails = json_decode($row['PaymentDetails'], true);
        $maskedDetails = '';
        
        if ($paymentDetails) {
            switch ($row['PaymentMethod']) {
                case 'paypal':
                case 'zelle':
                case 'apple_pay':
                case 'google_pay':
                    // Mask email addresses
                    $email = $paymentDetails['details'] ?? '';
                    if (strpos($email, '@') !== false) {
                        $parts = explode('@', $email);
                        $maskedDetails = substr($parts[0], 0, 2) . '***@' . $parts[1];
                    } else {
                        $maskedDetails = 'Email provided';
                    }
                    break;
                    
                case 'cashapp':
                case 'venmo':
                    // Mask usernames
                    $username = $paymentDetails['details'] ?? '';
                    if (strlen($username) > 3) {
                        $maskedDetails = substr($username, 0, 3) . '***';
                    } else {
                        $maskedDetails = 'Username provided';
                    }
                    break;
                    
                case 'bank_transfer':
                    $maskedDetails = 'Bank details on file';
                    break;
                    
                default:
                    $maskedDetails = 'Payment details provided';
            }
        }
        
        $payoutRequests[] = [
            'request_id' => $row['RequestId'],
            'amount' => floatval($row['Amount']),
            'payment_method' => $row['PaymentMethod'],
            'payment_details_masked' => $maskedDetails,
            'status' => $row['Status'],
            'requested_at' => $row['RequestedAt'],
            'processed_at' => $row['ProcessedAt'],
            'completed_at' => $row['CompletedAt'],
            'processing_fee' => floatval($row['ProcessingFee']),
            'net_amount' => floatval($row['NetAmount']),
            'failure_reason' => $row['FailureReason']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    returnWithInfo([
        'success' => true,
        'payout_requests' => $payoutRequests,
        'total_requests' => count($payoutRequests)
    ]);
    
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