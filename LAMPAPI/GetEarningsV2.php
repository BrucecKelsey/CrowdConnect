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

// Set error handler to catch any PHP errors
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    if (!isset($_GET['userId'])) {
        returnWithError('Missing userId parameter');
        exit;
    }

    $userId = (int)$_GET['userId'];

    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        returnWithError($conn->connect_error);
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT FirstName, LastName FROM Users WHERE ID = ?");
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
        
        // Calculate total earnings from EarningsHistory table (more accurate with fees)
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
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $earnings = $result->fetch_assoc();
        $stmt->close();
        
        // Get recent earnings transactions with request details (last 30 days)
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
            LIMIT 50
        ");
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
        
        // Calculate available balance (net earnings minus any payouts - for future implementation)
        $totalNetEarnings = (float)$earnings['total_net'];
        $availableBalance = $totalNetEarnings; // For now, assume no payouts have been made
        
        // Period earnings with accurate net amounts and tip counts
        $periodEarnings = [
            'today' => [
                'earnings' => (float)$earnings['today_earnings'],
                'tip_count' => (int)$earnings['today_tips']
            ],
            'this_week' => [
                'earnings' => (float)$earnings['week_earnings'], 
                'tip_count' => (int)$earnings['week_tips']
            ],
            'this_month' => [
                'earnings' => (float)$earnings['month_earnings'],
                'tip_count' => (int)$earnings['month_tips']
            ]
        ];
        
        $conn->close();
        
        returnWithInfo([
            'user_name' => $user['FirstName'] . ' ' . $user['LastName'],
            'total_gross_earnings' => (float)$earnings['total_gross'],
            'total_stripe_fees' => (float)$earnings['total_fees'],
            'total_net_earnings' => $totalNetEarnings,
            'available_balance' => $availableBalance,
            'total_transactions' => (int)$earnings['total_transactions'],
            'last_payout_amount' => 0, // For future implementation
            'last_payout_date' => null,
            'period_earnings' => $periodEarnings,
            'recent_transactions' => $recentEarnings,
            'data_source' => 'EarningsHistory', // Indicates we're using the proper earnings table
            'success' => true
        ]);
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->close();
        }
        returnWithError('Database error: ' . $e->getMessage());
    } catch (Error $e) {
        if (isset($conn)) {
            $conn->close();
        }
        returnWithError('Fatal error: ' . $e->getMessage());
    }

} catch (Exception $e) {
    returnWithError('System error: ' . $e->getMessage());
}

function returnWithError($err) {
    $retValue = json_encode(array("error" => $err, "success" => false));
    echo $retValue;
}

function returnWithInfo($info) {
    $retValue = json_encode($info);
    echo $retValue;
}
?>