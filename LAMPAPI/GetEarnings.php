<?php
// DEPRECATED: This API uses legacy fee calculations
// Use GetEarningsV2.php for official CrowdConnect fee structure (Platform: 5%, Stripe: 2.9% + $0.30)

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function returnWithError($err) {
    $retValue = json_encode(array("error" => $err, "success" => false));
    echo $retValue;
}

function returnWithInfo($info) {
    $retValue = json_encode($info);
    echo $retValue;
}

try {
    if (!isset($_GET['userId'])) {
        returnWithError('Missing userId parameter');
        exit;
    }

    $userId = (int)$_GET['userId'];
    
    if ($userId <= 0) {
        returnWithError('Invalid userId parameter');
        exit;
    }

    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        returnWithError('Database connection failed: ' . $conn->connect_error);
        exit;
    }
    // Check if user exists and get available funds
    $stmt = $conn->prepare("SELECT FirstName, LastName, AvailableFunds FROM Users WHERE ID = ?");
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
    
    // Calculate net earnings from Tips table (after Stripe 2.9% + $0.30 fee only)
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE 
                WHEN Status = 'completed' THEN 
                    TipAmount - (TipAmount * 0.029 + 0.30)
                ELSE 0 END), 0) as total_earnings,
            COUNT(CASE WHEN Status = 'completed' THEN 1 END) as completed_tips,
            COALESCE(SUM(CASE 
                WHEN Status = 'completed' AND Timestamp >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 
                    TipAmount - (TipAmount * 0.029 + 0.30)
                ELSE 0 END), 0) as today_earnings,
            COALESCE(SUM(CASE 
                WHEN Status = 'completed' AND Timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 
                    TipAmount - (TipAmount * 0.029 + 0.30)
                ELSE 0 END), 0) as week_earnings,
            COALESCE(SUM(CASE 
                WHEN Status = 'completed' AND Timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 
                    TipAmount - (TipAmount * 0.029 + 0.30)
                ELSE 0 END), 0) as month_earnings
        FROM Tips 
        WHERE DJUserID = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $earnings = $result->fetch_assoc();
    $stmt->close();
    
    // Get recent tip transactions (last 30 days)
    $stmt = $conn->prepare("
        SELECT 
            t.TipAmount,
            t.Timestamp,
            t.Status,
            r.SongName,
            r.RequestedBy,
            r.RequestId
        FROM Tips t
        LEFT JOIN Requests r ON t.RequestId = r.RequestId
        WHERE t.DJUserID = ? AND t.Timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND t.Status = 'completed'
        ORDER BY t.Timestamp DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $recentEarnings = [];
    while ($row = $result->fetch_assoc()) {
        $grossAmount = (float)$row['TipAmount'];
        $processingFee = round(($grossAmount * 0.029) + 0.30, 2);
        $netAmount = $grossAmount - $processingFee;
        
        $recentEarnings[] = [
            'amount' => $netAmount,
            'gross_amount' => $grossAmount,
            'processing_fee' => $processingFee,
            'date' => $row['Timestamp'],
            'song_name' => $row['SongName'] ?: 'Unknown Song',
            'from_user' => $row['RequestedBy'] ?: 'Anonymous',
            'request_id' => $row['RequestId']
        ];
    }
    $stmt->close();
    
    // Use AvailableFunds from Users table for accurate balance
    $availableBalance = (float)$user['AvailableFunds'];
    
    // Period earnings from our calculated data
    $periodEarnings = [
        'today' => [
            'earnings' => (float)$earnings['today_earnings'],
            'tip_count' => 0 // We'd need another query for exact counts per period
        ],
        'this_week' => [
            'earnings' => (float)$earnings['week_earnings'], 
            'tip_count' => 0
        ],
        'this_month' => [
            'earnings' => (float)$earnings['month_earnings'],
            'tip_count' => (int)$earnings['completed_tips']
        ]
    ];
    
    $conn->close();
    
    returnWithInfo([
        'user_name' => $user['FirstName'] . ' ' . $user['LastName'],
        'available_balance' => $availableBalance,
        'last_payout_amount' => 0, // No payout system implemented yet
        'last_payout_date' => null,
        'period_earnings' => $periodEarnings,
        'recent_transactions' => $recentEarnings,
        'success' => true
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn) {
        $conn->close();
    }
    returnWithError('Database error: ' . $e->getMessage());
} catch (Error $e) {
    if (isset($conn) && $conn) {
        $conn->close();
    }
    returnWithError('Fatal error: ' . $e->getMessage());
}
?>