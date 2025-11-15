<?php

	$inData = getRequestInfo();
	
	$id = 0;
	$firstName = "";
	$lastName = "";

	// Set response headers
	header('Content-Type: application/json');
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: POST');
	header('Access-Control-Allow-Headers: Content-Type');

	$conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
	if( $conn->connect_error )
	{
		returnWithError( $conn->connect_error );
	}
	else
	{
		// First, get user info including password hash and email verification status
		$stmt = $conn->prepare("SELECT ID, firstName, lastName, Login, Password, Email, IsEmailVerified FROM Users WHERE Login=?");
		$stmt->bind_param("s", $inData["login"]);
		$stmt->execute();
		$result = $stmt->get_result();

		if( $row = $result->fetch_assoc() )
		{
			// Check if password matches (support both old plain text and new hashed passwords)
			$passwordMatches = false;
			if (password_verify($inData["password"], $row['Password'])) {
				// New hashed password
				$passwordMatches = true;
			} elseif ($row['Password'] === $inData["password"]) {
				// Old plain text password - should be updated
				$passwordMatches = true;
				
				// Update to hashed password
				$hashedPassword = password_hash($inData["password"], PASSWORD_DEFAULT);
				$updateStmt = $conn->prepare("UPDATE Users SET Password = ? WHERE ID = ?");
				$updateStmt->bind_param("si", $hashedPassword, $row['ID']);
				$updateStmt->execute();
				$updateStmt->close();
			}
			
			if ($passwordMatches) {
				// Check if email is verified (only if email column exists)
				if (isset($row['IsEmailVerified']) && !$row['IsEmailVerified']) {
					returnWithError("Please verify your email address before logging in. Check your email for a verification link.");
				} else {
					$response = array(
						"id" => $row['ID'],
						"firstName" => $row['firstName'],
						"lastName" => $row['lastName'],
						"login" => $row['Login'],
						"email" => isset($row['Email']) ? $row['Email'] : "",
						"emailVerified" => isset($row['IsEmailVerified']) ? $row['IsEmailVerified'] : true,
						"token" => bin2hex(random_bytes(16))
					);
					sendResultInfoAsJson(json_encode($response));
				}
			} else {
				returnWithError("Invalid username or password");
			}
		}
		else
		{
			returnWithError("Invalid username or password");
		}

		$stmt->close();
		$conn->close();
	}
	
	function getRequestInfo()
	{
		return json_decode(file_get_contents('php://input'), true);
	}

	function sendResultInfoAsJson( $obj )
	{
		header('Content-type: application/json');
		echo $obj;
	}
	
	function returnWithError( $err )
	{
		$retValue = '{"id":0,"firstName":"","lastName":"","error":"' . $err . '"}';
		sendResultInfoAsJson( $retValue );
	}
	
	function returnWithInfo( $firstName, $lastName, $id )
	{
		$retValue = '{"id":' . $id . ',"firstName":"' . $firstName . '","lastName":"' . $lastName . '","error":""}';
		sendResultInfoAsJson( $retValue );
	}
	
?>
