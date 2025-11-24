<?php
session_start();
require_once 'config.php';
require_once 'audit_log.php'; // Use standardized audit_log.php

// Only super_admin can create admin accounts
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: user_accounts.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'admin';
    
    // Validation
    $errors = [];
    
    if (empty($fullname) || !preg_match("/^[a-zA-Z\s]+$/", $fullname)) {
        $errors[] = "Full name must contain only letters and spaces.";
    }
    
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    
    if (!empty($contact) && !preg_match("/^[0-9]{10,15}$/", $contact)) {
        $errors[] = "Contact number must be 10-15 digits.";
    }
    
    if (empty($password) || !preg_match("/^(?=.*[A-Z]).{8,}$/", $password)) {
        $errors[] = "Password must be at least 8 characters with 1 capital letter.";
    }
    
    if (!in_array($role, ['admin', 'super_admin'])) {
        $errors[] = "Invalid role selected.";
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors[] = "Email or Username already exists.";
        }
        $stmt->close();
    }
    
    // Create admin account
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $insert_stmt = $conn->prepare("INSERT INTO users (fullname, username, email, contact, password, role) VALUES (?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("ssssss", $fullname, $username, $email, $contact, $hashed_password, $role);
        
        if ($insert_stmt->execute()) {
            $new_user_id = $insert_stmt->insert_id;
            
            // --- ADDED/STANDARDIZED AUDIT LOG ---
            // $conn is already the connection to login_system (from config.php)
            logAdminAction(
                $conn,
                $_SESSION['user_id'],
                $_SESSION['fullname'],
                'admin_created',
                "Created new admin account: {$username} (Role: {$role})",
                'users',
                $new_user_id
            );
            // --- END OF LOG ---
            
            header("Location: user_accounts.php?success=admin_created");
            exit;
        } else {
            $errors[] = "Error creating admin account: " . $insert_stmt->error;
        }
        
        $insert_stmt->close();
    }
    
    // If there are errors, redirect back with error message
    if (!empty($errors)) {
        $_SESSION['admin_create_error'] = implode(" ", $errors);
        header("Location: user_accounts.php");
        exit;
    }
}

$conn->close();
header("Location: user_accounts.php");
exit;
?>