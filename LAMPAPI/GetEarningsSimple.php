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
        returnWithError('Prepare statement failed: ' . $conn->error);
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
    
    // Simple earnings calculation - just return basic info for now
    $stmt = $conn->prepare("SELECT COUNT(*) as tip_count, COALESCE(SUM(TipAmount), 0) as total FROM Tips WHERE DJUserID = ? AND Status = 'completed'");
    if (!$stmt) {
        $conn->close();
        returnWithError('Prepare earnings statement failed: ' . $conn->error);
        exit;
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $earnings = $result->fetch_assoc();
    $stmt->close();
    
    $conn->close();
    
    returnWithInfo([
        'user_name' => $user['FirstName'] . ' ' . $user['LastName'],
        'total_earnings' => (float)($earnings['total'] ?? 0),
        'available_balance' => (float)($earnings['total'] ?? 0),
        'total_tips' => (int)($earnings['tip_count'] ?? 0),
        'last_payout_amount' => 0,
        'last_payout_date' => null,
        'period_earnings' => [
            'today' => ['earnings' => 0, 'tip_count' => 0],
            'this_week' => ['earnings' => 0, 'tip_count' => 0], 
            'this_month' => ['earnings' => (float)($earnings['total'] ?? 0), 'tip_count' => (int)($earnings['tip_count'] ?? 0)]
        ],
        'recent_transactions' => [],
        'success' => true,
        'debug' => [
            'user_id' => $userId,
            'user_found' => $user ? true : false,
            'earnings_raw' => $earnings
        ]
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