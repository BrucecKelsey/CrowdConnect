<?php
// LAMPAPI/SubmitRequest.php
$inData = getRequestInfo();
$partyId = intval($inData["partyId"]);
$songName = $inData["songName"];
$requestedBy = isset($inData["requestedBy"]) ? $inData["requestedBy"] : "";

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
    // Insert request and get DJ info for tipping
    $stmt = $conn->prepare("INSERT INTO Requests (PartyId, SongName, RequestedBy, Timestamp) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $partyId, $songName, $requestedBy);
    if ($stmt->execute())
    {
        $requestId = $stmt->insert_id;
        $stmt->close();
        
        // Get DJ information for tipping
        $stmt = $conn->prepare("SELECT u.ID, u.FirstName, u.LastName FROM Users u JOIN Parties p ON u.ID = p.UserId WHERE p.PartyId = ?");
        $stmt->bind_param("i", $partyId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($djInfo = $result->fetch_assoc()) {
            $retValue = array(
                "error" => "", 
                "success" => true, 
                "requestId" => $requestId,
                "djId" => $djInfo["ID"],
                "djName" => $djInfo["FirstName"] . " " . $djInfo["LastName"]
            );
        } else {
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
