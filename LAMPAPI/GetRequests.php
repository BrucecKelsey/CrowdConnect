<?php
// LAMPAPI/GetRequests.php
header('Content-type: application/json');

$partyId = isset($_GET['partyId']) ? intval($_GET['partyId']) : 0;
$sinceId = isset($_GET['sinceId']) ? intval($_GET['sinceId']) : 0;
$retValue = array("requests" => array());
$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error)
{
    echo json_encode($retValue);
    exit();
}

// Use the consolidated Requests table with all payment information stored directly
if ($sinceId > 0) {
    $stmt = $conn->prepare("
        SELECT RequestId, SongName, RequestedBy, Timestamp, 
               COALESCE(PriceOfRequest, 0) as RequestFeeAmount,
               COALESCE(TipAmount, 0) as TipAmount, 
               COALESCE(TotalCollected, 0) as TotalCollected,
               COALESCE(PlatformRevenue, 0) as PlatformRevenue,
               PaymentStatus, ProcessedAt
        FROM Requests 
        WHERE PartyId = ? AND RequestId > ? 
        ORDER BY RequestId ASC
    ");
    $stmt->bind_param("ii", $partyId, $sinceId);
} else {
    $stmt = $conn->prepare("
        SELECT RequestId, SongName, RequestedBy, Timestamp,
               COALESCE(PriceOfRequest, 0) as RequestFeeAmount,
               COALESCE(TipAmount, 0) as TipAmount,
               COALESCE(TotalCollected, 0) as TotalCollected, 
               COALESCE(PlatformRevenue, 0) as PlatformRevenue,
               PaymentStatus, ProcessedAt
        FROM Requests 
        WHERE PartyId = ? 
        ORDER BY Timestamp DESC
    ");
    $stmt->bind_param("i", $partyId);
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $tipAmount = (float)$row['TipAmount'];
    $requestFeeAmount = (float)$row['RequestFeeAmount'];
    $totalCollected = (float)$row['TotalCollected'];
    $platformRevenue = (float)$row['PlatformRevenue'];
    
    // DJ's net earnings is already calculated and stored in TotalCollected
    // TotalCollected = Total customer payment - Platform fee (5%) - Stripe fee (2.9% + $0.30)
    $djTotal = $totalCollected;
    
    // Ensure DJ total is never negative
    $djTotal = max(0, $djTotal);
    
    // Only show earnings if payment was completed
    if ($row['PaymentStatus'] !== 'completed') {
        $djTotal = 0;
        $tipAmount = 0; // Don't show tip amount for incomplete payments
    }
    
    // Add calculated values to response
    $row['DJTotal'] = $djTotal;
    $row['TipAmount'] = $tipAmount; // Use the stored tip amount
    
    // Add debug info for transparency
    $row['DebugInfo'] = [
        'StoredTipAmount' => $tipAmount,
        'StoredRequestFee' => $requestFeeAmount,
        'StoredTotalCollected' => $totalCollected,
        'StoredPlatformRevenue' => $platformRevenue,
        'CalculatedDJTotal' => $djTotal,
        'PaymentStatus' => $row['PaymentStatus'],
        'ProcessedAt' => $row['ProcessedAt']
    ];
    
    $retValue["requests"][] = $row;
}
$stmt->close();
$conn->close();
echo json_encode($retValue);
?>
