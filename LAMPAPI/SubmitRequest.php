<?php
// LAMPAPI/SubmitRequest.php

// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$inData = getRequestInfo();
$partyId = intval($inData["partyId"]);
$songName = $inData["songName"];
$requestedBy = isset($inData["requestedBy"]) ? $inData["requestedBy"] : "";
$paymentIntentId = isset($inData["paymentIntentId"]) ? $inData["paymentIntentId"] : null;

$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error)
{
    returnWithError($conn->connect_error);
}
else
{
    // Check if requests are enabled for this party
    $stmt = $conn->prepare("SELECT RequestsEnabled FROM Parties WHERE PartyId=?");
    $stmt->bind_param("i", $partyId);
    $stmt->execute();
    $stmt->bind_result($requestsEnabled);
    if ($stmt->fetch() === false) {
        $stmt->close();
        $conn->close();
        returnWithError("Event not found.");
        exit();
    }
    $stmt->close();
    if (!$requestsEnabled) {
        $conn->close();
        returnWithError("Song requests are currently disabled for this event.");
        exit();
    }
    // Insert request (using original column names for compatibility)
    $stmt = $conn->prepare("INSERT INTO Requests (PartyId, SongName, RequestedBy, Timestamp) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $partyId, $songName, $requestedBy);
    if ($stmt->execute())
    {
        $requestId = $stmt->insert_id;
        $stmt->close();
        
        // Update Tips table with RequestId if paymentIntentId provided (for payment-first flow)
        if ($paymentIntentId) {
            $tipStmt = $conn->prepare("UPDATE Tips SET RequestId = ? WHERE StripePaymentIntentId = ? AND RequestId IS NULL");
            $tipStmt->bind_param("is", $requestId, $paymentIntentId);
            $tipStmt->execute();
            $rowsUpdated = $tipStmt->affected_rows;
            error_log("SubmitRequest: Linked RequestId " . $requestId . " to PaymentIntent " . $paymentIntentId . ", updated " . $rowsUpdated . " tip records");
            $tipStmt->close();
        }
        
        // Try to get DJ information for tipping (safely handle missing columns/tables)
        $djInfo = null;
        try {
            $stmt = $conn->prepare("SELECT u.ID, u.FirstName, u.LastName FROM Users u JOIN Parties p ON u.ID = p.UserId WHERE p.PartyId = ?");
            $stmt->bind_param("i", $partyId);
            $stmt->execute();
            $result = $stmt->get_result();
            $djInfo = $result->fetch_assoc();
            $stmt->close();
        } catch (Exception $e) {
            // Silently handle missing columns or other database issues
        }
        
        if ($djInfo) {
            $retValue = array(
                "error" => "", 
                "success" => true, 
                "requestId" => $requestId,
                "djId" => $djInfo["ID"],
                "djName" => trim($djInfo["FirstName"] . " " . $djInfo["LastName"])
            );
        } else {
            // Fallback response without DJ info
            $retValue = array(
                "error" => "", 
                "success" => true, 
                "requestId" => $requestId
            );
        }
        
        sendResultInfoAsJson(json_encode($retValue));
    }
    else
    {
        returnWithError($stmt->error);
    }
    $stmt->close();
    $conn->close();
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
// returnWithInfo removed, now returns more info above
?>
