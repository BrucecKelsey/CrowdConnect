<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// LAMPAPI/CreateParty.php
$inData = getRequestInfo();
$partyName = $inData["partyName"];
$djId = $inData["djId"];

$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error)
{
    returnWithError($conn->connect_error);
}
else
{
    $stmt = $conn->prepare("INSERT INTO Parties (PartyName, DJId) VALUES (?, ?)");
    $stmt->bind_param("si", $partyName, $djId);
    if ($stmt->execute())
    {
        $partyId = $conn->insert_id;
        returnWithInfo($partyId, $partyName);
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
    $retValue = '{"partyId":0,"partyName":"","error":"' . $err . '"}';
    sendResultInfoAsJson($retValue);
}
function returnWithInfo($partyId, $partyName)
{
    $retValue = '{"partyId":' . $partyId . ',"partyName":"' . $partyName . '","error":""}';
    sendResultInfoAsJson($retValue);
}
?>
