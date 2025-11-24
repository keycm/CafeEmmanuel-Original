<?php
session_start();
require_once 'audit_log.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: admin_inquiries.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "addproduct");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$audit_conn = new mysqli("localhost", "root", "", "login_system");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inquiry_id = intval($_POST['inquiry_id']);
    $response_message = trim($_POST['response_message']);
    $internal_notes = trim($_POST['internal_notes'] ?? '');
    $new_status = $_POST['status'] ?? 'responded';
    
    if (!$inquiry_id || empty($response_message)) {
        header("Location: admin_inquiries.php?error=missing_data");
        exit();
    }
    
    // Get inquiry details
    $stmt = $conn->prepare("SELECT * FROM inquiries WHERE id = ?");
    $stmt->bind_param("i", $inquiry_id);
    $stmt->execute();
    $inquiry = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$inquiry) {
        header("Location: admin_inquiries.php?error=not_found");
        exit();
    }
    
    // Update inquiry with response
    $stmt = $conn->prepare("UPDATE inquiries SET status = ?, admin_response = ?, responded_by = ?, responded_at = NOW(), internal_notes = ? WHERE id = ?");
    $stmt->bind_param("ssisi", $new_status, $response_message, $_SESSION['user_id'], $internal_notes, $inquiry_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Send email to customer
        $customer_email = $inquiry['email'];
        $customer_name = $inquiry['first_name'] . ' ' . $inquiry['last_name'];
        $subject = "Response to Your Inquiry - Cafe Emmanuel";
        
        $email_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #556ee6; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f8fb; padding: 30px; border-radius: 0 0 8px 8px; }
                .original-message { background: white; padding: 15px; border-left: 4px solid #556ee6; margin: 20px 0; }
                .response { background: white; padding: 20px; border-radius: 6px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #74788d; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Cafe Emmanuel</h1>
                    <p>Response to Your Inquiry</p>
                </div>
                <div class='content'>
                    <p>Dear " . htmlspecialchars($customer_name) . ",</p>
                    <p>Thank you for contacting Cafe Emmanuel. We have reviewed your inquiry and here is our response:</p>
                    
                    <div class='response'>
                        <strong>Our Response:</strong><br><br>
                        " . nl2br(htmlspecialchars($response_message)) . "
                    </div>
                    
                    <div class='original-message'>
                        <strong>Your Original Message:</strong><br><br>
                        " . nl2br(htmlspecialchars($inquiry['message'])) . "
                    </div>
                    
                    <p>If you have any additional questions, please don't hesitate to contact us again.</p>
                    <p>Best regards,<br><strong>Cafe Emmanuel Team</strong></p>
                </div>
                <div class='footer'>
                    <p>San Antonio, Guagua, 2003 Pampanga<br>(555) 123-CAFE</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Cafe Emmanuel <noreply@cafeemmanuel.com>" . "\r\n";
        
        // Send email
        $email_sent = mail($customer_email, $subject, $email_body, $headers);
        
        // Log audit
        logAdminAction(
            $audit_conn,
            $_SESSION['user_id'],
            $_SESSION['fullname'],
            'inquiry_response',
            "Responded to inquiry #$inquiry_id from {$customer_name} - Status: {$new_status}",
            'inquiries',
            $inquiry_id
        );
        
        if ($email_sent) {
            header("Location: admin_inquiries.php?success=response_sent");
        } else {
            header("Location: admin_inquiries.php?success=response_saved&warning=email_failed");
        }
    } else {
        header("Location: admin_inquiries.php?error=update_failed");
    }
    
    $conn->close();
    $audit_conn->close();
    exit();
}

header("Location: admin_inquiries.php");
exit();
?>
