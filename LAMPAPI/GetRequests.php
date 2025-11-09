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
    // Calculate the total amount the DJ receives (as requested: price + tip - fees)
    $tipAmount = (float)$row['TipAmount'];
    $grossAmount = $requestFeeAmount + $tipAmount;
    
    // Calculate what the DJ receives from the total payment
    $djTotal = 0;
    if ($grossAmount > 0) {
        // Calculate fees on the total amount
        $stripeFee = round(($grossAmount * 0.029) + 0.30, 2); // Stripe fee: 2.9% + $0.30
        $platformFee = round($grossAmount * 0.10, 2); // Platform fee: 10%
        $djTotal = $grossAmount - $stripeFee - $platformFee;
        
        // Ensure DJ total is never negative
        $djTotal = max(0, $djTotal);
    }
    
    // Add the calculated total to the row
    $row['DJTotal'] = $djTotal;
    $row['RequestFeeAmount'] = $requestFeeAmount;
    $row['GrossAmount'] = $grossAmount;
    
    $retValue["requests"][] = $row;
}
$stmt->close();
$conn->close();
echo json_encode($retValue);
?>
