<?php
session_start();
require_once 'audit_log.php'; // Use standardized audit_log.php

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Access denied.");
}

include 'db_connect.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']); // This is the ID from recently_deleted_users table
    $action = $_POST['action'];
    $admin_id = $_SESSION['user_id'];
    $admin_name = $_SESSION['fullname'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM recently_deleted_users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user) {
            throw new Exception("Deleted user not found.");
        }
        
        $original_user_id = $user['original_id'];
        $username = $user['username'];

        if ($action === 'restore') {
            // Check if another user took the username or email in the meantime
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check_stmt->bind_param("ss", $user['username'], $user['email']);
            $check_stmt->execute();
            $check_stmt->store_result();
            if ($check_stmt->num_rows > 0) {
                throw new Exception("Cannot restore user '{$username}'. Their username or email is already in use by an active account.");
            }
            $check_stmt->close();

            // Insert back into the main users table
            // Note: We are restoring with the original ID. This assumes password is lost or needs reset.
            // For this system, we are NOT restoring the password.
            $insert_stmt = $conn->prepare("INSERT INTO users (id, fullname, username, email, role, password) VALUES (?, ?, ?, ?, ?, ?)");
            $placeholder_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT); // Set a random, unusable password
            $insert_stmt->bind_param("isssss", $original_user_id, $user['fullname'], $user['username'], $user['email'], $user['role'], $placeholder_password);
            $insert_stmt->execute();
            
            // --- REFACTORED AUDIT LOG ---
            logAdminAction(
                $conn,
                $admin_id,
                $admin_name,
                'user_restore',
                "Restored user: {$username} (ID: {$original_user_id}). User must reset password.",
                'users',
                $original_user_id
            );
            // --- END OF LOG ---

        } else if ($action === 'permanent_delete') {
            
            // --- REFACTORED AUDIT LOG ---
            logAdminAction(
                $conn,
                $admin_id,
                $admin_name,
                'user_delete_permanent',
                "Permanently deleted user: {$username} (Original ID: {$original_user_id})",
                'recently_deleted_users',
                $id // Log against the recycle bin ID
            );
            // --- END OF LOG ---
        }

        // For both 'restore' and 'permanent_delete', we remove the record from the temp table
        $delete_stmt = $conn->prepare("DELETE FROM recently_deleted_users WHERE id = ?");
        $delete_stmt->bind_param("i", $id);
        $delete_stmt->execute();
        
        $conn->commit();
        $_SESSION['success_message'] = "User action '{$action}' completed successfully.";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
        
        // --- REFACTORED AUDIT LOG ---
        logAdminAction(
            $conn,
            $admin_id,
            $admin_name,
            'user_restore_failed',
            "Failed action '{$action}' for deleted user ID {$id}. Error: {$e->getMessage()}",
            'recently_deleted_users',
            $id
        );
        // --- END OF LOG ---
    }
}

header("Location: recently_deleted.php");
exit();
?>