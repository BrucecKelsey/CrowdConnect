<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// LAMPAPI/CreateParty.php
$inData = getRequestInfo();

// Validate input data
if (!$inData || !isset($inData["partyName"]) || !isset($inData["djId"])) {
    returnWithError("Missing required fields: partyName and djId");
    exit;
}

$partyName = trim($inData["partyName"]);
$djId = intval($inData["djId"]);

// Validate inputs
if (empty($partyName)) {
    returnWithError("Party name cannot be empty");
    exit;
}

if ($djId <= 0) {
    returnWithError("Invalid DJ ID");
    exit;
}

$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error)
{
    returnWithError("Database connection failed: " . $conn->connect_error);
    exit;
}

try {
    // Check if DJ exists
    $checkStmt = $conn->prepare("SELECT ID FROM Users WHERE ID = ?");
    $checkStmt->bind_param("i", $djId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        returnWithError("DJ with ID $djId not found");
        exit;
    }
    $checkStmt->close();
    
    // Create the party
    $stmt = $conn->prepare("INSERT INTO Parties (PartyName, DJId) VALUES (?, ?)");
    if (!$stmt) {
        returnWithError("Failed to prepare statement: " . $conn->error);
        $conn->close();
        exit;
    }
    
    $stmt->bind_param("si", $partyName, $djId);
    if ($stmt->execute())
    {
        $partyId = $conn->insert_id;
        $stmt->close();
        $conn->close();
        returnWithInfo($partyId, $partyName);
    }
    else
    {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        returnWithError("Failed to create party: " . $error);
    }
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    returnWithError("Database error: " . $e->getMessage());
}

function getRequestInfo()
{
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        returnWithError("Invalid JSON input: " . json_last_error_msg());
        exit;
    }
    
    return $decoded;
}

function sendResultInfoAsJson($obj)
{
    echo $obj;
}

function returnWithError($err)
{
    $retValue = json_encode([
        "partyId" => 0,
        "partyName" => "",
        "error" => $err,
        "success" => false
    ]);
    sendResultInfoAsJson($retValue);
}

function returnWithInfo($partyId, $partyName)
{
    $retValue = json_encode([
        "partyId" => $partyId,
        "partyName" => $partyName,
        "error" => "",
        "success" => true
    ]);
    sendResultInfoAsJson($retValue);
}
?>
