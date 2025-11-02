<?php
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
    
    if (!isset($input['paymentIntentId']) || !isset($input['requestId'])) {
        throw new Exception('Missing required fields');
    }
    
    $paymentIntentId = $input['paymentIntentId'];
    $requestId = (int)$input['requestId'];
    
    // Update tip record with the actual request ID
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $stmt = $conn->prepare("UPDATE Tips SET RequestId = ? WHERE StripePaymentIntentId = ?");
    $stmt->bind_param("is", $requestId, $paymentIntentId);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Tip record updated with request ID']);
    } else {
        echo json_encode(['success' => false, 'error' => 'No tip record found to update']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>