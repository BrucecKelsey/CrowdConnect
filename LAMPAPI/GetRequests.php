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

// First get the party's request fee settings
$requestFeeAmount = 0;
$partyStmt = $conn->prepare("SELECT RequestFeeAmount FROM Parties WHERE PartyId = ?");
$partyStmt->bind_param("i", $partyId);
$partyStmt->execute();
$partyResult = $partyStmt->get_result();
if ($partyRow = $partyResult->fetch_assoc()) {
    $requestFeeAmount = (float)$partyRow['RequestFeeAmount'];
}
$partyStmt->close();
if ($sinceId > 0) {
    $stmt = $conn->prepare("SELECT r.RequestId, r.SongName, r.RequestedBy, r.Timestamp, COALESCE(t.TipAmount, 0) as TipAmount FROM Requests r LEFT JOIN Tips t ON r.RequestId = t.RequestId AND t.Status = 'completed' WHERE r.PartyId=? AND r.RequestId > ? ORDER BY r.RequestId ASC");
    $stmt->bind_param("ii", $partyId, $sinceId);
} else {
    $stmt = $conn->prepare("SELECT r.RequestId, r.SongName, r.RequestedBy, r.Timestamp, COALESCE(t.TipAmount, 0) as TipAmount FROM Requests r LEFT JOIN Tips t ON r.RequestId = t.RequestId AND t.Status = 'completed' WHERE r.PartyId=? ORDER BY r.Timestamp DESC");
    $stmt->bind_param("i", $partyId);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Calculate the total amount the DJ receives (only if there was actually a payment)
    $tipAmount = (float)$row['TipAmount'];
    $djTotal = 0;
    
    // Only calculate earnings if there was actually a tip payment
    // DJ only gets the tip portion minus fees (request fees go to platform)
    if ($tipAmount > 0) {
        // Calculate fees only on the tip amount (not the request fee)
        $processingFee = round(($tipAmount * 0.075) + 0.30, 2); // Processing fee: 7.5% + $0.30
        
        // DJ gets only the tip amount minus processing fees
        $djTotal = $tipAmount - $processingFee;
        
        // Ensure DJ total is never negative
        $djTotal = max(0, $djTotal);
    }
    
    // If there's only a request fee (no tip), DJ gets $0
    // Request fees go entirely to the platform
    
    // Add the calculated total to the row
    $row['DJTotal'] = $djTotal;
    $row['RequestFeeAmount'] = $requestFeeAmount;
    
    // Add debug info (can be removed later)
    $row['DebugInfo'] = [
        'TipAmount' => $tipAmount,
        'RequestFee' => $requestFeeAmount,
        'GrossPayment' => ($tipAmount > 0) ? $requestFeeAmount + $tipAmount : 0,
        'ProcessingFee' => ($tipAmount > 0) ? round((($requestFeeAmount + $tipAmount) * 0.075) + 0.30, 2) : 0,
        'HasPayment' => $tipAmount > 0,
        'CalculatedTotal' => $djTotal
    ];
    
    $retValue["requests"][] = $row;
}
$stmt->close();
$conn->close();
echo json_encode($retValue);
?>
