<?php
session_start();
include 'config.php'; 
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/mailer.php'; 

$login_error = '';
$register_error = '';
$register_success = '';
$otp_error = '';
$otp_success = '';

// ---------------------------------------------------------
// 1. OTP VERIFICATION LOGIC
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $code_input = trim($_POST['otp_code'] ?? '');
    $userId = (int)($_SESSION['otp_user_id'] ?? 0);
    
    if (empty($code_input)) {
        $otp_error = 'Please enter the code.';
    } elseif (!$userId) {
        $otp_error = 'Session expired. Please login again.';
        unset($_SESSION['otp_user_id'], $_SESSION['otp_email'], $_SESSION['show_otp_modal']);
    } else {
        $stmt = $conn->prepare("SELECT id, code, expires_at, attempts FROM otp_codes WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $otpRecord = $result->fetch_assoc();
        $stmt->close();
        
        if (!$otpRecord) {
            $otp_error = 'Code expired or not found. Please resend.';
        } elseif (time() > strtotime($otpRecord['expires_at'])) {
            $otp_error = 'Code expired. Please resend.';
        } elseif ((int)$otpRecord['attempts'] >= 5) {
            $otp_error = 'Too many attempts. Please request a new code.';
        } elseif ($code_input !== $otpRecord['code']) {
            $updateStmt = $conn->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?");
            $updateStmt->bind_param('i', $otpRecord['id']);
            $updateStmt->execute();
            $updateStmt->close();
            $otp_error = 'Invalid code. Try again.';
        } else {
            // Success: Mark OTP used
            $markStmt = $conn->prepare("UPDATE otp_codes SET used_at = NOW() WHERE id = ?");
            $markStmt->bind_param('i', $otpRecord['id']);
            $markStmt->execute();
            $markStmt->close();
            
            // --- MARK USER AS VERIFIED (Safety Check) ---
            $verifyStmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
            if ($verifyStmt) {
                $verifyStmt->bind_param('i', $userId);
                $verifyStmt->execute();
                $verifyStmt->close();
            }
            
            // Log User In
            $_SESSION['user_id'] = $userId;
            $_SESSION['email'] = $_SESSION['otp_email'];
            $_SESSION['fullname'] = $_SESSION['otp_fullname'] ?? '';
            $_SESSION['role'] = $_SESSION['otp_role'] ?? 'user';
            
            // Cleanup
            unset($_SESSION['otp_user_id'], $_SESSION['otp_email'], $_SESSION['otp_fullname'], $_SESSION['otp_role'], $_SESSION['show_otp_modal']);
            
            audit($userId, 'login_success_otp_verified', 'users', $userId, []);
            
            if (in_array($_SESSION['role'], ['admin', 'super_admin'])) {
                header("Location: Dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit;
        }
    }
}

// ---------------------------------------------------------
// 2. LOGIN LOGIC (Updated: Email OR Username)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Accept 'identifier' (new input name) or fallback to 'email' (old)
    $identifier = trim($_POST['identifier'] ?? $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $login_error = "Please fill in both fields.";
    } else {
        // Check against EMAIL OR USERNAME
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                
                // CHECK IF VERIFIED
                // If column doesn't exist yet (during dev), assume verified to avoid lockout
                $isVerified = !isset($user['is_verified']) || $user['is_verified'] == 1;

                if ($isVerified) {
                    // Verified: Direct Login
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email']; // Use actual email from DB
                    $_SESSION['fullname'] = $user['fullname'];
                    $_SESSION['role'] = $user['role'];
                    
                    audit($user['id'], 'login_success', 'users', $user['id'], []);
                    
                    if (in_array($user['role'], ['admin', 'super_admin'])) {
                        header("Location: Dashboard.php");
                    } else {
                        header("Location: index.php");
                    }
                    exit;
                } else {
                    // Not Verified: Send OTP
                    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', time() + 600);
                    
                    $otpStmt = $conn->prepare("INSERT INTO otp_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
                    $otpStmt->bind_param("iss", $user['id'], $code, $expires);
                    $otpStmt->execute();
                    
                    $subject = 'Verify Your Account - Cafe Emmanuel';
                    $body = "<h3>Account Verification Needed</h3><p>Your code is: <b style='font-size:20px;'>$code</b></p>";
                    send_email($user['email'], $subject, $body);
                    
                    $_SESSION['otp_user_id'] = $user['id'];
                    $_SESSION['otp_email'] = $user['email'];
                    $_SESSION['otp_fullname'] = $user['fullname'];
                    $_SESSION['otp_role'] = $user['role'];
                    $_SESSION['show_otp_modal'] = true;
                    
                    header("Location: index.php?verify=pending");
                    exit;
                }
            } else {
                $login_error = "Wrong password!";
            }
        } else {
            $login_error = "Username or Email not found!";
        }
        $stmt->close();
    }
}

// ---------------------------------------------------------
// 3. REGISTRATION LOGIC
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $contact  = trim($_POST['contact'] ?? '');
    $gender   = trim($_POST['gender'] ?? '');
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $register_error = "Passwords do not match!";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $register_error = "Email or Username already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Try to insert with is_verified column
            $insert_stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, contact, gender, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
            
            // Fallback if column missing (prevents fatal error)
            if (!$insert_stmt) {
                 $insert_stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, contact, gender) VALUES (?, ?, ?, ?, ?, ?)");
                 $insert_stmt->bind_param("ssssss", $username, $hashed_password, $fullname, $email, $contact, $gender);
            } else {
                 $insert_stmt->bind_param("ssssss", $username, $hashed_password, $fullname, $email, $contact, $gender);
            }

            if ($insert_stmt->execute()) {
                $new_user_id = $insert_stmt->insert_id;
                
                // Generate OTP
                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', time() + 600);
                
                $otpStmt = $conn->prepare("INSERT INTO otp_codes (user_id, code, expires_at) VALUES (?, ?, ?)");
                $otpStmt->bind_param("iss", $new_user_id, $code, $expires);
                $otpStmt->execute();
                $otpStmt->close();
                
                // Send OTP Email
                $subject = "Welcome! Verify your Cafe Emmanuel Account";
                $body = "<div style='color:#333;'><h1>Welcome, $fullname!</h1><p>Verify your account using code: <b style='font-size:24px; color:#B95A4B;'>$code</b></p></div>";
                send_email($email, $subject, $body);

                $_SESSION['otp_user_id'] = $new_user_id;
                $_SESSION['otp_email'] = $email;
                $_SESSION['otp_fullname'] = $fullname;
                $_SESSION['otp_role'] = 'user';
                $_SESSION['show_otp_modal'] = true;

                header("Location: index.php?verify=new_account");
                exit;
                
            } else {
                $register_error = "Error: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
}

// --- Fetch Menu Items from Database (FIXED: Uses main $conn) ---
// Removed "root" connection that causes crashes on live server
$coffee_items = $food_items = $dessert_items = [];

if (isset($conn) && !$conn->connect_error) {
    $coffee_result = $conn->query("SELECT * FROM products WHERE category = 'coffee' AND stock > 0 ORDER BY id DESC LIMIT 4");
    $coffee_items = $coffee_result ? $coffee_result->fetch_all(MYSQLI_ASSOC) : [];

    $food_result = $conn->query("SELECT * FROM products WHERE category IN ('pizza', 'burger', 'pasta') AND stock > 0 ORDER BY id DESC LIMIT 4");
    $food_items = $food_result ? $food_result->fetch_all(MYSQLI_ASSOC) : [];

    $dessert_result = $conn->query("SELECT * FROM products WHERE category = 'dessert' AND stock > 0 ORDER BY id DESC LIMIT 4");
    $dessert_items = $dessert_result ? $dessert_result->fetch_all(MYSQLI_ASSOC) : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cafe Emmanuel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Akaya+Telivigala&family=Archivo+Black&family=Archivo+Narrow:wght@400;700&family=Birthstone+Bounce:wght@500&family=Inknut+Antiqua:wght@600&family=Playfair+Display:wght@700&family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* --- Global Styles & Variables --- */
        :root {
            /* Colors */
            --primary-color: #B95A4B;
            --secondary-color: #3C2A21;
            --text-color: #333;
            --heading-color: #1F1F1F;
            --light-gray: #F6F6F6;
            --white: #FFFFFF;
            --border-color: #EAEAEA;
            --footer-bg-color: #1a120b;
            --footer-text-color: #ccc;
            --footer-link-hover: #FFC94A;

            /* Custom Fonts */
            --font-logo-cafe: 'Archivo Black', sans-serif;
            --font-logo-emmanuel: 'Birthstone Bounce', cursive;
            --font-nav: 'Inknut Antiqua', serif;
            --font-hero-heading: 'Akaya Telivigala', cursive;
            --font-hero-body: 'Archivo Narrow', sans-serif;

            /* Fallback Fonts */
            --font-section-heading: 'Playfair Display', serif;
            --font-body-default: 'Lato', sans-serif;
            
            --nav-height: 90px;
        }

        /* --- Base & Reset --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html {
            scroll-behavior: smooth;
            scroll-padding-top: var(--nav-height);
        }
        body {
            font-family: var(--font-body-default);
            color: var(--text-color);
            background-color: var(--white);
            line-height: 1.7;
        }
        .container {
            max-width: 1140px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .section-title {
            font-family: var(--font-section-heading);
            text-align: center;
            font-size: 2.8rem;
            color: var(--heading-color);
            margin-bottom: 1rem;
        }
        .section-subtitle {
            text-align: center;
            max-width: 650px;
            margin: 0 auto 3.5rem auto;
            color: #666;
            font-size: 1.1rem;
        }
        section {
            padding: 6rem 0;
        }

        /* --- Header & Navigation --- */
        .header {
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            height: var(--nav-height);
            background: transparent;
            transition: background-color 0.4s ease, backdrop-filter 0.4s ease;
        }
        .header.scrolled {
            background: rgba(26, 18, 11, 0.85);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
        }
        .nav-logo {
            text-decoration: none;
            color: var(--white);
            display: flex;
            align-items: center;
        }
        .logo-cafe {
            font-family: var(--font-logo-cafe);
            font-size: 40px;
        }
        .logo-emmanuel {
            font-family: var(--font-logo-emmanuel);
            font-size: 40px;
            font-weight: 500;
            margin-left: 10px;
        }
        .first-letter {
            color: #b41329ff; /* Maroon Red */
        }
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 3rem;
        }
        .nav-link {
            font-family: var(--font-nav);
            font-size: 16px;
            font-weight: 600;
            color: #E0E0E0;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            color: var(--footer-link-hover);
        }
        .nav-button {
            background-color: var(--footer-link-hover);
            color: var(--secondary-color);
            padding: 10px 22px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .nav-button:hover {
            background-color: #e6b33a;
        }
        .hamburger { display: none; }
        .bar { display: block; width: 25px; height: 3px; margin: 5px auto; transition: all 0.3s ease-in-out; background-color: var(--white); }
        .nav-right-cluster {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .nav-cart-link {
            color: var(--white);
            font-size: 1.2rem;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .nav-cart-link:hover {
            color: var(--footer-link-hover);
        }

        /* --- Profile Dropdown --- */
        .profile-dropdown {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        .profile-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--white);
        }
        .profile-info span {
            font-weight: 500;
            font-size: 16px;
            font-family: var(--font-nav);
            color: #E0E0E0;
            transition: color 0.3s ease;
        }
        .profile-info i {
            font-size: 1.1rem;
            color: #E0E0E0;
            transition: color 0.3s ease;
        }
        .profile-dropdown:hover .profile-info span,
        .profile-dropdown:hover .profile-info i {
            color: var(--footer-link-hover);
        }
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 150%;
            background-color: var(--white);
            min-width: 180px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1001;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        .dropdown-content a {
            color: var(--text-color);
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 500;
            text-align: left;
            transition: background-color 0.2s, color 0.2s;
        }
        .dropdown-content a:hover {
            background-color: #f1f1f1;
            color: var(--primary-color);
        }
        .profile-dropdown.active .dropdown-content {
            display: block;
        }

        /* --- Hero Section --- */
        .hero-section {
            height: 100vh;
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('Cover-Photo.jpg') no-repeat center center/cover;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            color: var(--white);
            padding: 0 5%;
        }
        .hero-content {
            max-width: 800px;
            text-align: left;
        }
        .hero-content h1 {
            font-family: var(--font-hero-heading);
            font-size: 60px;
            font-weight: 400;
            color: var(--white);
            line-height: 1.4;
            margin-bottom: 1.5rem;
            letter-spacing: 1px;
        }
        .hero-content .highlight-text {
            color: #E84545;
        }
        .hero-content p {
            font-family: var(--font-hero-body);
            font-size: 20px;
            line-height: 1.7;
            max-width: 650px;
            margin: 0 0 2.5rem 0;
        }
        .hero-buttons .btn {
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-family: var(--font-hero-body);
            font-weight: 700;
            font-size: 16px;
            margin-right: 10px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .btn.btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }
        .btn.btn-primary:hover { background-color: #a14436; }
        .btn.btn-secondary {
            background-color: transparent;
            color: var(--white);
            border-color: var(--white);
        }
        .btn.btn-secondary:hover {
            background-color: var(--white);
            color: var(--secondary-color);
        }

        /* --- Menu Section (Homepage) --- */
        #menu {
            background-color: var(--white);
        }
        .menu-tabs {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 3.5rem;
        }
        .tab-link {
            font-family: var(--font-body-default);
            background-color: #f0f0f0;
            color: #555;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .tab-link:hover {
            background-color: #ddd;
        }
        .tab-link.active {
            background-color: var(--primary-color);
            color: var(--white);
            box-shadow: 0 4px 10px rgba(185, 90, 75, 0.3);
        }
        .menu-content {
            display: none; 
            grid-template-columns: repeat(2, 1fr);
            gap: 2.5rem;
            animation: fadeIn 0.5s ease-in-out;
        }
        .menu-content.active {
            display: grid;
        }
        .menu-item {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .menu-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 12px;
        }
        .item-details {
            flex-grow: 1;
        }
        .item-details h3 {
            font-family: var(--font-section-heading);
            font-size: 1.2rem;
            color: var(--heading-color);
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
        }
        .item-details p {
            color: #666;
            font-size: 0.9rem;
        }
        .item-price {
            font-family: var(--font-section-heading);
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--secondary-color);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* --- About, Contact, Footer, Modals, etc. --- */
        #about { background-color: #FCFBF8; }
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-bottom: 5rem; }
        .feature-card { background: var(--white); padding: 2.5rem; text-align: center; border-radius: 12px; border: 1px solid var(--border-color); }
        .feature-card i { font-size: 2.8rem; color: var(--primary-color); margin-bottom: 1.5rem; }
        .feature-card h3 { font-family: var(--font-section-heading); margin-bottom: 0.75rem; font-size: 1.4rem; }
        .story-section { display: grid; grid-template-columns: 1.2fr 1fr; gap: 4rem; align-items: center; }
        .story-content h3 { font-family: var(--font-section-heading); font-size: 2.2rem; margin-bottom: 1.5rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
        .stat-box { background: var(--light-gray); padding: 2rem; text-align: center; border-radius: 12px; border: 1px solid var(--border-color); }
        .stat-box strong { display: block; font-size: 2.5rem; color: var(--primary-color); font-family: var(--font-section-heading); }
        #contact { padding-bottom: 0; }
        .contact-wrapper { display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; }
        .info-item { display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 2rem; }
        .info-item i { font-size: 1.2rem; color: var(--primary-color); margin-top: 8px; width: 25px; text-align: center; }
        .info-item strong { display: block; font-size: 0.9rem; color: #999; letter-spacing: 1px; margin-bottom: 0.25rem; }
        .hours-box { background: var(--white); padding: 2rem; border-radius: 12px; margin-top: 2rem; border: 1px solid var(--border-color); }
        .hours-box h4 { font-family: var(--font-section-heading); margin-bottom: 1rem; font-size: 1.3rem; }
        .hours-box h4 i { margin-right: 0.75rem; }
        .hours-box ul { list-style: none; }
        .hours-box li { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color); }
        .hours-box li:last-child { border-bottom: none; }
        .contact-buttons { margin-top: 2rem; display: flex; gap: 1rem; }
        .contact-buttons .btn { text-decoration: none; padding: 14px 22px; border-radius: 8px; text-align: center; flex: 1; font-weight: bold; transition: all 0.3s ease; border: 2px solid; }
        .btn.btn-dark { background-color: var(--heading-color); color: var(--white); border-color: var(--heading-color); }
        .btn.btn-dark:hover { background-color: #000; }
        .btn.btn-outline { background-color: transparent; color: var(--heading-color); border-color: var(--heading-color); }
        .btn.btn-outline:hover { background-color: var(--heading-color); color: var(--white); }
        .contact-map { background: var(--light-gray); border-radius: 12px; overflow: hidden; }
        .contact-map iframe { width: 100%; height: 100%; min-height: 500px; border: 0; }
        .newsletter-section { background-color: var(--white); padding: 4rem 0; }
        .newsletter-content { background: #fff; border: 1px solid var(--border-color); border-radius: 12px; padding: 3rem; text-align: center; max-width: 700px; margin: 0 auto; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .newsletter-content h3 { font-family: var(--font-section-heading); font-size: 2rem; color: var(--heading-color); margin-bottom: 0.75rem; }
        .newsletter-content p { font-size: 1rem; color: #666; margin-bottom: 2rem; }
        .newsletter-form { display: flex; justify-content: center; gap: 1rem; }
        .newsletter-form input[type="email"] { flex-grow: 1; max-width: 400px; padding: 14px 20px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; color: var(--text-color); }
        .newsletter-form input[type="email"]::placeholder { color: #aaa; }
        .newsletter-form button { background-color: var(--primary-color); color: var(--white); padding: 14px 25px; border: none; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: background-color 0.3s ease; }
        .newsletter-form button:hover { background-color: #a14436; }
        .new-footer { background-color: var(--footer-bg-color); color: var(--footer-text-color); padding: 4rem 0 1rem; font-size: 0.95rem; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 2rem; margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .footer-about .footer-logo { font-family: var(--font-section-heading); font-size: 1.8rem; color: var(--white); margin-bottom: 1rem; }
        .footer-about p { color: #a0a0a0; line-height: 1.6; margin-bottom: 1.5rem; }
        .social-links { display: flex; gap: 15px; margin-top: 1rem; }
        .social-links a { color: var(--footer-text-color); font-size: 1.3rem; transition: color 0.3s ease; }
        .social-links a:hover { color: var(--footer-link-hover); }
        .footer-links h4, .footer-contact h4 { font-family: var(--font-body-default); font-size: 1.1rem; font-weight: bold; color: var(--white); margin-bottom: 1.2rem; }
        .footer-links ul { list-style: none; }
        .footer-links ul li { margin-bottom: 0.8rem; }
        .footer-links a { color: var(--footer-text-color); text-decoration: none; transition: color 0.3s ease; }
        .footer-links a:hover { color: var(--footer-link-hover); }
        .footer-contact p { margin-bottom: 0.8rem; color: var(--footer-text-color); }
        .footer-bottom { text-align: center; padding-top: 1rem; font-size: 0.85rem; color: #888; }
        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); padding-top: 60px; }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 30px; border: 1px solid #888; width: 90%; max-width: 380px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); text-align: center; position: relative; animation: fadeIn 0.5s; }
        .close-btn { color: #aaa; position: absolute; top: 10px; right: 20px; font-size: 28px; font-weight: bold; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; cursor: pointer; }
        
        /* --- FIX FOR INPUT FIELDS --- */
        /* This specifically targets all text-based inputs to ensure consistent full width and padding */
        .modal-content input[type="email"], 
        .modal-content input[type="password"], 
        .modal-content input[type="text"],
        .modal-content input[type="tel"] { 
            width: 100%; 
            padding: 10px; 
            margin: 8px 0; 
            display: inline-block; 
            border: 1px solid #ccc; 
            border-radius: 6px; 
            box-sizing: border-box; 
        }

        .modal-content button { background-color: #111; color: white; padding: 12px 20px; margin: 15px 0; border: none; border-radius: 6px; cursor: pointer; width: 100%; font-size: 16px; transition: background-color 0.3s; }
        .modal-content .options { display: flex; justify-content: space-between; align-items: center; font-size: 14px; margin-top: 10px; margin-bottom: 15px; }
        .modal-content .register a { color: #E03A3E; font-weight: bold; }
        @media (max-width: 992px) {
            .features-grid, .story-section, .contact-wrapper, .footer-grid { grid-template-columns: 1fr; }
            .story-section, .footer-grid { text-align: center; }
            .story-section { gap: 2rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); margin-top: 2rem; }
            .hero-content { text-align: center; } 
            .hero-content p { margin: 0 auto 2.5rem auto; }
            .hero-buttons .btn { margin: 5px; }
            .footer-about .social-links { justify-content: center; }
        }
        @media (max-width: 768px) {
            .nav-menu { position: fixed; left: -100%; top: var(--nav-height); flex-direction: column; background-color: var(--secondary-color); width: 100%; text-align: center; transition: 0.3s; }
            .nav-menu.active { left: 0; }
            .nav-item { margin: 1.5rem 0; }
            .hamburger { display: block; cursor: pointer; }
            .hamburger.active .bar:nth-child(2) { opacity: 0; }
            .hamburger.active .bar:nth-child(1) { transform: translateY(8px) rotate(45deg); }
            .hamburger.active .bar:nth-child(3) { transform: translateY(-8px) rotate(-45deg); }
            .nav-right-cluster { display: none; }
            .hamburger { display: block; }
            .hero-content h1 { font-size: 2.8rem; }
            .section-title { font-size: 2.2rem; }
            .stats-grid, .menu-content, .menu-grid { grid-template-columns: 1fr; }
            .menu-filters { display: flex; width: 100%; }
            .filter-btn { flex: 1; padding: 10px 5px; }
            .hero-section { padding: 0 20px; }
            .newsletter-form { flex-direction: column; gap: 15px; }
            .newsletter-form input, .newsletter-form button { width: 100%; max-width: none; }
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="navbar container">
            <a href="#home" class="nav-logo">
                <span class="logo-cafe"><span class="first-letter">C</span>afe</span>
                <span class="logo-emmanuel"><span class="first-letter">E</span>mmanuel</span>
            </a>
            <ul class="nav-menu">
                <li class="nav-item"><a href="index.php" class="nav-link active">Home</a></li>
                <li class="nav-item"><a href="product.php" class="nav-link">Menu</a></li>
                <li class="nav-item"><a href="about.php" class="nav-link">About</a></li>
                <li class="nav-item"><a href="contact.php" class="nav-link">Contact</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item"><a href="my_orders.php" class="nav-link">My Orders</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-right-cluster">
                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['fullname'])): ?>
                    <div class="profile-dropdown">
                        <div class="profile-info">
                            <i class="fa fa-user-circle"></i>
                            <span><?php echo htmlspecialchars(explode(' ', $_SESSION['fullname'])[0]); ?></span>
                            <i class="fa fa-caret-down"></i>
                        </div>
                        <div class="dropdown-content">
                            <a href="profile.php"><i class="fa fa-user"></i> My Profile</a>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="Dashboard.php"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
                            <?php endif; ?>
                            <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Log Out</a>
                        </div>
                    </div>
                <?php else: ?>
                    <button id="loginModalBtn" class="nav-button">Sign In / Sign Up</button>
                <?php endif; ?>
                
                <a href="cart.php" class="nav-cart-link"><i class="fas fa-shopping-cart"></i></a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php include 'notification_bell.php'; ?>
                <?php endif; ?>

            </div>
            <div class="hamburger">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </nav>
    </header>

    <main>
        <section id="home" class="hero-section">
            <div class="hero-content">
                <h1>Welcome to <span class="highlight-text">Cafe</span>Emmanuel</h1>
                <p>Where every cup tells a story and every meal brings people together. Experience the perfect blend of artisanal coffee, fresh food, and warm atmosphere.</p>
                <div class="hero-buttons">
                    <a href="product.php" class="btn btn-primary">View Menu</a>
                </div>
            </div>
        </section>

        <section id="menu" class="menu-section">
            <div class="container">
                <h2 class="section-title">Our Menu</h2>
                <p class="section-subtitle">Discover our carefully curated selection of artisanal coffee, fresh food, and delicious treats.</p>
                
                <div class="menu-tabs">
                    <button class="tab-link active" data-tab="coffee">Coffee & Drinks</button>
                    <button class="tab-link" data-tab="food">Food</button>
                    <button class="tab-link" data-tab="desserts">Desserts</button>
                </div>

                <div id="coffee" class="menu-content active">
                    <?php if (!empty($coffee_items)): ?>
                        <?php foreach ($coffee_items as $item): ?>
                            <div class="menu-item">
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <div class="item-details">
                                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p>A delightful coffee choice.</p>
                                </div>
                                <p class="item-price">₱<?php echo number_format($item['price'], 2); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No coffee or drinks available at the moment.</p>
                    <?php endif; ?>
                </div>

                <div id="food" class="menu-content">
                      <?php if (!empty($food_items)): ?>
                        <?php foreach ($food_items as $item): ?>
                            <div class="menu-item">
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <div class="item-details">
                                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p>A savory <?php echo htmlspecialchars($item['category']); ?> dish.</p>
                                </div>
                                <p class="item-price">₱<?php echo number_format($item['price'], 2); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No food items available at the moment.</p>
                    <?php endif; ?>
                </div>

                <div id="desserts" class="menu-content">
                      <?php if (!empty($dessert_items)): ?>
                        <?php foreach ($dessert_items as $item): ?>
                            <div class="menu-item">
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <div class="item-details">
                                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p>A sweet treat to complete your meal.</p>
                                </div>
                                <p class="item-price">₱<?php echo number_format($item['price'], 2); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No desserts available at the moment.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        
        <section id="about" class="about-section">
            <div class="container">
                <h2 class="section-title">About Cafe Emmanuel</h2>
                <p class="section-subtitle">Founded in 2018, we believe in creating a warm, welcoming space where great coffee meets genuine hospitality.</p>
                
                <div class="features-grid">
                    <div class="feature-card">
                        <i class="fas fa-coffee"></i>
                        <h3>Artisanal Coffee</h3>
                        <p>We source our beans directly from small farms and roast them in-house to ensure the perfect cup every time.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-heart"></i>
                        <h3>Made with Love</h3>
                        <p>Every dish and drink is prepared with care and attention to detail, using only the freshest ingredients.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-users"></i>
                        <h3>Community Hub</h3>
                        <p>More than just a cafe, we're a gathering place where neighbors become friends and ideas come to life.</p>
                    </div>
                </div>

                <div class="story-section">
                    <div class="story-content">
                        <h3>Our Story</h3>
                        <p>What started as a simple dream to serve exceptional coffee has grown into a community cornerstone. We wanted to create a space that felt like an extension of your living room—comfortable, welcoming, and filled with the aroma of freshly roasted coffee.</p>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-box"><strong>500+</strong><span>Cups per day</span></div>
                        <div class="stat-box"><strong>6</strong><span>Years serving</span></div>
                        <div class="stat-box"><strong>7</strong><span>Days a week</span></div>
                        <div class="stat-box"><strong>1</strong><span>Amazing team</span></div>
                    </div>
                </div>
            </div>
        </section>
        
        <section id="contact" class="contact-section">
            <div class="container">
                <h2 class="section-title">Visit Us</h2>
                <p class="section-subtitle">Come experience our cozy atmosphere and exceptional coffee. We'd love to welcome you to our cafe family.</p>
                
                <div class="contact-wrapper">
                    <div class="contact-info">
                        <div class="info-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <strong>ADDRESS</strong>
                                <p>San Antonio Road, Purok Dayat, San Antonio, Guagua, Pampanga 2003</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <strong>PHONE</strong>
                                <p>(555) 123-CAFE</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <strong>EMAIL</strong>
                                <p>hello@Cafeemmanuel.com</p>
                            </div>
                        </div>

                        <div class="hours-box">
                            <h4><i class="fas fa-clock"></i> Hours of Operation</h4>
                            <ul>
                                <li><span>Monday - Friday</span> <span>6:00 AM - 8:00 PM</span></li>
                                <li><span>Saturday</span> <span>7:00 AM - 9:00 PM</span></li>
                                <li><span>Sunday</span> <span>7:00 AM - 7:00 PM</span></li>
                            </ul>
                        </div>
                        <div class="contact-buttons">
                            <a href="#" class="btn btn-dark">Get Directions</a>
                            <a href="#" class="btn btn-outline">Call Now</a>
                        </div>
                    </div>
                    <div class="contact-map">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15418.99539257606!2d120.6283333!3d14.9785!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33965f3583168961%3A0x199271167195b053!2sSan%20Antonio%2C%20Guagua%2C%20Pampanga!5e0!3m2!1sen!2sph!4v1697520335832!5m2!1sen!2sph" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                </div>
            </div>
        </section>

        <section class="newsletter-section">
            <div class="container">
                <div class="newsletter-content">
                    <h3>Stay Connected</h3>
                    <p>Subscribe to our newsletter for special offers, new menu items, and cafe updates.</p>
                    <form class="newsletter-form">
                        <input type="email" placeholder="Enter your email" required>
                        <button type="submit">Subscribe</button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer class="new-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <h4 class="footer-logo">Cafe Emmanuel</h4>
                    <p>—where every cup is a brushstroke of flavor and every moment, a work of art.</p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/profile.php?id=61574968445731" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.instagram.com/cafeemmanuelph/" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="footer-contact">
                    <h4>Contact Info</h4>
                    <p>San Antonio Road, Purok Dayat, San Antonio, Guagua, Pampanga 2003</p>
                    <p>(555) 123-CAFE</p>
                    <p>hello@CafeEmmanuel.com</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© 2025 Cafe Emmanuel. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" id="closeLoginModal">×</span>
            <h2>Login to Cafe Emmanuel</h2>
            <?php if ($login_error): ?><p style="color:red;"><?php echo $login_error; ?></p><?php endif; ?>
            <form id="loginFormModal" method="POST" action="index.php">
            <input type="text" name="identifier" placeholder="Email or Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <div class="options">
                <label><input type="checkbox" name="remember"> Remember me</label>
                <a href="#" id="openForgotPassword">Forgot Password?</a>
            </div>
            <button type="submit" name="login">Login</button>
            <p class="register">Don't you have an account? <a href="#" id="showRegisterModal">Register</a></p>
            </form>
        </div>
    </div>

    <div id="registerModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" id="closeRegisterModal">×</span>
            <h2>Register to CafeEmmanuel</h2>
            <?php if ($register_error): ?><p style="color:red;"><?php echo $register_error; ?></p><?php endif; ?>
            <?php if ($register_success): ?><p style="color:green;"><?php echo $register_success; ?></p><?php endif; ?>
            <form method="POST" action="index.php">
                <input type="text" name="fullname" placeholder="Full Name" required minlength="8">
                <input type="text" name="username" placeholder="Username" required minlength="4">
                <input type="email" name="email" placeholder="Email" required>
                <input type="tel" name="contact" placeholder="Contact Number" pattern="[0-9]{10,15}"  title="Contact number must be 10-15 digits">
                <select name="gender" required style="width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box;">
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Non-Binary">Non-Binary</option>
                    <option value="Prefer not to say">Prefer not to say</option>
                </select>
                <input type="password" name="password" placeholder="Password" required minlength="8" pattern="^(?=.*[A-Z]).{8,}$" title="Password must be at least 8 characters and contain one capital letter">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <button type="submit" name="register">Register</button>
                <p class="login">Have an account? <a href="#" id="showLoginModal">Login</a></p>
            </form>
        </div>
    </div>

    <div id="otpModal" class="modal" <?php if(isset($_SESSION['show_otp_modal']) && $_SESSION['show_otp_modal']): ?>style="display:block;"<?php endif; ?>>
        <div class="modal-content">
            <span class="close-btn" id="closeOtpModal">×</span>
            <h2>Verify Your Email</h2>
            <p style="font-size:14px; color:#666; margin-bottom:15px;">We've sent a 6-digit code to <strong><?php echo htmlspecialchars($_SESSION['otp_email'] ?? ''); ?></strong></p>
            <?php if (isset($_SESSION['otp_resent']) && $_SESSION['otp_resent']): unset($_SESSION['otp_resent']); ?><p style="color:green; font-size:14px;">New code sent! Check your email.</p><?php endif; ?>
            <?php if ($otp_error): ?><p style="color:red; font-size:14px;"><?php echo $otp_error; ?></p><?php endif; ?>
            <form method="POST" action="index.php">
                <input type="text" name="otp_code" placeholder="Enter 6-digit code" required maxlength="6" pattern="[0-9]{6}" style="text-align:center; font-size:20px; letter-spacing:8px; font-weight:bold;">
                <button type="submit" name="verify_otp">Verify</button>
                <div style="display:flex; justify-content:space-between; margin-top:15px; font-size:13px;">
                    <a href="resend_otp.php" style="color:#E03A3E; text-decoration:none; font-weight:600;">Resend Code</a>
                    <a href="logout.php" style="color:#666; text-decoration:none;">Use different account</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" id="closeForgotPassword">×</span>
            <h2 id="forgotPasswordTitle">Forgot Password?</h2>
            <p id="forgotPasswordSubtitle" style="font-size:14px; color:#666; margin-bottom:20px;">Choose how to receive your password reset code.</p>
            
            <div id="forgotPasswordMessage" style="display:none; padding:12px; border-radius:6px; margin-bottom:15px; font-size:14px;"></div>
            
            <form id="forgotPasswordForm">
                <div style="margin-bottom:20px;">
                    <p style="font-weight:600; margin-bottom:10px; font-size:14px;">Select reset method:</p>
                    <div style="display:flex; gap:10px;">
                        <label style="flex:1; display:flex; flex-direction:column; align-items:center; padding:15px; border:2px solid #B95A4B; background:#fff5f5; border-radius:8px; cursor:pointer; transition:all 0.3s;" id="emailMethodLabel">
                            <input type="radio" name="resetMethod" value="email" checked style="display:none;">
                            <i class="fa fa-envelope" style="font-size:2rem; color:#B95A4B; margin-bottom:5px;"></i>
                            <span style="font-size:0.9rem; font-weight:600;">Email</span>
                        </label>
                        <label style="flex:1; display:flex; flex-direction:column; align-items:center; padding:15px; border:2px solid #e0e0e0; border-radius:8px; cursor:pointer; transition:all 0.3s;" id="phoneMethodLabel">
                            <input type="radio" name="resetMethod" value="phone" style="display:none;">
                            <i class="fa fa-mobile-alt" style="font-size:2rem; color:#B95A4B; margin-bottom:5px;"></i>
                            <span style="font-size:0.9rem; font-weight:600;">SMS</span>
                        </label>
                    </div>
                </div>
                
                <!-- Email input (shown by default) -->
                <div id="emailInputSection">
                    <label style="display:block; font-weight:600; margin-bottom:8px; font-size:14px; color:#555;">
                        <i class="fa fa-envelope"></i> Email Address
                    </label>
                    <input type="email" id="forgotEmail" placeholder="Enter your email address" required style="width:100%; padding:10px; margin:8px 0; border:1px solid #ccc; border-radius:6px; box-sizing:border-box;">
                </div>
                
                <!-- Phone input (hidden by default) -->
                <div id="phoneInputSection" style="display:none;">
                    <label style="display:block; font-weight:600; margin-bottom:8px; font-size:14px; color:#555;">
                        <i class="fa fa-mobile-alt"></i> Phone Number
                    </label>
                    <input type="tel" id="forgotPhone" placeholder="Enter your phone number" pattern="[0-9]{10,15}" style="width:100%; padding:10px; margin:8px 0; border:1px solid #ccc; border-radius:6px; box-sizing:border-box;">
                    <small style="display:block; color:#666; font-size:12px; margin-top:5px;">Enter the phone number registered with your account</small>
                </div>
                
                <button type="submit" style="background-color:#111; color:white; padding:12px 20px; margin:20px 0 10px 0; border:none; border-radius:6px; cursor:pointer; width:100%; font-size:16px; transition:background 0.3s;">
                    <i class="fa fa-paper-plane"></i> Send Reset Code
                </button>
                <button type="button" id="backToLogin" style="background-color:transparent; color:#B95A4B; padding:10px; border:2px solid #B95A4B; border-radius:6px; cursor:pointer; width:100%; font-size:14px; transition:all 0.3s;">
                    <i class="fa fa-arrow-left"></i> Back to Login
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // OTP modal controls
            const otpModal = document.getElementById("otpModal");
            const closeOtpModal = document.getElementById("closeOtpModal");
            if(closeOtpModal) closeOtpModal.onclick = () => { 
                if(confirm('Are you sure? You will need to login again.')) {
                    window.location.href = 'logout.php';
                }
            };
            const header = document.querySelector('.header');
            const hamburger = document.querySelector('.hamburger');
            const navMenu = document.querySelector('.nav-menu');
            const tabLinks = document.querySelectorAll('.tab-link');
            const menuContents = document.querySelectorAll('.menu-content');

            if (header) {
                window.addEventListener('scroll', () => {
                    if (window.scrollY > 50) {
                        header.classList.add('scrolled');
                    } else {
                        header.classList.remove('scrolled');
                    }
                });
            }

            if (hamburger) {
                hamburger.addEventListener('click', () => {
                    hamburger.classList.toggle('active');
                    navMenu.classList.toggle('active');
                });
            }

            tabLinks.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabLinks.forEach(item => item.classList.remove('active'));
                    menuContents.forEach(item => item.classList.remove('active'));

                    const target = document.querySelector(`#${tab.dataset.tab}`);
                    tab.classList.add('active');
                    if (target) {
                        target.classList.add('active');
                    }
                });
            });
        });

        document.addEventListener("DOMContentLoaded", function() {
            const loginModal = document.getElementById("loginModal");
            const registerModal = document.getElementById("registerModal");
            const loginBtn = document.getElementById("loginModalBtn");
            const closeLoginModal = document.getElementById("closeLoginModal");
            const closeRegisterModal = document.getElementById("closeRegisterModal");
            const showRegisterModal = document.getElementById("showRegisterModal");
            const showLoginModal = document.getElementById("showLoginModal");
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'login') {
                if (loginModal) {
                    loginModal.style.display = "block";
                }
            }
            if(loginBtn) loginBtn.onclick = () => { loginModal.style.display = "block"; }
            if(closeLoginModal) closeLoginModal.onclick = () => { loginModal.style.display = "none"; }
            if(closeRegisterModal) closeRegisterModal.onclick = () => { registerModal.style.display = "none"; }
            window.onclick = (event) => {
                if (event.target == loginModal) loginModal.style.display = "none";
                if (event.target == registerModal) registerModal.style.display = "none";
            }
            if(showRegisterModal) showRegisterModal.onclick = (e) => { e.preventDefault(); loginModal.style.display = "none"; registerModal.style.display = "block"; }
            if(showLoginModal) showLoginModal.onclick = (e) => { e.preventDefault(); registerModal.style.display = "none"; loginModal.style.display = "block"; }
            const profileDropdown = document.querySelector('.profile-dropdown');
            if (profileDropdown) {
                profileDropdown.addEventListener('click', function(event) {
                    event.stopPropagation();
                    this.classList.toggle('active');
                });
                window.addEventListener('click', function() {
                    if(profileDropdown.classList.contains('active')) {
                        profileDropdown.classList.remove('active');
                    }
                });
            }
            
            // Forgot Password Modal
            const forgotPasswordModal = document.getElementById('forgotPasswordModal');
            const openForgotPassword = document.getElementById('openForgotPassword');
            const closeForgotPassword = document.getElementById('closeForgotPassword');
            const forgotMessage = document.getElementById('forgotPasswordMessage');
            const emailMethodLabel = document.getElementById('emailMethodLabel');
            const phoneMethodLabel = document.getElementById('phoneMethodLabel');
            const emailInputSection = document.getElementById('emailInputSection');
            const phoneInputSection = document.getElementById('phoneInputSection');
            const forgotEmailInput = document.getElementById('forgotEmail');
            const forgotPhoneInput = document.getElementById('forgotPhone');
            const backToLogin = document.getElementById('backToLogin');
            
            // Method selection - toggle input fields
            document.querySelectorAll('input[name="resetMethod"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'email') {
                        emailMethodLabel.style.borderColor = '#B95A4B';
                        emailMethodLabel.style.background = '#fff5f5';
                        phoneMethodLabel.style.borderColor = '#e0e0e0';
                        phoneMethodLabel.style.background = 'white';
                        emailInputSection.style.display = 'block';
                        phoneInputSection.style.display = 'none';
                        forgotEmailInput.required = true;
                        forgotPhoneInput.required = false;
                    } else {
                        emailMethodLabel.style.borderColor = '#e0e0e0';
                        emailMethodLabel.style.background = 'white';
                        phoneMethodLabel.style.borderColor = '#B95A4B';
                        phoneMethodLabel.style.background = '#fff5f5';
                        emailInputSection.style.display = 'none';
                        phoneInputSection.style.display = 'block';
                        forgotEmailInput.required = false;
                        forgotPhoneInput.required = true;
                    }
                });
            });
            
            if(openForgotPassword) {
                openForgotPassword.onclick = (e) => {
                    e.preventDefault();
                    loginModal.style.display = 'none';
                    forgotPasswordModal.style.display = 'block';
                    forgotMessage.style.display = 'none';
                    // Reset to email by default
                    document.querySelector('input[name="resetMethod"][value="email"]').checked = true;
                    emailInputSection.style.display = 'block';
                    phoneInputSection.style.display = 'none';
                    forgotEmailInput.value = '';
                    forgotPhoneInput.value = '';
                };
            }
            
            if(closeForgotPassword) {
                closeForgotPassword.onclick = () => {
                    forgotPasswordModal.style.display = 'none';
                };
            }
            
            if(backToLogin) {
                backToLogin.onclick = () => {
                    forgotPasswordModal.style.display = 'none';
                    loginModal.style.display = 'block';
                };
            }
            
            window.onclick = (event) => {
                if (event.target == forgotPasswordModal) forgotPasswordModal.style.display = 'none';
            };
            
            // Form submission
            document.getElementById('forgotPasswordForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const method = document.querySelector('input[name="resetMethod"]:checked').value;
                const identifier = method === 'email' ? forgotEmailInput.value : forgotPhoneInput.value;
                
                if (!identifier) {
                    showMessage('Please enter your ' + (method === 'email' ? 'email address' : 'phone number'), 'error');
                    return;
                }
                
                showMessage('Sending reset code...', 'info');
                
                try {
                    const formData = new FormData();
                    formData.append('ajax', '1');
                    formData.append('send_reset_code', '1');
                    formData.append('identifier', identifier);
                    formData.append('reset_method', method);
                    
                    const response = await fetch('forgot_password.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showMessage(data.message + ' Redirecting...', 'success');
                        setTimeout(() => {
                            window.location.href = 'reset_password.php';
                        }, 2000);
                    } else {
                        showMessage(data.message, 'error');
                    }
                } catch (error) {
                    showMessage('An error occurred. Please try again.', 'error');
                }
            });
            
            // Show message helper
            function showMessage(message, type) {
                if (!message) {
                    forgotMessage.style.display = 'none';
                    return;
                }
                forgotMessage.style.display = 'block';
                forgotMessage.textContent = message;
                if (type === 'error') {
                    forgotMessage.style.background = '#f8d7da';
                    forgotMessage.style.color = '#721c24';
                    forgotMessage.style.border = '1px solid #f5c6cb';
                } else if (type === 'success') {
                    forgotMessage.style.background = '#d4edda';
                    forgotMessage.style.color = '#155724';
                    forgotMessage.style.border = '1px solid #c3e6cb';
                } else {
                    forgotMessage.style.background = '#d1ecf1';
                    forgotMessage.style.color = '#0c5460';
                    forgotMessage.style.border = '1px solid #bee5eb';
                }
            }
        });
    </script>
</body>
</html>