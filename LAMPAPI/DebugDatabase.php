<?php
// LAMPAPI/DebugDatabase.php - Debug endpoint to check database state
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$debug = [];

try {
    // Check Parties table
    $result = $conn->query("SELECT PartyId, PartyName, DJId, RequestsEnabled FROM Parties LIMIT 5");
    $parties = [];
    while ($row = $result->fetch_assoc()) {
        $parties[] = $row;
    }
    $debug['parties'] = $parties;
    
    // Check Users table
    $result = $conn->query("SELECT ID, FirstName, LastName FROM Users LIMIT 5");
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $debug['users'] = $users;
    
    // Check Requests table structure
    $result = $conn->query("DESCRIBE Requests");
    $requestsStructure = [];
    while ($row = $result->fetch_assoc()) {
        $requestsStructure[] = $row;
    }
    $debug['requests_structure'] = $requestsStructure;
    
    // Check recent requests
    $result = $conn->query("SELECT RequestId, PartyId, SongName, RequestedBy, DJUserID, PaymentStatus, Timestamp FROM Requests ORDER BY Timestamp DESC LIMIT 5");
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $debug['recent_requests'] = $requests;
    
    echo json_encode(['success' => true, 'debug' => $debug]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Debug query failed: ' . $e->getMessage()]);
}

$conn->close();
?>