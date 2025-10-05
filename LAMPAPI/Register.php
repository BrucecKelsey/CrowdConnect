<?php
    $inData = getRequestInfo();
    $login = $inData["login"];
    $password = $inData["password"];
    $firstName = $inData["firstName"];
    $lastName = $inData["lastName"];
    $email = $inData["email"];

    $conn = new mysqli("localhost", "TheBeast", "WeLoveCOP4331", "COP4331");
    if ($conn->connect_error)
    {
        returnWithError($conn->connect_error);
    }
    else
    {
        // Check if user already exists
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
            $stmt = $conn->prepare("INSERT INTO Users (Login,Password,firstName,lastName,Email) VALUES (?,?,?,?,?)");
            $stmt->bind_param("sssss", $login, $password, $firstName, $lastName, $email);
            if ($stmt->execute())
            {
                returnWithInfo($firstName, $lastName, $conn->insert_id);
            }
            else
            {
                returnWithError($stmt->error);
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
        $retValue = '{"id":0,"firstName":"","lastName":"","error":"' . $err . '"}';
        sendResultInfoAsJson($retValue);
    }
    function returnWithInfo($firstName, $lastName, $id)
    {
        $retValue = '{"id":' . $id . ',"firstName":"' . $firstName . '","lastName":"' . $lastName . '","error":""}';
        sendResultInfoAsJson($retValue);
    }
?>
