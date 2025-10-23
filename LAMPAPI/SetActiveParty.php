<?php
// LAMPAPI/SetActiveParty.php
header('Content-type: application/json');
$inData = json_decode(file_get_contents('php://input'), true);
$userId = isset($inData['userId']) ? intval($inData['userId']) : 0;
$partyId = isset($inData['partyId']) ? intval($inData['partyId']) : null;
$retValue = array("error" => "");
if ($userId <= 0) {
    $retValue["error"] = "Missing or invalid userId.";
    echo json_encode($retValue);
    exit();
}
$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error) {
    $retValue["error"] = $conn->connect_error;
    echo json_encode($retValue);
    exit();
}
if ($partyId) {
    $stmt = $conn->prepare("UPDATE Users SET ActivePartyId=? WHERE UserId=?");
    $stmt->bind_param("ii", $partyId, $userId);
} else {
    $stmt = $conn->prepare("UPDATE Users SET ActivePartyId=NULL WHERE UserId=?");
    $stmt->bind_param("i", $userId);
}
if (!$stmt->execute()) {
    $retValue["error"] = $stmt->error;
}
$stmt->close();
$conn->close();
echo json_encode($retValue);
?>
