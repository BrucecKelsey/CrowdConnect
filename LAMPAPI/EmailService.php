<?php
require_once 'EmailConfig.php';

class EmailService {
    
    public static function sendEmail($to, $subject, $htmlBody, $textBody = null) {
        // If no text body provided, strip HTML for text version
        if ($textBody === null) {
            $textBody = strip_tags($htmlBody);
        }
        
        // Headers
        $headers = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . EmailConfig::FROM_NAME . ' <' . EmailConfig::FROM_EMAIL . '>';
        $headers[] = 'Reply-To: ' . EmailConfig::SUPPORT_EMAIL;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        // Send email
        $success = mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        
        // Log email attempt
        error_log("Email sent to {$to}: " . ($success ? 'SUCCESS' : 'FAILED') . " - Subject: {$subject}");
        
        return $success;
    }
    
    public static function sendEmailVerification($email, $firstName, $token) {
        $verificationLink = EmailConfig::BASE_URL . "/verify-email.html?token=" . urlencode($token);
        $subject = "Verify your " . EmailConfig::APP_NAME . " account";
        $htmlBody = EmailTemplates::getEmailVerificationTemplate($firstName, $verificationLink);
        
        return self::sendEmail($email, $subject, $htmlBody);
    }
    
    public static function sendPasswordReset($email, $firstName, $token) {
        $resetLink = EmailConfig::BASE_URL . "/reset-password.html?token=" . urlencode($token);
        $subject = "Reset your " . EmailConfig::APP_NAME . " password";
        $htmlBody = EmailTemplates::getPasswordResetTemplate($firstName, $resetLink);
        
        return self::sendEmail($email, $subject, $htmlBody);
    }
    
    public static function sendUsernameReminder($email, $firstName, $username) {
        $subject = "Your " . EmailConfig::APP_NAME . " username";
        $htmlBody = EmailTemplates::getUsernameReminderTemplate($firstName, $username);
        
        return self::sendEmail($email, $subject, $htmlBody);
    }
    
    // Generate secure random token
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    // Calculate expiration datetime
    public static function getExpirationTime($hours) {
        return date('Y-m-d H:i:s', time() + ($hours * 3600));
    }
}
?>