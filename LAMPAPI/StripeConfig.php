<?php
// LAMPAPI/StripeConfig.php - Secure Stripe configuration with environment variables

class StripeConfig {
    private static $secretKey = null;
    private static $publishableKey = null;
    private static $webhookSecret = null;
    
    public static function init() {
        // Load environment variables from .env file if it exists
        self::loadEnvFile();
        
        // Set Stripe keys from environment variables
        self::$secretKey = self::getEnvVar('STRIPE_SECRET_KEY');
        self::$publishableKey = self::getEnvVar('STRIPE_PUBLISHABLE_KEY');
        self::$webhookSecret = self::getEnvVar('STRIPE_WEBHOOK_SECRET');
        
        if (!self::$secretKey || !self::$publishableKey) {
            throw new Exception('Stripe API keys not configured. Please set environment variables.');
        }
    }
    
    private static function loadEnvFile() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue; // Skip comments
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }
    
    private static function getEnvVar($key) {
        // Try $_ENV first, then getenv(), then $_SERVER
        return $_ENV[$key] ?? getenv($key) ?: ($_SERVER[$key] ?? null);
    }
    
    public static function getSecretKey() {
        if (self::$secretKey === null) {
            self::init();
        }
        return self::$secretKey;
    }
    
    public static function getPublishableKey() {
        if (self::$publishableKey === null) {
            self::init();
        }
        return self::$publishableKey;
    }
    
    public static function getWebhookSecret() {
        if (self::$webhookSecret === null) {
            self::init();
        }
        return self::$webhookSecret;
    }
    
    public static function getStripeClient() {
        require_once 'stripe-php-simple.php';
        return new SimpleStripe(self::getSecretKey());
    }
}
?>