<?php
session_start();
$servername = "localhost";
$username = "root"; 
$password = "";    
$dbname = "tracker"; 

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$user_id = $_SESSION['user_id'];
$weight = $_POST['weight'];
$log_date = $_POST['log_date'];

// Insert data into table
$sql = "INSERT INTO body_weight_logs (user_id, weight, log_date) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ids", $user_id, $weight, $log_date);

if ($stmt->execute()) {
    header("Location: WeightLogger.php?success=1");
    exit();
} else {
    header("Location: WeightLogger.php?error=1");
    exit();
}

$stmt->close();
$conn->close();
?>
