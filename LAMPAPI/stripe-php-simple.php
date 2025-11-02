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
        
        // Check if cURL is available, otherwise use file_get_contents
        if (function_exists('curl_init') && function_exists('curl_exec')) {
            return $this->makeRequestWithCurl($url, $data, $method);
        } else {
            return $this->makeRequestWithFileGetContents($url, $data, $method);
        }
    }
    
    private function makeRequestWithCurl($url, $data = null, $method = 'POST') {
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
    
    private function makeRequestWithFileGetContents($url, $data = null, $method = 'POST') {
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        $context_options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true
            ]
        ];
        
        if ($method === 'POST' && $data) {
            $context_options['http']['content'] = http_build_query($data);
        }
        
        $context = stream_context_create($context_options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception('Failed to connect to Stripe API');
        }
        
        // Parse HTTP response code from headers
        $http_response_header = $http_response_header ?? [];
        $httpCode = 200; // Default
        foreach ($http_response_header as $header) {
            if (strpos($header, 'HTTP/') === 0) {
                $parts = explode(' ', $header);
                if (isset($parts[1])) {
                    $httpCode = (int)$parts[1];
                }
            }
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode !== 200) {
            throw new Exception($decoded['error']['message'] ?? 'Stripe API error (HTTP ' . $httpCode . ')');
        }
        
        return $decoded;
    }
    
    public function createPaymentIntent($amount, $currency = 'usd', $metadata = []) {
        $data = [
            'amount' => $amount,
            'currency' => $currency,
            'automatic_payment_methods[enabled]' => 'true'
        ];
        
        // Add metadata with proper Stripe format
        foreach ($metadata as $key => $value) {
            $data["metadata[$key]"] = $value;
        }
        
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