<?php
// Debug script to test CreateConsolidatedPayment.php
header('Content-Type: text/plain'); // Use plain text to see raw output

echo "=== Testing CreateConsolidatedPayment.php ===\n";

$testData = [
    'requestId' => 0,
    'partyId' => 21, // Use an actual party ID from your system
    'songName' => 'Test Song',
    'requestedBy' => 'Test Artist',
    'requestFee' => 0.50,
    'tipAmount' => 0.50,
    'customerId' => null
];

echo "Test data being sent:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

// Simulate the request
$url = 'http://178.128.4.96/LAMPAPI/CreateConsolidatedPayment.php';
$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($testData))
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
echo "cURL Error: $error\n";
echo "Raw Response:\n";
echo "--- START RESPONSE ---\n";
echo $response;
echo "\n--- END RESPONSE ---\n";

// Try to decode as JSON
$decoded = json_decode($response, true);
if ($decoded === null) {
    echo "\nJSON Decode Error: " . json_last_error_msg() . "\n";
    echo "This explains why the frontend gets 'Invalid JSON response'\n";
} else {
    echo "\nSuccessfully decoded JSON:\n";
    echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
}
?>