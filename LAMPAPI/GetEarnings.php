<?php
require_once 'Database.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if (!isset($_GET['userId'])) {
    returnWithError('Missing userId parameter');
    exit;
}

$userId = (int)$_GET['userId'];

$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
if ($conn->connect_error) {
    returnWithError($conn->connect_error);
} else {
    // Get user's total earnings and payout info
    $stmt = $conn->prepare("SELECT TotalEarnings, LastPayoutAmount, LastPayoutDate FROM Users WHERE ID = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        $conn->close();
        returnWithError('User not found');
        exit;
    }
    
    // Get recent earnings history (last 30 days)
    $stmt = $conn->prepare("
        SELECT eh.*, t.RequestId, r.SongName, r.RequestedBy 
        FROM EarningsHistory eh
        LEFT JOIN Tips t ON eh.TipId = t.TipId
        LEFT JOIN Requests r ON t.RequestId = r.RequestId
        WHERE eh.UserId = ? AND eh.TransactionDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY eh.TransactionDate DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $recentEarnings = [];
    while ($row = $result->fetch_assoc()) {
        $recentEarnings[] = $row;
    }
    $stmt->close();
    
    // Calculate different time periods
    $periods = ['today', 'this_week', 'this_month'];
    $periodEarnings = [];
    
    foreach ($periods as $period) {
        $dateCondition = '';
        switch ($period) {
            case 'today':
                $dateCondition = "DATE(eh.TransactionDate) = CURDATE()";
                break;
            case 'this_week':
                $dateCondition = "YEARWEEK(eh.TransactionDate) = YEARWEEK(NOW())";
                break;
            case 'this_month':
                $dateCondition = "YEAR(eh.TransactionDate) = YEAR(NOW()) AND MONTH(eh.TransactionDate) = MONTH(NOW())";
                break;
        }
        
        $stmt = $conn->prepare("
            SELECT SUM(eh.NetAmount) as total_earnings, COUNT(*) as tip_count
            FROM EarningsHistory eh 
            WHERE eh.UserId = ? AND $dateCondition
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $periodData = $result->fetch_assoc();
        $stmt->close();
        
        $periodEarnings[$period] = [
            'earnings' => (float)($periodData['total_earnings'] ?? 0),
            'tip_count' => (int)($periodData['tip_count'] ?? 0)
        ];
    }
    
    // Calculate available balance (total earnings minus last payout)
    $totalEarnings = (float)$user['TotalEarnings'];
    $lastPayout = (float)($user['LastPayoutAmount'] ?? 0);
    $availableBalance = $totalEarnings - $lastPayout;
    
    $conn->close();
    
    returnWithInfo([
        'total_earnings' => $totalEarnings,
        'available_balance' => $availableBalance,
        'last_payout_amount' => $lastPayout,
        'last_payout_date' => $user['LastPayoutDate'],
        'period_earnings' => $periodEarnings,
        'recent_transactions' => $recentEarnings
    ]);
}

function returnWithError($err) {
    $retValue = json_encode(array("error" => $err));
    echo $retValue;
}

function returnWithInfo($info) {
    $retValue = json_encode($info);
    echo $retValue;
}
?>