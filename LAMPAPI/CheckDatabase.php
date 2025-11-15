<?php
// Database structure checker
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
        exit;
    }
    
    // Get Users table structure
    $result = $conn->query("DESCRIBE Users");
    $columns = [];
    
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }
    
    echo json_encode([
        "success" => true,
        "table_structure" => $columns
    ], JSON_PRETTY_PRINT);
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(["error" => "Error: " . $e->getMessage()]);
}
?>