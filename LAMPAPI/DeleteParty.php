<?php
// LAMPAPI/DeleteParty.php
header('Content-type: application/json');
$inData = json_decode(file_get_contents('php://input'), true);
$partyId = isset($inData['partyId']) ? intval($inData['partyId']) : 0;
$djId = isset($inData['djId']) ? intval($inData['djId']) : 0;
$retValue = array("error" => "");
if ($partyId <= 0 || $djId <= 0) {
    $retValue["error"] = "Missing partyId or djId.";
    echo json_encode($retValue);
    exit();
}
$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error) {
    $retValue["error"] = $conn->connect_error;
    echo json_encode($retValue);
    exit();
}
$stmt = $conn->prepare("DELETE FROM Parties WHERE PartyId=? AND DJId=?");
$stmt->bind_param("ii", $partyId, $djId);
if (!$stmt->execute()) {
    $retValue["error"] = $stmt->error;
}
$stmt->close();
$conn->close();
echo json_encode($retValue);
?>
