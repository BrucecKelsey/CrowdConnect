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
$success = true;
// Always set all parties for this DJ to RequestsEnabled=0
$stmt = $conn->prepare("UPDATE Parties SET RequestsEnabled=0 WHERE DJId=?");
if (!$stmt) {
    $retValue["error"] = $conn->error;
    $success = false;
} else {
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        $retValue["error"] = $stmt->error;
        $success = false;
    }
    $stmt->close();
}

if ($partyId && $success) {
    // Set the selected party to RequestsEnabled=1 (activate only this one)
    $stmt = $conn->prepare("UPDATE Parties SET RequestsEnabled=1 WHERE PartyId=? AND DJId=?");
    if (!$stmt) {
        $retValue["error"] = $conn->error;
        $success = false;
    } else {
        $stmt->bind_param("ii", $partyId, $userId);
        if (!$stmt->execute()) {
            $retValue["error"] = $stmt->error;
            $success = false;
        }
        $stmt->close();
    }
    // Set active party in Users
    if ($success) {
        $stmt = $conn->prepare("UPDATE Users SET ActivePartyId=? WHERE ID=?");
        if (!$stmt) {
            $retValue["error"] = $conn->error;
        } else {
            $stmt->bind_param("ii", $partyId, $userId);
            if (!$stmt->execute()) {
                $retValue["error"] = $stmt->error;
            }
            $stmt->close();
        }
    }
} else if ($success) {
    // Deactivate: set active party in Users to NULL
    $stmt = $conn->prepare("UPDATE Users SET ActivePartyId=NULL WHERE ID=?");
    if (!$stmt) {
        $retValue["error"] = $conn->error;
    } else {
        $stmt->bind_param("i", $userId);
        if (!$stmt->execute()) {
            $retValue["error"] = $stmt->error;
        }
        $stmt->close();
    }
}
$conn->close();
echo json_encode($retValue);
?>
