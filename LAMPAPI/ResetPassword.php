<?php
require_once 'EmailService.php';

$inData = getRequestInfo();

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST' && isset($inData["action"])) {
    $action = $inData["action"];
    
    if ($action === "request" && isset($inData["email"])) {
        // Request password reset
        $email = $inData["email"];
        
        $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
        if ($conn->connect_error) {
            returnWithError($conn->connect_error);
            return;
        }
        
        // Check if user exists with this email
        $stmt = $conn->prepare("SELECT UserId, FirstName FROM Users WHERE Email = ? AND IsEmailVerified = TRUE");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $userId = $row['UserId'];
            $firstName = $row['FirstName'];
            
            // Generate reset token
            $resetToken = EmailService::generateToken();
            $expiresAt = EmailService::getExpirationTime(EmailConfig::PASSWORD_RESET_EXPIRES);
            
            // Save reset token
            $updateStmt = $conn->prepare("UPDATE Users SET PasswordResetToken = ?, PasswordResetExpires = ? WHERE UserId = ?");
            $updateStmt->bind_param("ssi", $resetToken, $expiresAt, $userId);
            
            if ($updateStmt->execute()) {
                // Send reset email
                if (EmailService::sendPasswordReset($email, $firstName, $resetToken)) {
                    returnWithInfo("Password reset email sent successfully!");
                } else {
                    returnWithError("Failed to send password reset email. Please try again later.");
                }
            } else {
                returnWithError("Failed to generate reset token. Please try again.");
            }
            $updateStmt->close();
            
        } else {
            // For security, don't reveal if email exists
            returnWithInfo("If this email is registered, a password reset email has been sent.");
        }
        
        $stmt->close();
        $conn->close();
        
    } elseif ($action === "reset" && isset($inData["token"]) && isset($inData["password"])) {
        // Reset password with token
        $token = $inData["token"];
        $newPassword = $inData["password"];
        
        $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
        if ($conn->connect_error) {
            returnWithError($conn->connect_error);
            return;
        }
        
        // Verify reset token
        $stmt = $conn->prepare("SELECT UserId, PasswordResetExpires FROM Users WHERE PasswordResetToken = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $userId = $row['UserId'];
            $expiresAt = $row['PasswordResetExpires'];
            
            // Check if token has expired
            if (strtotime($expiresAt) < time()) {
                returnWithError("Password reset link has expired. Please request a new one.");
            } else {
                // Hash the new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update password and clear reset token
                $updateStmt = $conn->prepare("UPDATE Users SET Password = ?, PasswordResetToken = NULL, PasswordResetExpires = NULL WHERE UserId = ?");
                $updateStmt->bind_param("si", $hashedPassword, $userId);
                
                if ($updateStmt->execute()) {
                    returnWithInfo("Password reset successfully! You can now log in with your new password.");
                } else {
                    returnWithError("Failed to reset password. Please try again.");
                }
                $updateStmt->close();
            }
        } else {
            returnWithError("Invalid or expired reset token.");
        }
        
        $stmt->close();
        $conn->close();
        
    } else {
        returnWithError("Invalid action or missing parameters.");
    }
    
} else {
    returnWithError("Invalid request method or missing action.");
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