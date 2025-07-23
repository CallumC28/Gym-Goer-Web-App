<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tracker";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check which files were uploaded (checking for no error code)
$uploadedBefore = isset($_FILES['beforePhoto']) && $_FILES['beforePhoto']['error'] === UPLOAD_ERR_OK;
$uploadedAfter  = isset($_FILES['afterPhoto']) && $_FILES['afterPhoto']['error'] === UPLOAD_ERR_OK;

//If neither file is uploaded, you might just redirect or show a message
if (!$uploadedBefore && !$uploadedAfter) {
    //No file chosen
    header('Location: profile.php?no_file=1');
    exit();
}

// Ensure uploads directory exists
$uploadDir = "uploads/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

//Check if there's already a row for this user in progress_photos
$sql_check = "SELECT before_photo, after_photo FROM progress_photos WHERE user_id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$row = $result_check->fetch_assoc();
$stmt_check->close();

$beforePhotoName = null;
$afterPhotoName  = null;

// If the user uploaded a 'before' photo
if ($uploadedBefore) {
    $tmp = $_FILES['beforePhoto']['tmp_name'];
    $originalName = basename($_FILES['beforePhoto']['name']);

    //Unique filename
    $beforePhotoName = uniqid("before_") . "_" . $originalName;
    $destination = $uploadDir . $beforePhotoName;
    
    //Move the file
    move_uploaded_file($tmp, $destination);

    //Optionally delete old before photo file if it exists
    if ($row && $row['before_photo']) {
        $oldBeforePath = $uploadDir . $row['before_photo'];
        if (file_exists($oldBeforePath)) {
            unlink($oldBeforePath);
        }
    }
}

// If the user uploaded an 'after' photo
if ($uploadedAfter) {
    $tmp = $_FILES['afterPhoto']['tmp_name'];
    $originalName = basename($_FILES['afterPhoto']['name']);
    
    $afterPhotoName = uniqid("after_") . "_" . $originalName;
    $destination = $uploadDir . $afterPhotoName;

    move_uploaded_file($tmp, $destination);

    //Optionally delete old after photo file if it exists
    if ($row && $row['after_photo']) {
        $oldAfterPath = $uploadDir . $row['after_photo'];
        if (file_exists($oldAfterPath)) {
            unlink($oldAfterPath);
        }
    }
}

//If row exists, update only the columns for which we have a new file
if ($row) {
    $sets = [];
    $params = [];
    $types  = '';

    // If we got a new before photo, add that to the update
    if ($uploadedBefore) {
        $sets[] = "before_photo = ?";
        $params[] = $beforePhotoName;
        $types .= 's';
    }

    //If we got a new after photo, add that to the update
    if ($uploadedAfter) {
        $sets[] = "after_photo = ?";
        $params[] = $afterPhotoName;
        $types .= 's';
    }

    //Also update the upload_date
    $sets[] = "upload_date = NOW()";

    $sql_update = "UPDATE progress_photos SET " . implode(", ", $sets) . " WHERE user_id = ?";
    $stmt_update = $conn->prepare($sql_update);

    $types .= 'i'; //user_id is an integer
    $params[] = $user_id;

    $stmt_update->bind_param($types, ...$params);

    if ($stmt_update->execute()) {
        header('Location: profile.php?upload_success=1');
        exit();
    } else {
        echo "Error updating database: " . $stmt_update->error;
    }
    $stmt_update->close();

} else {
    //No existing row then insert a new row with whichever photos were provided
    $sql_insert = "INSERT INTO progress_photos (user_id, before_photo, after_photo, upload_date)
                   VALUES (?, ?, ?, NOW())";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("iss", $user_id, $beforePhotoName, $afterPhotoName);

    if ($stmt_insert->execute()) {
        header('Location: profile.php?upload_success=1');
        exit();
    } else {
        echo "Error inserting into database: " . $stmt_insert->error;
    }
    $stmt_insert->close();
}

$conn->close();
