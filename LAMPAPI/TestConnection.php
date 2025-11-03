<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Test database connection
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    
    if ($conn->connect_error) {
        echo json_encode([
            'success' => false,
            'error' => 'Connection failed: ' . $conn->connect_error
        ]);
        exit;
    }
    
    // Test basic query
    $result = $conn->query("SELECT COUNT(*) as count FROM Users");
    if ($result) {
        $row = $result->fetch_assoc();
        $userCount = $row['count'];
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Query failed: ' . $conn->error
        ]);
        exit;
    }
    
    // Test Tips table
    $result = $conn->query("SELECT COUNT(*) as count FROM Tips");
    if ($result) {
        $row = $result->fetch_assoc();
        $tipCount = $row['count'];
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Tips query failed: ' . $conn->error
        ]);
        exit;
    }
    
    // Test specific user
    $stmt = $conn->prepare("SELECT ID, FirstName, LastName FROM Users WHERE ID = ? LIMIT 1");
    $testUserId = 1;
    $stmt->bind_param("i", $testUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $testUser = $result->fetch_assoc();
    $stmt->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'database_connection' => 'OK',
        'total_users' => $userCount,
        'total_tips' => $tipCount,
        'test_user' => $testUser,
        'php_version' => phpversion(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Exception: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Fatal Error: ' . $e->getMessage()
    ]);
}
?>