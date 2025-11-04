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
    $retValue["requests"][] = $row;
}
$stmt->close();
$conn->close();
echo json_encode($retValue);
?>
