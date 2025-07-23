<?php

$DATABASE_HOST = 'localhost';
$DATABASE_USER = 'root';
$DATABASE_PASS = '';
$DATABASE_NAME = 'tracker';

$con = mysqli_connect($DATABASE_HOST, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);

if (mysqli_connect_errno()) {
    exit('Failed to connect to MySQL: ' . mysqli_connect_error());
}

// Check if the form is submitted and required fields are set
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username'], $_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Debugging: Display the submitted form values
    //echo "Username: $username<br>";
    //echo "Password: $password<br>";

    // Check if the username already exists in the database
    if ($stmt = $con->prepare('SELECT user_id FROM users WHERE username = ?')) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo "Username is already taken!";
        } else {
            // Hash the password before storing it
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert the new user into the database
            if ($stmt = $con->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')) {
                $stmt->bind_param('ss', $username, $password_hash);
                $stmt->execute();
                
                // Check if insert was successful
                if ($stmt->affected_rows > 0) {
                    // Redirect to login page after successful registration
                    header("Location: login.html");
                    exit(); // Ensure no further code is executed after the redirect
                } else {
                    echo "Error registering user: " . $stmt->error;
                }
            } else {
                echo "Error preparing the query: " . $con->error;
            }
        }
        $stmt->close();
    } else {
        echo "Error preparing the SELECT query: " . $con->error;
    }
} else {
    echo "Form was not submitted correctly.";
}
?>
