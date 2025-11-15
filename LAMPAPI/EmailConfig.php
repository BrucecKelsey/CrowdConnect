<?php
// Email Configuration
// Configure your email settings here

class EmailConfig {
    // SMTP Configuration
    const SMTP_HOST = 'crowdconnectclub.gmail.com'; // Change to your SMTP server
    const SMTP_PORT = 587;
    const SMTP_USERNAME = 'crowdconnectclub@gmail.com'; // Your email
    const SMTP_PASSWORD = 'pgypeypxkxgmqyxk'; // Your app password
    const SMTP_ENCRYPTION = 'tls';
    
    // Email Settings
    const FROM_EMAIL = 'crowdconnectclub@gmail.com';
    const FROM_NAME = 'CrowdConnect';
    const SUPPORT_EMAIL = 'crowdconnectclub@gmail.com';
    
    // Application Settings
    const APP_NAME = 'CrowdConnect';
    const BASE_URL = 'https://crowdconnect.club';
    const LOGO_URL = 'https://crowdconnect.club/images/logo.png';
    
    // Token Expiration (in hours)
    const EMAIL_VERIFICATION_EXPIRES = 24;
    const PASSWORD_RESET_EXPIRES = 1;
}

// Email Templates
class EmailTemplates {
    
    public static function getEmailVerificationTemplate($firstName, $verificationLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                .email-container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
                .header { background: #6366f1; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f9f9f9; }
                .button { display: inline-block; background: #6366f1; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                .footer { background: #333; color: white; padding: 20px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🎵 " . EmailConfig::APP_NAME . "</h1>
                    <p style='margin: 0; font-size: 14px; opacity: 0.9;'>Connect DJs with Their Audience</p>
                </div>
                <div class='content'>
                    <h2>Welcome, {$firstName}!</h2>
                    <p>Thank you for joining " . EmailConfig::APP_NAME . "! To complete your registration, please verify your email address by clicking the button below:</p>
                    <div style='text-align: center;'>
                        <a href='{$verificationLink}' class='button'>Verify Email Address</a>
                    </div>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #666;'>{$verificationLink}</p>
                    <p><strong>This link will expire in " . EmailConfig::EMAIL_VERIFICATION_EXPIRES . " hours.</strong></p>
                    <p>If you didn't create an account with us, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 " . EmailConfig::APP_NAME . ". All rights reserved.</p>
                    <p>Need help? Contact us at " . EmailConfig::SUPPORT_EMAIL . "</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    public static function getPasswordResetTemplate($firstName, $resetLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                .email-container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
                .header { background: #6366f1; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f9f9f9; }
                .button { display: inline-block; background: #f59e0b; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                .footer { background: #333; color: white; padding: 20px; text-align: center; font-size: 12px; }
                .warning { background: #fee; border-left: 4px solid #f87171; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🎵 " . EmailConfig::APP_NAME . "</h1>
                    <p style='margin: 0; font-size: 14px; opacity: 0.9;'>Connect DJs with Their Audience</p>
                </div>
                <div class='content'>
                    <h2>Password Reset Request</h2>
                    <p>Hi {$firstName},</p>
                    <p>We received a request to reset your password. Click the button below to create a new password:</p>
                    <div style='text-align: center;'>
                        <a href='{$resetLink}' class='button'>Reset Password</a>
                    </div>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #666;'>{$resetLink}</p>
                    <div class='warning'>
                        <strong>⚠️ Security Notice:</strong>
                        <ul>
                            <li>This link will expire in " . EmailConfig::PASSWORD_RESET_EXPIRES . " hour(s)</li>
                            <li>If you didn't request this reset, please ignore this email</li>
                            <li>Your password will not change unless you click the link above</li>
                        </ul>
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 " . EmailConfig::APP_NAME . ". All rights reserved.</p>
                    <p>Need help? Contact us at " . EmailConfig::SUPPORT_EMAIL . "</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    public static function getUsernameReminderTemplate($firstName, $username) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                .email-container { max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif; }
                .header { background: #6366f1; color: white; padding: 20px; text-align: center; }
                .content { padding: 30px; background: #f9f9f9; }
                .username-box { background: white; border: 2px solid #6366f1; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                .footer { background: #333; color: white; padding: 20px; text-align: center; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1>🎵 " . EmailConfig::APP_NAME . "</h1>
                    <p style='margin: 0; font-size: 14px; opacity: 0.9;'>Connect DJs with Their Audience</p>
                </div>
                <div class='content'>
                    <h2>Username Reminder</h2>
                    <p>Hi {$firstName},</p>
                    <p>You requested a reminder of your username. Here it is:</p>
                    <div class='username-box'>
                        <h3>Your Username: <strong>{$username}</strong></h3>
                    </div>
                    <p>You can now use this username to log in to your " . EmailConfig::APP_NAME . " account.</p>
                    <p>If you didn't request this reminder, please contact our support team.</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2025 " . EmailConfig::APP_NAME . ". All rights reserved.</p>
                    <p>Need help? Contact us at " . EmailConfig::SUPPORT_EMAIL . "</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
?>