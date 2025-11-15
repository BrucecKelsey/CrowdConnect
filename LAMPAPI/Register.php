<?php
    // Try to include EmailService, but don't fail if it doesn't exist
    if (file_exists('EmailService.php')) {
        require_once 'EmailService.php';
    }
    
    // Set response headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');
    
    $inData = getRequestInfo();
    $login = $inData["login"];
    $password = $inData["password"];
    $firstName = $inData["firstName"];
    $lastName = $inData["lastName"];
    $email = $inData["email"];

    // Debug: Log received data
    error_log("Register.php - Received data: " . json_encode($inData));
    
    // Validate required fields
    if (empty($login) || empty($password) || empty($firstName) || empty($lastName) || empty($email)) {
        $missing = [];
        if (empty($login)) $missing[] = 'username';
        if (empty($password)) $missing[] = 'password';
        if (empty($firstName)) $missing[] = 'firstName';
        if (empty($lastName)) $missing[] = 'lastName';
        if (empty($email)) $missing[] = 'email';
        returnWithError("Missing required fields: " . implode(', ', $missing));
        return;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        returnWithError("Invalid email format: " . $email);
        return;
    }

    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error)
    {
        error_log("Register.php - Database connection failed: " . $conn->connect_error);
        returnWithError("Database connection failed: " . $conn->connect_error);
    }
    else
    {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT ID FROM Users WHERE Login=?");
        $stmt->bind_param("s", $login);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->fetch_assoc())
        {
            returnWithError("Username already exists");
        }
        else
        {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT ID FROM Users WHERE Email=?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->fetch_assoc())
            {
                returnWithError("Email already registered");
            }
            else
            {
                // Check if Email column exists in database
                $checkColumn = $conn->query("SHOW COLUMNS FROM Users LIKE 'Email'");
                $emailColumnExists = $checkColumn->num_rows > 0;
                
                if ($emailColumnExists) {
                    // New system with email verification
                    error_log("Register.php - Using new email system");
                    
                    // Hash password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Generate email verification token
                    try {
                        $verificationToken = EmailService::generateToken();
                        $expiresAt = EmailService::getExpirationTime(EmailConfig::EMAIL_VERIFICATION_EXPIRES);
                    } catch (Exception $e) {
                        error_log("Register.php - EmailService error: " . $e->getMessage());
                        $verificationToken = bin2hex(random_bytes(32));
                        $expiresAt = date('Y-m-d H:i:s', time() + (24 * 3600));
                    }
                    
                    $stmt = $conn->prepare("INSERT INTO Users (FirstName, LastName, Login, Password, Email, EmailVerificationToken, EmailVerificationExpires) VALUES (?,?,?,?,?,?,?)");
                    $stmt->bind_param("sssssss", $firstName, $lastName, $login, $hashedPassword, $email, $verificationToken, $expiresAt);
                    
                    if ($stmt->execute())
                    {
                        $userId = $conn->insert_id;
                        
                        // Send verification email
                        try {
                            if (class_exists('EmailService') && EmailService::sendEmailVerification($email, $firstName, $verificationToken)) {
                                returnWithInfo($firstName, $lastName, $userId, "Registration successful! Please check your email to verify your account.");
                            } else {
                                returnWithInfo($firstName, $lastName, $userId, "Registration successful! However, verification email could not be sent. Please try resending it.");
                            }
                        } catch (Exception $e) {
                            error_log("Register.php - Email send error: " . $e->getMessage());
                            returnWithInfo($firstName, $lastName, $userId, "Registration successful! Email system temporarily unavailable.");
                        }
                    }
                    else
                    {
                        error_log("Register.php - Database insert error: " . $stmt->error);
                        returnWithError("Registration failed: " . $stmt->error);
                    }
                } else {
                    // Fallback to old system without email
                    error_log("Register.php - Using legacy system (no email column)");
                    
                    $stmt = $conn->prepare("INSERT INTO Users (FirstName,LastName,Login,Password) VALUES (?,?,?,?)");
                    $stmt->bind_param("ssss", $firstName, $lastName, $login, $password);
                    
                    if ($stmt->execute())
                    {
                        returnWithInfo($firstName, $lastName, $conn->insert_id, "Registration successful! Email system not yet configured.");
                    }
                    else
                    {
                        error_log("Register.php - Legacy insert error: " . $stmt->error);
                        returnWithError("Registration failed: " . $stmt->error);
                    }
                }
            }
        }
        $stmt->close();
        $conn->close();
    }

    function getRequestInfo()
    {
        return json_decode(file_get_contents('php://input'), true);
    }
    function sendResultInfoAsJson($obj)
    {
        header('Content-type: application/json');
        echo $obj;
    }
    function returnWithError($err)
    {
        $retValue = '{"id":0,"firstName":"","lastName":"","message":"","error":"' . $err . '"}';
        sendResultInfoAsJson($retValue);
    }
    function returnWithInfo($firstName, $lastName, $id, $message = "")
    {
        $retValue = '{"id":' . $id . ',"firstName":"' . $firstName . '","lastName":"' . $lastName . '","message":"' . $message . '","error":""}';
        sendResultInfoAsJson($retValue);
    }
?>
