<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = $_POST['fname'] ?? '';
    $last_name = $_POST['lname'] ?? '';
    $email = $_POST['email'] ?? '';
    $message = $_POST['message'] ?? '';

    // Simple validation
    if (!empty($first_name) && !empty($last_name) && !empty($email) && !empty($message)) {
        $conn = new mysqli("localhost", "root", "", "addproduct");
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $stmt = $conn->prepare("INSERT INTO inquiries (first_name, last_name, email, message) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $first_name, $last_name, $email, $message);

        if ($stmt->execute()) {
            // Success: redirect to contact.php
            header("Location: contact.php?status=success");
            exit();
        } else {
            // Database error: redirect to contact.php
            header("Location: contact.php?status=error");
            exit();
        }
        $stmt->close();
        $conn->close();
    } else {
        // Validation error: redirect to contact.php
        header("Location: contact.php?status=error");
        exit();
    }
} else {
    // Not a POST request: redirect to contact.php
    header("Location: contact.php");
    exit();
}
?>