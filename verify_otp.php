<?php
session_start();
require_once 'config.php';
require_once 'audit.php';

// Check if user is in the OTP flow
if (!isset($_SESSION['otp_user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code_input = trim($_POST['otp_code'] ?? ''); // Match the input name from index.php modal
    $userId = (int)$_SESSION['otp_user_id'];

    if (empty($code_input)) {
        $error_message = 'Please enter the verification code.';
    } else {
        // 1. Get the latest active OTP for this user
        $stmt = $conn->prepare("SELECT id, code, expires_at, attempts FROM otp_codes WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $otpRecord = $result->fetch_assoc();
        $stmt->close();

        if (!$otpRecord) {
            $error_message = 'No valid code found. Please resend.';
            audit($userId, 'otp_failed_not_found', 'otp_codes', null, ['input' => $code_input]);
        } elseif (strtotime($otpRecord['expires_at']) < time()) {
            $error_message = 'Code has expired. Please resend.';
            audit($userId, 'otp_failed_expired', 'otp_codes', $otpRecord['id'], []);
        } elseif ($otpRecord['attempts'] >= 5) {
            $error_message = 'Too many attempts. Please request a new code.';
            audit($userId, 'otp_failed_max_attempts', 'otp_codes', $otpRecord['id'], []);
        } elseif ($otpRecord['code'] !== $code_input) {
            // Increment attempts
            $updateStmt = $conn->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?");
            $updateStmt->bind_param('i', $otpRecord['id']);
            $updateStmt->execute();
            $error_message = 'Invalid code. Please try again.';
            audit($userId, 'otp_failed_mismatch', 'otp_codes', $otpRecord['id'], ['input' => $code_input]);
        } else {
            // 2. Success: Mark OTP as used
            $markStmt = $conn->prepare("UPDATE otp_codes SET used_at = NOW() WHERE id = ?");
            $markStmt->bind_param('i', $otpRecord['id']);
            $markStmt->execute();

            // 3. Log the user in fully
            $_SESSION['user_id'] = $userId;
            $_SESSION['email'] = $_SESSION['otp_email'];
            $_SESSION['fullname'] = $_SESSION['otp_fullname'];
            $_SESSION['role'] = $_SESSION['otp_role'];
            
            // Clear OTP temp session
            unset($_SESSION['otp_user_id']);
            unset($_SESSION['otp_email']);
            unset($_SESSION['otp_fullname']);
            unset($_SESSION['otp_role']);
            unset($_SESSION['show_otp_modal']);

            audit($userId, 'login_success_otp', 'users', $userId, []);

            // 4. Redirect based on role
            if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin') {
                header("Location: Dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit;
        }
    }
}

// If we are here, there was an error, redirect back to index with error
// You should handle this error display in your index.php modal
if ($error_message) {
    // Re-trigger the modal and show error
    $_SESSION['show_otp_modal'] = true;
    $_SESSION['otp_error'] = $error_message; // You need to display this in index.php
    header("Location: index.php");
    exit;
}
?>