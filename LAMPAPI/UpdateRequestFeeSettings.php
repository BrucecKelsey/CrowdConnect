<?php

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get request fee setting for party
    if (!isset($_GET['partyId'])) {
        returnWithError('Missing party ID');
        exit;
    }
    
    $partyId = (int)$_GET['partyId'];
    
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        returnWithError($conn->connect_error);
    } else {
        $stmt = $conn->prepare("SELECT AllowRequestFees, RequestFeeAmount FROM Parties WHERE PartyId = ?");
        $stmt->bind_param("i", $partyId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $retValue = array(
                'success' => true,
                'allowRequestFees' => (bool)$row['AllowRequestFees'],
                'requestFeeAmount' => (float)$row['RequestFeeAmount']
            );
            sendResultInfoAsJson(json_encode($retValue));
        } else {
            returnWithError('Party not found');
        }
        
        $stmt->close();
        $conn->close();
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update request fee setting for party
    $input = getRequestInfo();
    
    if (!isset($input['partyId'])) {
        returnWithError('Missing party ID');
        exit;
    }
    
    $partyId = (int)$input['partyId'];
    $allowRequestFees = isset($input['allowRequestFees']) ? (bool)$input['allowRequestFees'] : false;
    $requestFeeAmount = isset($input['requestFeeAmount']) ? (float)$input['requestFeeAmount'] : 0.00;
    
    // Validate fee amount
    if ($allowRequestFees && ($requestFeeAmount < 0 || $requestFeeAmount > 100)) {
        returnWithError('Request fee amount must be between $0.00 and $100.00');
        exit;
    }
    
    $allowRequestFeesValue = $allowRequestFees ? 1 : 0;
    
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        returnWithError($conn->connect_error);
    } else {
        $stmt = $conn->prepare("UPDATE Parties SET AllowRequestFees = ?, RequestFeeAmount = ? WHERE PartyId = ?");
        $stmt->bind_param("idi", $allowRequestFeesValue, $requestFeeAmount, $partyId);
        
        if ($stmt->execute()) {
            $retValue = array(
                'success' => true,
                'message' => 'Request fee settings updated successfully',
                'allowRequestFees' => $allowRequestFees,
                'requestFeeAmount' => $requestFeeAmount
            );
            sendResultInfoAsJson(json_encode($retValue));
        } else {
            returnWithError('Failed to update request fee settings');
        }
        
        $stmt->close();
        $conn->close();
    }
} else {
    returnWithError('Method not allowed');
}

function getRequestInfo()
{
    return json_decode(file_get_contents('php://input'), true);
}

function sendResultInfoAsJson($obj)
{
    header('Content-type: application/json');
    echo $obj;
}

function returnWithError($err)
{
    $retValue = '{"error":"' . $err . '"}';
    sendResultInfoAsJson($retValue);
}
?>