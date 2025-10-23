<?php
// LAMPAPI/GetUser.php
header('Content-type: application/json');
$userId = isset($_GET['userId']) ? intval($_GET['userId']) : 0;
$retValue = array("error" => "");
if ($userId <= 0) {
    $retValue["error"] = "Missing or invalid userId.";
    echo json_encode($retValue);
    exit();
}
$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error) {
    $retValue["error"] = $conn->connect_error;
    echo json_encode($retValue);
    exit();
}
$stmt = $conn->prepare("SELECT FirstName, LastName, Login, ActivePartyId FROM Users WHERE ID=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($firstName, $lastName, $login, $activePartyId);
if ($stmt->fetch()) {
    $retValue["FirstName"] = $firstName;
    $retValue["LastName"] = $lastName;
    $retValue["Username"] = $login;
    $retValue["ActivePartyId"] = $activePartyId;
} else {
    $retValue["error"] = "User not found.";
}
$stmt->close();
$conn->close();
echo json_encode($retValue);
?>
