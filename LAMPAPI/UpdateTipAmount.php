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
    $stmt = $conn->prepare("UPDATE Requests SET TipAmount = ? WHERE RequestId = ?");
    $stmt->bind_param("di", $tipAmount, $requestId);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $retValue = array(
                'success' => true,
                'message' => 'Tip amount updated successfully'
            );
            sendResultInfoAsJson(json_encode($retValue));
        } else {
            returnWithError('Request not found or no changes made');
        }
    } else {
        returnWithError($stmt->error);
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