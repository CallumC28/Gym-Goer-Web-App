<?php

session_start();

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
    // echo "Username: $username<br>";
    // echo "Password: $password<br>";

    // Check if the username exists in the database
    if ($stmt = $con->prepare('SELECT user_id, password_hash FROM users WHERE username = ?')) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($user_id, $password_hash);
            $stmt->fetch();

            // Debugging: Display the fetched password hash
            // echo "Fetched password_hash: $password_hash<br>";

            // Verify the entered password against the hashed password from the database
            if (password_verify($password, $password_hash)) {
                // Password verification successful
                session_regenerate_id();  // Protect against session fixation attacks
                $_SESSION['loggedin'] = TRUE;
                $_SESSION['username'] = $username;
                $_SESSION['user_id'] = $user_id;

                // Redirect to homepage or another page
                header("Location: Homepage.php");
                exit(); // Ensure no further code is executed after the redirect
            } else {
                echo "Incorrect username and/or password!";
            }
        } else {
            // No username found in the database
            echo "Incorrect username and/or password!";
        }
        $stmt->close();
    } else {
        // Error preparing the query
        echo "Error preparing the query: " . $con->error;
    }
}
?>
