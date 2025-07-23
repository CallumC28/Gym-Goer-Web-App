<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['goal_id']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
    exit;
}

$goal_id = intval($data['goal_id']);
$status = $data['status'];

if (!in_array($status, ['completed', 'failed'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid status.']);
    exit;
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tracker";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Connection failed.']);
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE user_goals SET status = ? WHERE goal_id = ? AND user_id = ?");
$stmt->bind_param("sii", $status, $goal_id, $user_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}
$stmt->close();
$conn->close();
?>
