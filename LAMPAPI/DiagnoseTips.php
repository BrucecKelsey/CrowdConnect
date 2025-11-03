<?php
// Diagnostic script to check Tips table data integrity
header('Content-Type: application/json');

$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== TIPS DATA INTEGRITY ANALYSIS ===\n\n";

// 1. Check all tips in Tips table
echo "1. ALL TIPS IN TIPS TABLE:\n";
$result = $conn->query("SELECT * FROM Tips ORDER BY Timestamp DESC");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "TipId: {$row['TipId']}, RequestId: {$row['RequestId']}, DJUserID: {$row['DJUserID']}, CustomerUserID: {$row['CustomerUserID']}, Amount: {$row['TipAmount']}, Status: {$row['Status']}, PaymentIntentId: {$row['StripePaymentIntentId']}\n";
    }
} else {
    echo "No tips found\n";
}

echo "\n2. TIPS WITH MISSING USER REFERENCES:\n";
// Check for tips with DJUserID that don't exist in Users table
$result = $conn->query("
    SELECT t.*, 'DJ User Missing' as Issue 
    FROM Tips t 
    LEFT JOIN Users u ON t.DJUserID = u.ID 
    WHERE u.ID IS NULL AND t.DJUserID IS NOT NULL
    UNION
    SELECT t.*, 'Customer User Missing' as Issue 
    FROM Tips t 
    LEFT JOIN Users u ON t.CustomerUserID = u.ID 
    WHERE u.ID IS NULL AND t.CustomerUserID IS NOT NULL
");

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "TipId: {$row['TipId']}, Issue: {$row['Issue']}, DJUserID: {$row['DJUserID']}, CustomerUserID: {$row['CustomerUserID']}\n";
    }
} else {
    echo "No missing user references found\n";
}

echo "\n3. TIPS WITH MISSING REQUEST REFERENCES:\n";
// Check for tips with RequestId that don't exist in Requests table
$result = $conn->query("
    SELECT t.*, 'Request Missing' as Issue 
    FROM Tips t 
    LEFT JOIN Requests r ON t.RequestId = r.RequestId 
    WHERE r.RequestId IS NULL AND t.RequestId IS NOT NULL
");

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "TipId: {$row['TipId']}, RequestId: {$row['RequestId']}, Amount: {$row['TipAmount']}\n";
    }
} else {
    echo "No missing request references found\n";
}

echo "\n4. TIPS WITH NULL FOREIGN KEYS:\n";
// Check for tips with NULL foreign keys
$result = $conn->query("
    SELECT * FROM Tips 
    WHERE RequestId IS NULL OR DJUserID IS NULL OR CustomerUserID IS NULL
");

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "TipId: {$row['TipId']}, RequestId: {$row['RequestId']}, DJUserID: {$row['DJUserID']}, CustomerUserID: {$row['CustomerUserID']}, Status: {$row['Status']}\n";
    }
} else {
    echo "No tips with NULL foreign keys found\n";
}

echo "\n5. USERS TABLE SAMPLE:\n";
$result = $conn->query("SELECT ID, FirstName, LastName FROM Users LIMIT 10");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "UserID: {$row['ID']}, Name: {$row['FirstName']} {$row['LastName']}\n";
    }
}

echo "\n6. REQUESTS TABLE SAMPLE:\n";
$result = $conn->query("SELECT RequestId, SongName, RequestedBy FROM Requests LIMIT 10");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "RequestId: {$row['RequestId']}, Song: {$row['SongName']}, By: {$row['RequestedBy']}\n";
    }
}

$conn->close();
?>