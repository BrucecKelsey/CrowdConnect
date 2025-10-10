<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$inData = json_decode(file_get_contents('php://input'), true);
$partyId = isset($inData['partyId']) ? intval($inData['partyId']) : 0;

if ($partyId <= 0) {
    echo json_encode(["error" => "Invalid party ID."]);
    exit();
}

$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error) {
    echo json_encode(["error" => $conn->connect_error]);
    exit();
}

// Delete all song requests for this party
$stmt = $conn->prepare("DELETE FROM Requests WHERE PartyId = ?");
if (!$stmt) {
    echo json_encode(["error" => $conn->error]);
    $conn->close();
    exit();
}
$stmt->bind_param("i", $partyId);
$stmt->execute();
$stmt->close();

// Delete the party itself
$stmt = $conn->prepare("DELETE FROM Parties WHERE PartyId = ?");
if (!$stmt) {
    echo json_encode(["error" => $conn->error]);
    $conn->close();
    exit();
}
$stmt->bind_param("i", $partyId);
$stmt->execute();
$stmt->close();

$conn->close();
echo json_encode(["success" => true]);
?>
