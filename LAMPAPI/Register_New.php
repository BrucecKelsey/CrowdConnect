<?php
// Set response headers first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

function getRequestInfo() {
    return json_decode(file_get_contents('php://input'), true);
}

function sendResultInfoAsJson($obj) {
    header('Content-type: application/json');
    echo $obj;
}

function returnWithError($err) {
    $retValue = '{"id":0,"firstName":"","lastName":"","message":"","error":"' . $err . '"}';
    sendResultInfoAsJson($retValue);
}

function returnWithInfo($firstName, $lastName, $id, $message = "") {
    $retValue = '{"id":' . $id . ',"firstName":"' . $firstName . '","lastName":"' . $lastName . '","message":"' . $message . '","error":""}';
    sendResultInfoAsJson($retValue);
}

try {
    $inData = getRequestInfo();
    
    // Debug: Log received data
    error_log("Register.php - Received data: " . json_encode($inData));
    
    if (!$inData) {
        returnWithError("No data received");
        exit;
    }
    
    $login = isset($inData["login"]) ? $inData["login"] : "";
    $password = isset($inData["password"]) ? $inData["password"] : "";
    $firstName = isset($inData["firstName"]) ? $inData["firstName"] : "";
    $lastName = isset($inData["lastName"]) ? $inData["lastName"] : "";
    $email = isset($inData["email"]) ? $inData["email"] : "";

    // Validate required fields
    if (empty($login) || empty($password) || empty($firstName) || empty($lastName) || empty($email)) {
        $missing = [];
        if (empty($login)) $missing[] = 'username';
        if (empty($password)) $missing[] = 'password';
        if (empty($firstName)) $missing[] = 'firstName';
        if (empty($lastName)) $missing[] = 'lastName';
        if (empty($email)) $missing[] = 'email';
        returnWithError("Missing required fields: " . implode(', ', $missing));
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        returnWithError("Invalid email format: " . $email);
        exit;
    }

    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error) {
        error_log("Register.php - Database connection failed: " . $conn->connect_error);
        returnWithError("Database connection failed");
        exit;
    }
    
    // Check if user already exists
    $stmt = $conn->prepare("SELECT ID FROM Users WHERE Login=?");
    if (!$stmt) {
        error_log("Register.php - Prepare statement failed: " . $conn->error);
        returnWithError("Database error");
        exit;
    }
    
    $stmt->bind_param("s", $login);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->fetch_assoc()) {
        returnWithError("Username already exists");
        $stmt->close();
        $conn->close();
        exit;
    }
    
    // Check if Email column exists in database
    $checkColumn = $conn->query("SHOW COLUMNS FROM Users LIKE 'Email'");
    $emailColumnExists = $checkColumn->num_rows > 0;
    
    if ($emailColumnExists) {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT ID FROM Users WHERE Email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->fetch_assoc()) {
            returnWithError("Email already registered");
            $stmt->close();
            $conn->close();
            exit;
        }
        
        // New system with email verification
        error_log("Register.php - Using new email system");
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate simple verification token
        $verificationToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (24 * 3600)); // 24 hours from now
        
        $stmt = $conn->prepare("INSERT INTO Users (FirstName, LastName, Login, Password, Email, EmailVerificationToken, EmailVerificationExpires) VALUES (?,?,?,?,?,?,?)");
        if (!$stmt) {
            error_log("Register.php - Prepare insert failed: " . $conn->error);
            returnWithError("Database error during registration");
            exit;
        }
        
        $stmt->bind_param("sssssss", $firstName, $lastName, $login, $hashedPassword, $email, $verificationToken, $expiresAt);
        
        if ($stmt->execute()) {
            $userId = $conn->insert_id;
            error_log("Register.php - User created successfully with ID: " . $userId);
            returnWithInfo($firstName, $lastName, $userId, "Registration successful! Email verification will be implemented soon.");
        } else {
            error_log("Register.php - Database insert error: " . $stmt->error);
            returnWithError("Registration failed: " . $stmt->error);
        }
        
    } else {
        // Fallback to old system without email
        error_log("Register.php - Using legacy system (no email column)");
        
        $stmt = $conn->prepare("INSERT INTO Users (FirstName,LastName,Login,Password) VALUES (?,?,?,?)");
        if (!$stmt) {
            error_log("Register.php - Prepare legacy insert failed: " . $conn->error);
            returnWithError("Database error");
            exit;
        }
        
        $stmt->bind_param("ssss", $firstName, $lastName, $login, $password);
        
        if ($stmt->execute()) {
            returnWithInfo($firstName, $lastName, $conn->insert_id, "Registration successful! Email system not yet configured.");
        } else {
            error_log("Register.php - Legacy insert error: " . $stmt->error);
            returnWithError("Registration failed: " . $stmt->error);
        }
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("Register.php - Exception: " . $e->getMessage());
    returnWithError("Server error: " . $e->getMessage());
}
?>