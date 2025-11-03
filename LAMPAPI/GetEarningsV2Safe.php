<?php
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
    echo json_encode(array("error" => $err, "success" => false));
}

function returnWithInfo($info) {
    echo json_encode($info);
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
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT FirstName, LastName FROM Users WHERE ID = ?");
    if (!$stmt) {
        $conn->close();
        returnWithError('Prepare user statement failed: ' . $conn->error);
        exit;
    }
    
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
    
    // First check if EarningsHistory table exists and has data
    $earningsHistoryExists = false;
    $result = $conn->query("SHOW TABLES LIKE 'EarningsHistory'");
    if ($result && $result->num_rows > 0) {
        // Table exists, check if it has data for this user
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM EarningsHistory WHERE UserId = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            if ($row['count'] > 0) {
                $earningsHistoryExists = true;
            }
        }
    }
    
    if ($earningsHistoryExists) {
        // Use EarningsHistory table for accurate fee calculations
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(GrossAmount), 0) as total_gross,
                COALESCE(SUM(StripeFeeAmount), 0) as total_fees,
                COALESCE(SUM(NetAmount), 0) as total_net,
                COUNT(*) as total_transactions,
                COALESCE(SUM(CASE WHEN TransactionDate >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN NetAmount ELSE 0 END), 0) as today_earnings,
                COALESCE(SUM(CASE WHEN TransactionDate >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN NetAmount ELSE 0 END), 0) as week_earnings,
                COALESCE(SUM(CASE WHEN TransactionDate >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN NetAmount ELSE 0 END), 0) as month_earnings,
                COUNT(CASE WHEN TransactionDate >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as today_tips,
                COUNT(CASE WHEN TransactionDate >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_tips,
                COUNT(CASE WHEN TransactionDate >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as month_tips
            FROM EarningsHistory 
            WHERE UserId = ? AND Status = 'completed'
        ");
        
        if (!$stmt) {
            $conn->close();
            returnWithError('Prepare EarningsHistory statement failed: ' . $conn->error);
            exit;
        }
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $earnings = $result->fetch_assoc();
        $stmt->close();
        
        // Get recent earnings transactions
        $stmt = $conn->prepare("
            SELECT 
                eh.GrossAmount,
                eh.StripeFeeAmount,
                eh.NetAmount,
                eh.TransactionDate,
                eh.StripeChargeId,
                r.SongName,
                r.RequestedBy,
                r.RequestId,
                t.TipId
            FROM EarningsHistory eh
            LEFT JOIN Tips t ON eh.TipId = t.TipId
            LEFT JOIN Requests r ON t.RequestId = r.RequestId
            WHERE eh.UserId = ? AND eh.TransactionDate >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND eh.Status = 'completed'
            ORDER BY eh.TransactionDate DESC
            LIMIT 20
        ");
        
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $recentEarnings = [];
            while ($row = $result->fetch_assoc()) {
                $recentEarnings[] = [
                    'gross_amount' => (float)$row['GrossAmount'],
                    'stripe_fee' => (float)$row['StripeFeeAmount'],
                    'net_amount' => (float)$row['NetAmount'],
                    'date' => $row['TransactionDate'],
                    'song_name' => $row['SongName'] ?: 'Direct Tip',
                    'from_user' => $row['RequestedBy'] ?: 'Anonymous',
                    'request_id' => $row['RequestId'],
                    'tip_id' => $row['TipId'],
                    'stripe_charge_id' => $row['StripeChargeId']
                ];
            }
            $stmt->close();
        } else {
            $recentEarnings = [];
        }
        
        $dataSource = 'EarningsHistory';
        
    } else {
        // Fallback to Tips table
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN Status = 'completed' THEN TipAmount ELSE 0 END), 0) as total_earnings,
                COUNT(CASE WHEN Status = 'completed' THEN 1 END) as completed_tips,
                COALESCE(SUM(CASE WHEN Status = 'completed' AND Timestamp >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN TipAmount ELSE 0 END), 0) as today_earnings,
                COALESCE(SUM(CASE WHEN Status = 'completed' AND Timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN TipAmount ELSE 0 END), 0) as week_earnings,
                COALESCE(SUM(CASE WHEN Status = 'completed' AND Timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN TipAmount ELSE 0 END), 0) as month_earnings
            FROM Tips 
            WHERE DJUserID = ?
        ");
        
        if (!$stmt) {
            $conn->close();
            returnWithError('Prepare Tips statement failed: ' . $conn->error);
            exit;
        }
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $earnings = $result->fetch_assoc();
        $stmt->close();
        
        // Estimate fees (2.9% + $0.30 per transaction)
        $grossAmount = (float)$earnings['total_earnings'];
        $estimatedFees = $grossAmount > 0 ? ($grossAmount * 0.029) + (0.30 * $earnings['completed_tips']) : 0;
        $netAmount = $grossAmount - $estimatedFees;
        
        // Convert Tips format to EarningsHistory format
        $earnings = [
            'total_gross' => $grossAmount,
            'total_fees' => $estimatedFees,
            'total_net' => $netAmount,
            'total_transactions' => $earnings['completed_tips'],
            'today_earnings' => (float)$earnings['today_earnings'],
            'week_earnings' => (float)$earnings['week_earnings'],
            'month_earnings' => (float)$earnings['month_earnings'],
            'today_tips' => 0, // Would need separate query
            'week_tips' => 0,
            'month_tips' => $earnings['completed_tips']
        ];
        
        $recentEarnings = [];
        $dataSource = 'Tips (fallback)';
    }
    
    // Calculate available balance
    $totalNetEarnings = (float)($earnings['total_net'] ?? 0);
    $availableBalance = $totalNetEarnings;
    
    // Period earnings
    $periodEarnings = [
        'today' => [
            'earnings' => (float)($earnings['today_earnings'] ?? 0),
            'tip_count' => (int)($earnings['today_tips'] ?? 0)
        ],
        'this_week' => [
            'earnings' => (float)($earnings['week_earnings'] ?? 0), 
            'tip_count' => (int)($earnings['week_tips'] ?? 0)
        ],
        'this_month' => [
            'earnings' => (float)($earnings['month_earnings'] ?? 0),
            'tip_count' => (int)($earnings['month_tips'] ?? 0)
        ]
    ];
    
    $conn->close();
    
    returnWithInfo([
        'user_name' => $user['FirstName'] . ' ' . $user['LastName'],
        'total_gross_earnings' => (float)($earnings['total_gross'] ?? 0),
        'total_stripe_fees' => (float)($earnings['total_fees'] ?? 0),
        'total_net_earnings' => $totalNetEarnings,
        'available_balance' => $availableBalance,
        'total_transactions' => (int)($earnings['total_transactions'] ?? 0),
        'last_payout_amount' => 0,
        'last_payout_date' => null,
        'period_earnings' => $periodEarnings,
        'recent_transactions' => $recentEarnings,
        'data_source' => $dataSource,
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
} catch (Throwable $e) {
    if (isset($conn) && $conn) {
        $conn->close();
    }
    returnWithError('Unexpected error: ' . $e->getMessage());
}
?>