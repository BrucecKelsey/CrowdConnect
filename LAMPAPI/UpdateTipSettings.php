<?php

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get tip setting for party
    if (!isset($_GET['partyId'])) {
        returnWithError('Missing party ID');
        exit;
    }
    
    $partyId = (int)$_GET['partyId'];
    
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        returnWithError($conn->connect_error);
    } else {
        $stmt = $conn->prepare("SELECT AllowTips FROM Parties WHERE PartyId = ?");
        $stmt->bind_param("i", $partyId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $retValue = array(
                'success' => true,
                'allowTips' => (bool)$row['AllowTips']
            );
            sendResultInfoAsJson(json_encode($retValue));
        } else {
            returnWithError('Party not found');
        }
        
        $stmt->close();
        $conn->close();
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update tip setting for party
    $input = getRequestInfo();
    
    if (!isset($input['partyId']) || !isset($input['allowTips'])) {
        returnWithError('Missing required fields');
        exit;
    }
    
    $partyId = (int)$input['partyId'];
    $allowTips = (bool)$input['allowTips'];
    $allowTipsValue = $allowTips ? 1 : 0;
    
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        returnWithError($conn->connect_error);
    } else {
        $stmt = $conn->prepare("UPDATE Parties SET AllowTips = ? WHERE PartyId = ?");
        $stmt->bind_param("ii", $allowTipsValue, $partyId);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $retValue = array(
                    'success' => true,
                    'message' => $allowTips ? 'Tips enabled for this event' : 'Tips disabled for this event'
                );
                sendResultInfoAsJson(json_encode($retValue));
            } else {
                returnWithError('Party not found or no changes made');
            }
        } else {
            returnWithError($stmt->error);
        }
        
        $stmt->close();
        $conn->close();
    }
    
} else {
    returnWithError('Method not allowed');
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