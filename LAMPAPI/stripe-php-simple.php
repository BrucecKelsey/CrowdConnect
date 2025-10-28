<?php
// LAMPAPI/stripe-php-simple.php - Simple Stripe integration without Composer
// This is a lightweight version that handles basic Stripe operations

class SimpleStripe {
    private $apiKey;
    private $baseUrl = 'https://api.stripe.com/v1/';
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    private function makeRequest($endpoint, $data = null, $method = 'POST') {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $decoded = json_decode($response, true);
        
        if ($httpCode !== 200) {
            throw new Exception($decoded['error']['message'] ?? 'Stripe API error');
        }
        
        return $decoded;
    }
    
    public function createPaymentIntent($amount, $currency = 'usd', $metadata = []) {
        $data = [
            'amount' => $amount,
            'currency' => $currency,
            'automatic_payment_methods' => json_encode(['enabled' => true]),
            'metadata' => $metadata
        ];
        
        return $this->makeRequest('payment_intents', $data);
    }
    
    public function retrievePaymentIntent($paymentIntentId) {
        return $this->makeRequest('payment_intents/' . $paymentIntentId, null, 'GET');
    }
    
    public function createAccount($accountData) {
        return $this->makeRequest('accounts', $accountData);
    }
    
    public function createAccountLink($accountId, $returnUrl, $refreshUrl) {
        $data = [
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding'
        ];
        
        return $this->makeRequest('account_links', $data);
    }
    
    public function retrieveAccount($accountId) {
        return $this->makeRequest('accounts/' . $accountId, null, 'GET');
    }
}
?>