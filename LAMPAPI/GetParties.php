<?php
// LAMPAPI/GetParties.php
header('Content-type: application/json');
$djId = isset($_GET['djId']) ? intval($_GET['djId']) : 0;
$retValue = array("parties" => array());
$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error)
{
    echo json_encode($retValue);
    exit();
}
$stmt = $conn->prepare("SELECT PartyId, PartyName, AllowTips, AllowRequestFees, RequestFeeAmount FROM Parties WHERE DJId=?");
$stmt->bind_param("i", $djId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $retValue["parties"][] = $row;
}
$stmt->close();
$conn->close();
echo json_encode($retValue);
?>
