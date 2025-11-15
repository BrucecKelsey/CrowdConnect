<?php
    require_once 'EmailService.php';
    
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

    // Validate required fields
    if (empty($login) || empty($password) || empty($firstName) || empty($lastName) || empty($email)) {
        returnWithError("All fields are required");
        return;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        returnWithError("Invalid email format");
        return;
    }

    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error)
    {
        returnWithError($conn->connect_error);
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
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Generate email verification token
                $verificationToken = EmailService::generateToken();
                $expiresAt = EmailService::getExpirationTime(EmailConfig::EMAIL_VERIFICATION_EXPIRES);
                
                $stmt = $conn->prepare("INSERT INTO Users (FirstName, LastName, Login, Password, Email, EmailVerificationToken, EmailVerificationExpires) VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param("sssssss", $firstName, $lastName, $login, $hashedPassword, $email, $verificationToken, $expiresAt);
                
                if ($stmt->execute())
                {
                    $userId = $conn->insert_id;
                    
                    // Send verification email
                    if (EmailService::sendEmailVerification($email, $firstName, $verificationToken)) {
                        returnWithInfo($firstName, $lastName, $userId, "Registration successful! Please check your email to verify your account.");
                    } else {
                        returnWithInfo($firstName, $lastName, $userId, "Registration successful! However, verification email could not be sent. Please try resending it.");
                    }
                }
                else
                {
                    returnWithError($stmt->error);
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
