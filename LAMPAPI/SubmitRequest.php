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
    // Get DJ ID from Parties table for the enhanced Requests table
    $djUserID = null;
    $djStmt = $conn->prepare("SELECT DJId FROM Parties WHERE PartyId = ?");
    $djStmt->bind_param("i", $partyId);
    $djStmt->execute();
    $djStmt->bind_result($djUserID);
    $djStmt->fetch();
    $djStmt->close();
    
    // Insert request with enhanced columns for consolidated payment system
    $stmt = $conn->prepare("INSERT INTO Requests (PartyId, SongName, RequestedBy, Timestamp, DJUserID, PriceOfRequest, TipAmount, TotalCharged, ProcessingFee, TotalCollected, PlatformRevenue, PaymentStatus) VALUES (?, ?, ?, NOW(), ?, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'pending')");
    $stmt->bind_param("issi", $partyId, $songName, $requestedBy, $djUserID);
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
        
        // Get DJ information using the DJUserID we already have
        $djInfo = null;
        if ($djUserID) {
            try {
                $stmt = $conn->prepare("SELECT ID, FirstName, LastName FROM Users WHERE ID = ?");
                $stmt->bind_param("i", $djUserID);
                $stmt->execute();
                $result = $stmt->get_result();
                $djInfo = $result->fetch_assoc();
                $stmt->close();
            } catch (Exception $e) {
                error_log("SubmitRequest: Error getting DJ info: " . $e->getMessage());
            }
        }
        
        error_log("SubmitRequest: Successfully inserted request ID " . $requestId . " for PartyId " . $partyId . " with DJUserID " . $djUserID);
        
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
        error_log("SubmitRequest: Database error - " . $stmt->error);
        returnWithError("Failed to submit request: " . $stmt->error);
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
