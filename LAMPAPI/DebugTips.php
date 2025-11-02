<?php
// LAMPAPI/DebugTips.php - Debug endpoint to check Tips table

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

try {
    // Get all tips with their request information
    $stmt = $conn->prepare("
        SELECT 
            t.TipId,
            t.RequestId,
            t.DJUserID,
            t.CustomerUserID,
            t.TipAmount,
            t.StripePaymentIntentId,
            t.Status,
            t.Timestamp,
            r.SongName,
            r.RequestedBy,
            r.PartyId,
            u.FirstName,
            u.LastName
        FROM Tips t
        LEFT JOIN Requests r ON t.RequestId = r.RequestId
        LEFT JOIN Users u ON t.DJUserID = u.ID
        ORDER BY t.Timestamp DESC
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tips = [];
    while ($row = $result->fetch_assoc()) {
        $tips[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'tip_count' => count($tips),
        'tips' => $tips
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
}

$conn->close();
?>