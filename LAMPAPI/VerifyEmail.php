<?php
require_once 'EmailService.php';

$inData = getRequestInfo();

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$userId = 0;
$firstName = "";
$lastName = "";
$login = "";

// Check if this is a verification request (GET) or resend request (POST)
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && isset($_GET['token'])) {
    // Verify email with token
    $token = $_GET['token'];
    
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        returnWithError($conn->connect_error);
        return;
    }
    
    // Check if token exists and is valid
    $stmt = $conn->prepare("SELECT UserId, FirstName, EmailVerificationExpires FROM Users WHERE EmailVerificationToken = ? AND IsEmailVerified = FALSE");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $userId = $row['UserId'];
        $firstName = $row['FirstName'];
        $expiresAt = $row['EmailVerificationExpires'];
        
        // Check if token has expired
        if (strtotime($expiresAt) < time()) {
            returnWithError("Verification link has expired. Please request a new one.");
        } else {
            // Mark email as verified
            $updateStmt = $conn->prepare("UPDATE Users SET IsEmailVerified = TRUE, EmailVerificationToken = NULL, EmailVerificationExpires = NULL WHERE UserId = ?");
            $updateStmt->bind_param("i", $userId);
            
            if ($updateStmt->execute()) {
                returnWithInfo("Email verified successfully! You can now log in to your account.");
            } else {
                returnWithError("Failed to verify email. Please try again.");
            }
            $updateStmt->close();
        }
    } else {
        returnWithError("Invalid or expired verification token.");
    }
    
    $stmt->close();
    $conn->close();
    
} elseif ($method === 'POST' && isset($inData["email"])) {
    // Resend verification email
    $email = $inData["email"];
    
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        returnWithError($conn->connect_error);
        return;
    }
    
    // Check if user exists and email is not already verified
    $stmt = $conn->prepare("SELECT UserId, FirstName FROM Users WHERE Email = ? AND IsEmailVerified = FALSE");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $userId = $row['UserId'];
        $firstName = $row['FirstName'];
        
        // Generate new verification token
        $verificationToken = EmailService::generateToken();
        $expiresAt = EmailService::getExpirationTime(EmailConfig::EMAIL_VERIFICATION_EXPIRES);
        
        // Update user with new token
        $updateStmt = $conn->prepare("UPDATE Users SET EmailVerificationToken = ?, EmailVerificationExpires = ? WHERE UserId = ?");
        $updateStmt->bind_param("ssi", $verificationToken, $expiresAt, $userId);
        
        if ($updateStmt->execute()) {
            // Send verification email
            if (EmailService::sendEmailVerification($email, $firstName, $verificationToken)) {
                returnWithInfo("Verification email sent successfully!");
            } else {
                returnWithError("Failed to send verification email. Please try again later.");
            }
        } else {
            returnWithError("Failed to generate verification token. Please try again.");
        }
        $updateStmt->close();
        
    } else {
        // For security, don't reveal if email exists or is already verified
        returnWithInfo("If this email is registered and unverified, a verification email has been sent.");
    }
    
    $stmt->close();
    $conn->close();
    
} else {
    returnWithError("Invalid request method or missing parameters.");
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