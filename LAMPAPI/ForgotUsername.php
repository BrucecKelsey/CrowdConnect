<?php
require_once 'EmailService.php';

$inData = getRequestInfo();

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($inData["email"])) {
    $email = $inData["email"];
    
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        returnWithError($conn->connect_error);
        return;
    }
    
    // Check if user exists with this email
    $stmt = $conn->prepare("SELECT FirstName, Login FROM Users WHERE Email = ? AND IsEmailVerified = TRUE");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $firstName = $row['FirstName'];
        $username = $row['Login'];
        
        // Send username reminder email
        if (EmailService::sendUsernameReminder($email, $firstName, $username)) {
            returnWithInfo("Username reminder sent to your email address!");
        } else {
            returnWithError("Failed to send username reminder. Please try again later.");
        }
        
    } else {
        // For security, don't reveal if email exists
        returnWithInfo("If this email is registered, a username reminder has been sent.");
    }
    
    $stmt->close();
    $conn->close();
    
} else {
    returnWithError("Invalid request method or missing email.");
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

function returnWithInfo($info) {
    $retValue = '{"message":"' . $info . '"}';
    sendResultInfoAsJson($retValue);
}
?>