<?php
session_start();
require_once 'audit_log.php'; // Use standardized audit_log.php

// Ensure the current user is an admin before proceeding
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Access denied.");
}

$conn = new mysqli("localhost", "root", "", "login_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $user_id = intval($_GET['id']);
    $admin_id = $_SESSION['user_id'];
    $admin_name = $_SESSION['fullname'];

    // Security check: an admin cannot demote or delete their own account
    if ($user_id == $admin_id) {
        header("Location: user_accounts.php");
        exit();
    }

    $conn->begin_transaction();
    try {
        if ($action === 'promote') {
            // Only super_admin can promote
            if ($_SESSION['role'] === 'super_admin') {
                $stmt = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                logAdminAction($conn, $admin_id, $admin_name, 'user_role_change', "Promoted user ID #{$user_id} to admin", 'users', $user_id);
            }
        } elseif ($action === 'demote') {
            // Only super_admin can demote
            if ($_SESSION['role'] === 'super_admin') {
                $stmt = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                logAdminAction($conn, $admin_id, $admin_name, 'user_role_change', "Demoted user ID #{$user_id} to user", 'users', $user_id);
            }
        } elseif ($action === 'delete') {
            // 1. Get the user's data
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user) {
                // Security check: Admin cannot delete super_admin
                if ($user['role'] === 'super_admin' && $_SESSION['role'] !== 'super_admin') {
                     throw new Exception("Admin cannot delete Super Admin.");
                }

                // 2. Insert into the recently_deleted_users table
                $insert_stmt = $conn->prepare("INSERT INTO recently_deleted_users (original_id, fullname, username, email, role) VALUES (?, ?, ?, ?, ?)");
                $insert_stmt->bind_param("issss", $user['id'], $user['fullname'], $user['username'], $user['email'], $user['role']);
                $insert_stmt->execute();

                // 3. Delete from the main users table
                $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $delete_stmt->bind_param("i", $user_id);
                $delete_stmt->execute();

                // --- ADDED/STANDARDIZED AUDIT LOG ---
                logAdminAction(
                    $conn,
                    $admin_id,
                    $admin_name,
                    'user_delete',
                    "Moved user to recycle bin: {$user['username']} (ID: {$user_id})",
                    'users',
                    $user_id
                );
                // --- END OF LOG ---
            } else {
                throw new Exception("User not found.");
            }
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        // Log the error
        logAdminAction(
            $conn,
            $admin_id,
            $admin_name,
            'user_action_failed',
            "Failed action '{$action}' on user ID {$user_id}. Error: {$e->getMessage()}",
            'users',
            $user_id
        );
    }
}

// Redirect back to the user accounts page after the action
header("Location: user_accounts.php");
exit();
?>