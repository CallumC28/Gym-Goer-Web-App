<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tracker";

session_start();
$user_id = $_SESSION['user_id'];

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->begin_transaction();

try {
    // Delete data from the body_weight_logs table
    $sql_weight = "DELETE FROM body_weight_logs WHERE user_id = ?";
    $stmt_weight = $conn->prepare($sql_weight);
    $stmt_weight->bind_param("i", $user_id);
    $stmt_weight->execute();

    // Delete the user from the users table
    $sql_user = "DELETE FROM users WHERE user_id = ?";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();

    // Commit the transaction
    $conn->commit();

    // Log the user out by destroying the session
    session_destroy();

    // Redirect to the home page after deletion
    header("Location: index.html"); // Redirect to index.html
    exit();
} catch (Exception $e) {
    // Rollback if something goes wrong
    $conn->rollback();
    echo "Error deleting profile: " . $e->getMessage();
}

// Close the connection
$stmt_weight->close();
$stmt_user->close();
$conn->close();
?>
