<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['loggedin'])) {
    echo json_encode(["success" => false, "message" => "Not logged in."]);
    exit;
}

// Retrieve the JSON payload
$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (!$data || !isset($data['route_name']) || !isset($data['route_data'])) {
    echo json_encode(["success" => false, "message" => "Invalid input."]);
    exit;
}

$route_name = $data['route_name'];
$route_data = json_encode($data['route_data']);  // Save route_data as JSON string

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tracker";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("INSERT INTO user_routes (user_id, route_name, route_data) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $user_id, $route_name, $route_data);
if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to save route."]);
}

$stmt->close();
$conn->close();
?>
