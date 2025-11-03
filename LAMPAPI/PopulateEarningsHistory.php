<?php
// Script to populate EarningsHistory from Tips table
header('Content-Type: text/plain');

$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== POPULATING EARNINGS HISTORY ===\n\n";

// Calculate Stripe fees (2.9% + $0.30 per transaction)
function calculateStripeFee($amount) {
    return round(($amount * 0.029) + 0.30, 2);
}

// Get all completed tips that aren't already in EarningsHistory
$result = $conn->query("
    SELECT t.TipId, t.DJUserID, t.TipAmount, t.StripePaymentIntentId, t.Timestamp
    FROM Tips t
    LEFT JOIN EarningsHistory eh ON t.TipId = eh.TipId
    WHERE t.Status = 'completed' AND eh.TipId IS NULL
    ORDER BY t.Timestamp
");

$processed = 0;
$errors = 0;

if ($result->num_rows > 0) {
    echo "Found {$result->num_rows} completed tips to process...\n\n";
    
    while($tip = $result->fetch_assoc()) {
        $grossAmount = $tip['TipAmount'];
        $stripeFee = calculateStripeFee($grossAmount);
        $netAmount = $grossAmount - $stripeFee;
        
        // Insert into EarningsHistory
        $stmt = $conn->prepare("
            INSERT INTO EarningsHistory 
            (UserId, TipId, GrossAmount, StripeFeeAmount, NetAmount, TransactionDate, StripeChargeId, Status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
        ");
        
        $stmt->bind_param("iidddss", 
            $tip['DJUserID'], 
            $tip['TipId'], 
            $grossAmount, 
            $stripeFee, 
            $netAmount, 
            $tip['Timestamp'], 
            $tip['StripePaymentIntentId']
        );
        
        if ($stmt->execute()) {
            echo "✓ Processed TipId {$tip['TipId']}: \${$grossAmount} (Net: \${$netAmount}, Fee: \${$stripeFee})\n";
            $processed++;
        } else {
            echo "✗ Error processing TipId {$tip['TipId']}: " . $stmt->error . "\n";
            $errors++;
        }
        
        $stmt->close();
    }
} else {
    echo "No new completed tips to process.\n";
}

echo "\n=== SUMMARY ===\n";
echo "Processed: $processed earnings records\n";
echo "Errors: $errors\n";

// Show final EarningsHistory stats
$result = $conn->query("
    SELECT 
        COUNT(*) as total_earnings,
        SUM(GrossAmount) as total_gross,
        SUM(StripeFeeAmount) as total_fees,
        SUM(NetAmount) as total_net
    FROM EarningsHistory
");

if ($result && $stats = $result->fetch_assoc()) {
    echo "\nEARNINGS HISTORY TOTALS:\n";
    echo "Records: {$stats['total_earnings']}\n";
    echo "Gross: \${$stats['total_gross']}\n";
    echo "Fees: \${$stats['total_fees']}\n";
    echo "Net: \${$stats['total_net']}\n";
}

$conn->close();
?>