<?php
// LAMPAPI/GetPartyName.php

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_GET['partyId'])) {
    returnWithError("Missing party ID");
    exit;
}

$partyId = intval($_GET['partyId']);

$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error) {
    returnWithError($conn->connect_error);
} else {
    // Get party name and DJ information
    $stmt = $conn->prepare("SELECT p.PartyName, u.ID, u.FirstName, u.LastName FROM Parties p JOIN Users u ON p.UserId = u.ID WHERE p.PartyId = ?");
    $stmt->bind_param("i", $partyId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $retValue = array(
            "partyName" => $row["PartyName"],
            "djId" => $row["ID"],
            "djName" => $row["FirstName"] . " " . $row["LastName"]
        );
        sendResultInfoAsJson(json_encode($retValue));
    } else {
        returnWithError("Party not found");
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