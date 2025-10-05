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
    $stmt = $conn->prepare("INSERT INTO Requests (PartyId, SongName, RequestedBy) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $partyId, $songName, $requestedBy);
    if ($stmt->execute())
    {
        returnWithInfo("Request submitted successfully!");
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
function returnWithInfo($msg)
{
    $retValue = '{"error":""}';
    sendResultInfoAsJson($retValue);
}
?>
