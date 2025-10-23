<?php
// LAMPAPI/ChangePassword.php
header('Content-type: application/json');
$inData = json_decode(file_get_contents('php://input'), true);
$userId = isset($inData['userId']) ? intval($inData['userId']) : 0;
$currentPassword = isset($inData['currentPassword']) ? $inData['currentPassword'] : '';
$newPassword = isset($inData['newPassword']) ? $inData['newPassword'] : '';
$retValue = array("error" => "");
if ($userId <= 0 || !$currentPassword || !$newPassword) {
    $retValue["error"] = "Missing userId, current password, or new password.";
    echo json_encode($retValue);
    exit();
}
$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error) {
    $retValue["error"] = $conn->connect_error;
    echo json_encode($retValue);
    exit();
}
// Check current password
$stmt = $conn->prepare("SELECT Password FROM Users WHERE ID=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($dbPassword);
if ($stmt->fetch()) {
    if ($dbPassword !== $currentPassword) {
        $retValue["error"] = "Current password is incorrect.";
        $stmt->close();
        $conn->close();
        echo json_encode($retValue);
        exit();
    }
} else {
    $retValue["error"] = "User not found.";
    $stmt->close();
    $conn->close();
    echo json_encode($retValue);
    exit();
}
$stmt->close();
// Update password
$stmt = $conn->prepare("UPDATE Users SET Password=? WHERE ID=?");
$stmt->bind_param("si", $newPassword, $userId);
if (!$stmt->execute()) {
    $retValue["error"] = $stmt->error;
}
$stmt->close();
$conn->close();
echo json_encode($retValue);
?>
