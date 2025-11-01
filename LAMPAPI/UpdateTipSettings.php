<?php
require_once 'Database.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get tip setting for party
    if (!isset($_GET['partyId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing party ID']);
        exit;
    }
    
    $partyId = (int)$_GET['partyId'];
    
    try {
        $conn = new Database();
        $pdo = $conn->getConnection();
        
        $stmt = $pdo->prepare("SELECT AllowTips FROM Parties WHERE PartyId = ?");
        $stmt->execute([$partyId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            throw new Exception('Party not found');
        }
        
        echo json_encode([
            'success' => true,
            'allowTips' => (bool)$result['AllowTips']
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update tip setting for party
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['partyId']) || !isset($input['allowTips'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    $partyId = (int)$input['partyId'];
    $allowTips = (bool)$input['allowTips'];
    
    try {
        $conn = new Database();
        $pdo = $conn->getConnection();
        
        $stmt = $pdo->prepare("UPDATE Parties SET AllowTips = ? WHERE PartyId = ?");
        $stmt->execute([$allowTips ? 1 : 0, $partyId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Party not found or no changes made');
        }
        
        echo json_encode([
            'success' => true,
            'message' => $allowTips ? 'Tips enabled for this event' : 'Tips disabled for this event'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>