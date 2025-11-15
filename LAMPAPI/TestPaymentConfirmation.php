<?php
// Test script to manually trigger payment confirmation
// Usage: http://yourserver.com/LAMPAPI/TestPaymentConfirmation.php?payment_intent_id=pi_xxxxx

require_once 'StripeConfig.php';

header('Content-Type: application/json');

$paymentIntentId = $_GET['payment_intent_id'] ?? '';

if (empty($paymentIntentId)) {
    echo json_encode(['error' => 'Please provide payment_intent_id parameter']);
    exit;
}

echo "<h2>Testing Payment Confirmation for: $paymentIntentId</h2>";

// Check if this payment exists in the database
include_once "database.php";
$conn = getDatabaseConnection();

echo "<h3>1. Checking Requests table:</h3>";
$requestStmt = $conn->prepare("SELECT RequestId, DJUserID, PriceOfRequest, TipAmount, TotalCharged, ProcessingFee, TotalCollected, PlatformRevenue, PaymentStatus FROM Requests WHERE StripePaymentIntentId = ?");
$requestStmt->bind_param("s", $paymentIntentId);
$requestStmt->execute();
$requestResult = $requestStmt->get_result();

if ($request = $requestResult->fetch_assoc()) {
    echo "<pre>";
    print_r($request);
    echo "</pre>";
    
    echo "<h3>2. Calling ConfirmPayment.php:</h3>";
    
    // Manually call the confirmation
    $url = 'http://localhost/LAMPAPI/ConfirmPayment.php'; // Adjust this URL as needed
    $data = json_encode(['paymentIntentId' => $paymentIntentId]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
    echo "<p><strong>Response:</strong></p>";
    echo "<pre>$response</pre>";
    
    echo "<h3>3. Checking updated data:</h3>";
    
    // Check Requests table again
    $requestStmt->execute();
    $updatedRequest = $requestStmt->get_result()->fetch_assoc();
    echo "<h4>Updated Requests record:</h4>";
    echo "<pre>";
    print_r($updatedRequest);
    echo "</pre>";
    
    // Check EarningsHistory
    $earningsStmt = $conn->prepare("SELECT * FROM EarningsHistory WHERE RequestId = ? ORDER BY CreatedAt DESC LIMIT 1");
    $earningsStmt->bind_param("i", $request['RequestId']);
    $earningsStmt->execute();
    $earningsResult = $earningsStmt->get_result();
    
    echo "<h4>EarningsHistory record:</h4>";
    if ($earnings = $earningsResult->fetch_assoc()) {
        echo "<pre>";
        print_r($earnings);
        echo "</pre>";
    } else {
        echo "<p>No earnings record found!</p>";
    }
    
    // Check Users table
    $userStmt = $conn->prepare("SELECT ID, FirstName, LastName, TotalEarnings, AvailableFunds FROM Users WHERE ID = ?");
    $userStmt->bind_param("i", $request['DJUserID']);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    echo "<h4>DJ User record:</h4>";
    if ($user = $userResult->fetch_assoc()) {
        echo "<pre>";
        print_r($user);
        echo "</pre>";
    } else {
        echo "<p>DJ User not found!</p>";
    }
    
} else {
    echo "<p>No request found with payment intent ID: $paymentIntentId</p>";
    
    // Check Tips table (legacy)
    echo "<h3>Checking Tips table (legacy):</h3>";
    $tipStmt = $conn->prepare("SELECT * FROM Tips WHERE StripePaymentIntentId = ?");
    $tipStmt->bind_param("s", $paymentIntentId);
    $tipStmt->execute();
    $tipResult = $tipStmt->get_result();
    
    if ($tip = $tipResult->fetch_assoc()) {
        echo "<pre>";
        print_r($tip);
        echo "</pre>";
    } else {
        echo "<p>No record found in Tips table either!</p>";
    }
}

$conn->close();
?>