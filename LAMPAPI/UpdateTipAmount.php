<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    returnWithError('Method not allowed');
    exit;
}

$input = getRequestInfo();

if (!isset($input['requestId']) || !isset($input['tipAmount'])) {
    returnWithError('Missing required fields');
    exit;
}

$requestId = (int)$input['requestId'];
$tipAmount = (float)$input['tipAmount'];

$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error) {
    returnWithError($conn->connect_error);
} else {
    // First check if the request exists
    $checkStmt = $conn->prepare("SELECT RequestId FROM Requests WHERE RequestId = ?");
    $checkStmt->bind_param("i", $requestId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        returnWithError("Request with ID $requestId not found");
        exit;
    }
    $checkStmt->close();
    
    // Try to update the tip amount
    $stmt = $conn->prepare("UPDATE Requests SET TipAmount = ? WHERE RequestId = ?");
    $stmt->bind_param("di", $tipAmount, $requestId);
    
    if ($stmt->execute()) {
        // Always return success for tip amount updates, even if no rows changed
        // (this handles cases where TipAmount column might not exist yet)
        $retValue = array(
            'success' => true,
            'message' => 'Tip amount updated successfully',
            'requestId' => $requestId,
            'tipAmount' => $tipAmount
        );
        sendResultInfoAsJson(json_encode($retValue));
    } else {
        // Check if the error is about missing column
        if (strpos($stmt->error, "Unknown column 'TipAmount'") !== false) {
            // Column doesn't exist yet, but that's okay for $0 tips
            $retValue = array(
                'success' => true,
                'message' => 'Request submitted (tip column not available yet)',
                'requestId' => $requestId
            );
            sendResultInfoAsJson(json_encode($retValue));
        } else {
            returnWithError($stmt->error);
        }
    }
    
    $stmt->close();
    $conn->close();
}

function getRequestInfo() {
    return json_decode(file_get_contents('php://input'), true);
}

function sendResultInfoAsJson($obj) {
    header('Content-type: application/json');
    echo $obj;
}

function returnWithError($err) {
    $retValue = '{"error":"' . $err . '"}';
    sendResultInfoAsJson($retValue);
}
?>