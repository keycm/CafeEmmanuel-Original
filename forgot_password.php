<?php
session_start();
require_once 'config.php';
require_once 'audit.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    // Send reset code directly via email or phone
    if (isset($_POST['send_reset_code'])) {
        $identifier = trim($_POST['identifier'] ?? '');
        $reset_method = $_POST['reset_method'] ?? '';
        
        if (empty($identifier)) {
            echo json_encode(['success' => false, 'message' => 'Please provide your email or phone number.']);
            exit;
        }
        
        // Find user based on method
        if ($reset_method === 'email') {
            $stmt = $conn->prepare("SELECT id, fullname, email, contact FROM users WHERE email = ?");
            $stmt->bind_param("s", $identifier);
        } else {
            $stmt = $conn->prepare("SELECT id, fullname, email, contact FROM users WHERE contact = ?");
            $stmt->bind_param("s", $identifier);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if (!$user) {
            if ($reset_method === 'email') {
                echo json_encode(['success' => false, 'message' => 'No account found with that email address.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No account found with that phone number.']);
            }
            exit;
        }
        
        // Generate 6-digit reset code
        $reset_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes
        
        // Store reset code in database
        $insertStmt = $conn->prepare("INSERT INTO password_resets (user_id, reset_code, reset_method, expires_at) VALUES (?, ?, ?, ?)");
        $insertStmt->bind_param("isss", $user['id'], $reset_code, $reset_method, $expires);
        $insertStmt->execute();
        $insertStmt->close();
        
        // Send code based on method
        if ($reset_method === 'email') {
            require_once __DIR__ . '/mailer.php';
            $subject = 'Password Reset Code - Cafe Emmanuel';
            $body = "Hello " . htmlspecialchars($user['fullname']) . ",\n\n";
            $body .= "Your password reset code is: $reset_code\n\n";
            $body .= "This code will expire in 10 minutes.\n\n";
            $body .= "If you didn't request this, please ignore this email.\n\n";
            $body .= "- Cafe Emmanuel Team";
            
            send_email($user['email'], $subject, $body);
            $message = 'Reset code sent to ' . maskEmail($user['email']);
        } else {
            // TODO: Send SMS with code
            // send_sms($user['contact'], "Your Cafe Emmanuel password reset code is: $reset_code");
            $message = 'Reset code sent to ' . maskPhone($user['contact']);
        }
        
        // Store user ID in session for reset page
        $_SESSION['reset_user_id'] = $user['id'];
        $_SESSION['reset_method'] = $reset_method;
        
        audit($user['id'], 'password_reset_requested', 'users', $user['id'], ['method' => $reset_method]);
        
        echo json_encode(['success' => true, 'message' => $message]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Helper functions to mask sensitive data
function maskEmail($email) {
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1];
    $masked_name = substr($name, 0, 2) . str_repeat('*', strlen($name) - 2);
    return $masked_name . '@' . $domain;
}

function maskPhone($phone) {
    $visible = substr($phone, -4);
    return str_repeat('*', strlen($phone) - 4) . $visible;
}
?>
