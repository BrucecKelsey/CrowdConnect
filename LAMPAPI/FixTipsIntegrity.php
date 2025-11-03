<?php
// Data integrity fix and analysis script
header('Content-Type: text/plain');

$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== CROWDCONNECT TIPS DATA INTEGRITY FIX ===\n\n";

// 1. Analyze current state
echo "1. CURRENT TIPS ANALYSIS:\n";
$result = $conn->query("
    SELECT 
        COUNT(*) as total_tips,
        COUNT(RequestId) as tips_with_requests,
        COUNT(CASE WHEN RequestId IS NULL THEN 1 END) as tips_without_requests,
        COUNT(DJUserID) as tips_with_dj,
        COUNT(CASE WHEN DJUserID IS NULL THEN 1 END) as tips_without_dj,
        COUNT(CustomerUserID) as tips_with_customer,
        COUNT(CASE WHEN CustomerUserID IS NULL THEN 1 END) as tips_without_customer
    FROM Tips
");
$stats = $result->fetch_assoc();
foreach ($stats as $key => $value) {
    echo "$key: $value\n";
}

echo "\n2. TIPS WITH MISSING REFERENCES:\n";
$result = $conn->query("
    SELECT 
        t.TipId,
        t.RequestId,
        t.DJUserID,
        t.CustomerUserID,
        t.TipAmount,
        t.Status,
        t.StripePaymentIntentId,
        CASE 
            WHEN t.RequestId IS NOT NULL AND r.RequestId IS NULL THEN 'Request Missing'
            WHEN t.DJUserID IS NOT NULL AND dj.ID IS NULL THEN 'DJ Missing'
            WHEN t.CustomerUserID IS NOT NULL AND c.ID IS NULL THEN 'Customer Missing'
            ELSE 'OK'
        END as Issue
    FROM Tips t
    LEFT JOIN Requests r ON t.RequestId = r.RequestId
    LEFT JOIN Users dj ON t.DJUserID = dj.ID
    LEFT JOIN Users c ON t.CustomerUserID = c.ID
    WHERE (t.RequestId IS NOT NULL AND r.RequestId IS NULL)
       OR (t.DJUserID IS NOT NULL AND dj.ID IS NULL)
       OR (t.CustomerUserID IS NOT NULL AND c.ID IS NULL)
");

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "TipId: {$row['TipId']}, Issue: {$row['Issue']}, Amount: {$row['TipAmount']}, Status: {$row['Status']}\n";
    }
} else {
    echo "No broken references found!\n";
}

echo "\n3. TIPS WITHOUT REQUESTS (Payment-First Flow):\n";
$result = $conn->query("
    SELECT TipId, DJUserID, CustomerUserID, TipAmount, Status, StripePaymentIntentId, Timestamp
    FROM Tips 
    WHERE RequestId IS NULL
    ORDER BY Timestamp DESC
");

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "TipId: {$row['TipId']}, DJ: {$row['DJUserID']}, Customer: {$row['CustomerUserID']}, Amount: {$row['TipAmount']}, Status: {$row['Status']}, PaymentIntent: {$row['StripePaymentIntentId']}\n";
    }
} else {
    echo "All tips have associated requests\n";
}

echo "\n4. POTENTIAL FIXES NEEDED:\n";

// Check for orphaned completed tips without requests
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM Tips 
    WHERE RequestId IS NULL AND Status = 'completed'
");
$orphaned = $result->fetch_assoc()['count'];
if ($orphaned > 0) {
    echo "- $orphaned completed tips without requests (may need manual review)\n";
}

// Check for invalid user references
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM Tips t
    LEFT JOIN Users u ON t.DJUserID = u.ID
    WHERE t.DJUserID IS NOT NULL AND u.ID IS NULL
");
$invalid_dj = $result->fetch_assoc()['count'];
if ($invalid_dj > 0) {
    echo "- $invalid_dj tips with invalid DJ user references\n";
}

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM Tips t
    LEFT JOIN Users u ON t.CustomerUserID = u.ID
    WHERE t.CustomerUserID IS NOT NULL AND u.ID IS NULL
");
$invalid_customer = $result->fetch_assoc()['count'];
if ($invalid_customer > 0) {
    echo "- $invalid_customer tips with invalid customer user references\n";
}

if ($orphaned == 0 && $invalid_dj == 0 && $invalid_customer == 0) {
    echo "No critical data integrity issues found!\n";
}

$conn->close();

echo "\n=== ANALYSIS COMPLETE ===\n";
?>