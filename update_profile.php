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

// Initialise variables for form submission
$new_username = '';
$new_password = '';
$update_message = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get new data from the form
    $new_username = $_POST['username'];
    $new_password = $_POST['password'];

    // Username validation
    if (!empty($new_username)) {
        // Check if the new username already exists
        $sql_check = "SELECT * FROM users WHERE username = ? AND user_id != ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $new_username, $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            // Username already exists, show an error message
            $update_message = "Username already taken. Please choose another one.";
        } else {
            // Update the username in the database
            $sql_update = "UPDATE users SET username = ? WHERE user_id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("si", $new_username, $user_id);

            if ($stmt_update->execute()) {
                $update_message = "Username updated successfully!";
                $_SESSION['username'] = $new_username; // Update session username
            } else {
                $update_message = "Error updating username. Please try again.";
            }

            $stmt_update->close();
        }

        $stmt_check->close();
    }

    // Password update
    if (!empty($new_password)) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT); // Hash new password
        $sql_update_password = "UPDATE users SET password_hash = ? WHERE user_id = ?";
        $stmt_password = $conn->prepare($sql_update_password);
        $stmt_password->bind_param("si", $password_hash, $user_id);
        $stmt_password->execute();
        $stmt_password->close();
    }
}

// Close the connection
$conn->close();

// Redirect to the profile page after update
header('Location: profile.php'); // Redirect to the profile page after the update
exit;
?>
