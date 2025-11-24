<?php
session_start();
include 'config.php'; // Handles connection to the main database as $conn
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
        // Fetch latest unused OTP for this user
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
            // Increment attempts
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
            // Ensure user is verified upon successful OTP
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
            
            // Cleanup OTP session vars
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
// 2. LOGIN LOGIC (Email OR Username)
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
                    $_SESSION['email'] = $user['email'];
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
            
            // Try to insert with is_verified column (default 0)
            // Fallback to standard insert if column missing
            $insert_stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, contact, gender, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
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

// ---------------------------------------------------------
// 4. FETCH MENU ITEMS (FIXED: Uses existing $conn)
// ---------------------------------------------------------
$coffee_items = $food_items = $dessert_items = [];

// Use the $conn that was established in config.php
if (isset($conn) && !$conn->connect_error) {
    // Fetch Coffee & Drinks (Category: coffee)
    $coffee_result = $conn->query("SELECT * FROM products WHERE category = 'coffee' AND stock > 0 ORDER BY id DESC LIMIT 4");
    $coffee_items = $coffee_result ? $coffee_result->fetch_all(MYSQLI_ASSOC) : [];

    // Fetch Food (Categories: pizza, burger, pasta)
    $food_result = $conn->query("SELECT * FROM products WHERE category IN ('pizza', 'burger', 'pasta') AND stock > 0 ORDER BY id DESC LIMIT 4");
    $food_items = $food_result ? $food_result->fetch_all(MYSQLI_ASSOC) : [];

    // Fetch Desserts (Category: dessert)
    $dessert_result = $conn->query("SELECT * FROM products WHERE category = 'dessert' AND stock > 0 ORDER BY id DESC LIMIT 4");
    $dessert_items = $dessert_result ? $dessert_result->fetch_all(MYSQLI_ASSOC) : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cafe Emmanuel - Roasting with Art</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Akaya+Telivigala&family=Archivo+Black&family=Archivo+Narrow:wght@400;700&family=Birthstone+Bounce:wght@500&family=Inknut+Antiqua:wght@600&family=Playfair+Display:wght@400;600;700&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        /* --- Global Variables --- */
        :root {
            /* Colors */
            --primary-color: #B95A4B;
            --primary-dark: #9C4538;
            --secondary-color: #3C2A21;
            --accent-color: #E03A3E;
            
            --text-color: #333;
            --text-light: #666;
            --heading-color: #1F1F1F;
            --bg-body: #FFFFFF;
            --bg-light: #FCFBF8;
            --bg-gray: #F9F9F9;
            --white: #FFFFFF;

            --footer-bg-color: #1a120b;
            --footer-text-color: #ccc;
            --footer-link-hover: #FFC94A;

            /* Custom Fonts */
            --font-logo-cafe: 'Archivo Black', sans-serif;
            --font-logo-emmanuel: 'Birthstone Bounce', cursive;
            --font-nav: 'Inknut Antiqua', serif;
            --font-hero: 'Akaya Telivigala', cursive;
            --font-heading: 'Playfair Display', serif;
            --font-body: 'Lato', sans-serif;
            
            --nav-height: 90px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: var(--nav-height); }
        
        body {
            font-family: var(--font-body);
            color: var(--text-color);
            background-color: var(--bg-body);
            line-height: 1.7;
            overflow-x: hidden;
        }

        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        h1, h2, h3, h4 { color: var(--heading-color); margin-bottom: 1rem; }
        a { text-decoration: none; color: inherit; transition: all 0.3s ease; }
        ul { list-style: none; }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 12px 30px;
            border-radius: 50px;
            font-family: var(--font-body);
            font-weight: 700;
            font-size: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .btn-primary { background-color: var(--primary-color); color: var(--white); box-shadow: 0 4px 15px rgba(185, 90, 75, 0.3); }
        .btn-primary:hover { background-color: var(--primary-dark); transform: translateY(-2px); }
        .btn-outline { background-color: transparent; color: var(--white); border-color: var(--white); }
        .btn-outline:hover { background-color: var(--white); color: var(--secondary-color); }
        .btn-dark { background-color: var(--heading-color); color: var(--white); }
        .btn-dark:hover { background-color: #000; }

        /* --- Header & Navigation --- */
        .header {
            position: fixed; width: 100%; top: 0; z-index: 1000; height: var(--nav-height);
            background: transparent; transition: background 0.4s ease, box-shadow 0.4s ease;
        }
        .header.scrolled { background: rgba(26, 18, 11, 0.98); box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 100%; }
        .nav-logo { display: flex; align-items: center; color: var(--white); }
        .logo-cafe { font-family: var(--font-logo-cafe); font-size: 32px; letter-spacing: -1px; }
        .logo-emmanuel { font-family: var(--font-logo-emmanuel); font-size: 38px; margin-left: 8px; color: var(--primary-color); font-weight: 500; }
        .nav-menu { display: flex; gap: 2.5rem; }
        .nav-link {
            font-family: var(--font-nav); font-size: 15px; font-weight: 500; color: rgba(255,255,255,0.9); position: relative; letter-spacing: 0.5px;
        }
        .nav-link::after {
            content: ''; position: absolute; width: 0; height: 2px; bottom: -4px; left: 0; background-color: var(--footer-link-hover); transition: width 0.3s;
        }
        .nav-link:hover::after, .nav-link.active::after { width: 100%; }
        .nav-link:hover, .nav-link.active { color: var(--footer-link-hover); }
        .nav-right-cluster { display: flex; align-items: center; gap: 1.5rem; }
        .nav-icon-link { color: var(--white); font-size: 1.2rem; transition: color 0.3s; }
        .nav-icon-link:hover { color: var(--footer-link-hover); }
        .login-trigger {
            background: var(--primary-color); color: var(--white); padding: 8px 24px; border-radius: 30px; font-weight: 600; font-size: 0.9rem; border: none; cursor: pointer; transition: background 0.3s;
        }
        .login-trigger:hover { background: var(--primary-dark); }
        
        .profile-dropdown { position: relative; cursor: pointer; }
        .profile-header { display: flex; align-items: center; gap: 8px; color: var(--white); font-weight: 500; }
        .profile-menu {
            display: none; position: absolute; right: 0; top: 140%; background: var(--white); min-width: 200px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); overflow: hidden; z-index: 1001;
        }
        .profile-dropdown:hover .profile-menu { display: block; }
        .profile-menu a { display: block; padding: 12px 20px; color: var(--text-color); font-size: 0.95rem; border-bottom: 1px solid var(--border-color); }
        .profile-menu a:hover { background: var(--bg-light); color: var(--primary-color); }
        
        .hamburger { display: none; cursor: pointer; z-index: 1002; }
        .bar { display: block; width: 25px; height: 3px; margin: 5px auto; background-color: var(--white); transition: 0.3s; }

        /* --- Hero Section --- */
        .hero-section {
            position: relative; height: 100vh; width: 100%; overflow: hidden; display: flex; align-items: center; justify-content: flex-start; margin-top: 0;
        }
        .hero-slider, .hero-overlay, .hero-slide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .hero-slide { background-size: cover; background-position: center; opacity: 0; transition: opacity 1.5s ease-in-out; transform: scale(1.05); }
        .hero-slide.active { opacity: 1; transform: scale(1); transition: opacity 1.5s ease-in-out, transform 6s ease-out; }
        .hero-overlay { background: linear-gradient(to right, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.6) 40%, rgba(0,0,0,0) 100%); z-index: 1; }
        .hero-content {
            position: relative; z-index: 2; color: #FFFFFF; max-width: 700px; padding-left: 5%; padding-right: 20px; margin: 0; text-align: left; opacity: 0; animation: fadeInUp 1.2s ease-out forwards 0.5s;
        }
        .hero-content h1 {
            font-family: var(--font-hero); font-size: 5rem; line-height: 1.1; margin-bottom: 1.5rem; color: #FFFFFF; text-shadow: 2px 2px 20px rgba(0,0,0,0.9);
        }
        .hero-content h1 span { color: var(--primary-color); }
        .hero-content p {
            font-size: 1.3rem; font-weight: 400; margin-bottom: 2.5rem; color: #FFFFFF; text-shadow: 1px 1px 10px rgba(0,0,0,0.9); max-width: 600px; line-height: 1.6;
        }
        .hero-actions { display: flex; gap: 1rem; justify-content: flex-start; }

        /* --- Menu Section --- */
        .section-padding { padding: 6rem 0; }
        .section-title { font-family: var(--font-heading); font-size: 3rem; text-align: center; margin-bottom: 1rem; color: var(--heading-color); }
        .section-subtitle { text-align: center; color: var(--text-light); max-width: 600px; margin: 0 auto 3.5rem; font-size: 1.1rem; }
        .menu-tabs { display: flex; justify-content: center; gap: 1rem; margin-bottom: 3rem; }
        .tab-btn { background: transparent; border: 2px solid var(--border-color); padding: 10px 25px; border-radius: 30px; font-family: var(--font-body); font-weight: 600; color: var(--text-light); cursor: pointer; transition: all 0.3s; }
        .tab-btn:hover, .tab-btn.active { background: var(--primary-color); border-color: var(--primary-color); color: var(--white); }
        .menu-grid { display: none; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 2rem; animation: fadeIn 0.5s ease; }
        .menu-grid.active { display: grid; }
        .menu-item-card { background: var(--white); border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: transform 0.3s ease; border: 1px solid var(--border-color); display: flex; flex-direction: column; }
        .menu-item-card:hover { transform: translateY(-8px); box-shadow: 0 15px 40px rgba(0,0,0,0.1); }
        .card-img { height: 220px; overflow: hidden; background: #f4f4f4; }
        .card-img img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .menu-item-card:hover .card-img img { transform: scale(1.1); }
        .card-body { padding: 1.5rem; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .card-title { font-family: var(--font-heading); font-size: 1.3rem; font-weight: 700; margin-bottom: 0.5rem; }
        .card-desc { font-size: 0.9rem; color: var(--text-light); margin-bottom: 1rem; line-height: 1.5; }
        .card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: auto; }
        .card-price { font-weight: 700; color: var(--primary-color); font-size: 1.2rem; }
        
        /* --- About Section --- */
        .about-section { background-color: var(--bg-light); }
        .about-content { display: grid; grid-template-columns: 1.2fr 1fr; gap: 4rem; align-items: center; }
        .about-text h3 { font-family: var(--font-heading); font-size: 2.2rem; margin-bottom: 1.5rem; }
        .about-text p { margin-bottom: 1.5rem; color: #555; font-size: 1.05rem; }
        .features-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-bottom: 4rem; }
        .feature-box { background: var(--white); padding: 2.5rem; border-radius: 12px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.03); transition: transform 0.3s; }
        .feature-box:hover { transform: translateY(-5px); }
        .feature-box i { font-size: 2.5rem; color: var(--primary-color); margin-bottom: 1.5rem; }
        .feature-box h4 { font-family: var(--font-heading); font-size: 1.25rem; }
        .stats-row { display: flex; gap: 2rem; margin-top: 2rem; }
        .stat-item { text-align: center; flex: 1; background: rgba(185, 90, 75, 0.05); padding: 1rem; border-radius: 10px; }
        .stat-num { font-size: 2rem; font-weight: 700; color: var(--primary-color); display: block; }
        .stat-label { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; color: #777; }

        /* --- Contact Section --- */
        .contact-section { background: var(--white); padding-bottom: 0; }
        .contact-wrapper { display: grid; grid-template-columns: 1fr 1.5fr; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.08); margin-bottom: 5rem; }
        .contact-details { background: var(--secondary-color); color: var(--white); padding: 3rem; display: flex; flex-direction: column; justify-content: center; }
        .contact-details h3 { color: var(--white); font-family: var(--font-heading); font-size: 2rem; margin-bottom: 2rem; }
        .contact-item { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
        .contact-item i { color: var(--footer-link-hover); font-size: 1.2rem; margin-top: 5px; }
        .hours-box { background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 10px; margin-top: 1rem; }
        .hours-row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem; }
        .map-container { min-height: 400px; }
        .map-container iframe { width: 100%; height: 100%; border: 0; }

        /* --- Newsletter --- */
        .newsletter-section { background: var(--bg-gray); text-align: center; }
        .newsletter-card { background: var(--white); max-width: 800px; margin: 0 auto; padding: 3rem; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .newsletter-form { display: flex; gap: 10px; max-width: 500px; margin: 2rem auto 0; }
        .newsletter-input { flex: 1; padding: 12px 20px; border: 1px solid var(--border-color); border-radius: 50px; font-family: var(--font-body); outline: none; }
        .newsletter-input:focus { border-color: var(--primary-color); }

        /* --- Footer --- */
        .footer { background-color: var(--footer-bg-color); color: var(--footer-text-color); padding-top: 4rem; font-size: 0.95rem; }
        .footer-content { display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 3rem; padding-bottom: 3rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .footer-brand h3 { color: var(--white); font-family: var(--font-logo-cafe); font-size: 1.8rem; }
        .footer-brand p { opacity: 0.7; margin-bottom: 1.5rem; line-height: 1.7; }
        .socials { display: flex; gap: 15px; }
        .social-link { width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .social-link:hover { background: var(--footer-link-hover); color: var(--secondary-color); }
        .footer-col h4 { color: var(--white); font-size: 1.1rem; margin-bottom: 1.5rem; font-family: var(--font-body); }
        .footer-links li { margin-bottom: 0.8rem; }
        .footer-links a { color: rgba(255,255,255,0.6); transition: 0.3s; }
        .footer-links a:hover { color: var(--footer-link-hover); padding-left: 5px; }
        .copyright { text-align: center; padding: 1.5rem 0; opacity: 0.5; font-size: 0.85rem; }

        /* --- Modal Styles --- */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center;
            backdrop-filter: blur(8px); animation: fadeIn 0.3s;
        }
        .modal-box {
            background: #ffffff;
            padding: 40px;
            border-radius: 24px;
            width: 90%;
            max-width: 420px;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .modal-close {
            position: absolute; top: 20px; right: 25px; font-size: 1.5rem; color: #ccc; cursor: pointer; transition: 0.2s;
        }
        .modal-close:hover { color: var(--primary-color); }
        
        .modal-title {
            text-align: center; font-family: var(--font-heading); font-size: 2rem;
            margin-bottom: 2rem; color: var(--heading-color); letter-spacing: -0.5px;
        }
        
        /* Input Groups */
        .input-group { position: relative; margin-bottom: 20px; }
        .input-icon {
            position: absolute; left: 18px; top: 50%; transform: translateY(-50%);
            color: #aaa; font-size: 1rem; transition: 0.3s;
        }
        .input-field {
            width: 100%; padding: 14px 15px 14px 48px;
            border: 1px solid #e2e8f0; border-radius: 12px;
            font-family: var(--font-body); font-size: 1rem;
            background: #f8f9fa; transition: 0.3s; color: var(--text-color);
        }
        .input-field:focus {
            border-color: var(--primary-color); background: #fff;
            box-shadow: 0 0 0 4px rgba(185, 90, 75, 0.1); outline: none;
        }
        .input-field:focus + .input-icon { color: var(--primary-color); }
        
        .modal-btn { width: 100%; padding: 14px; border-radius: 12px; font-size: 1rem; margin-top: 10px; letter-spacing: 0.5px; }
        .modal-options { display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; margin-bottom: 20px; color: #666; }
        .modal-footer { text-align: center; margin-top: 25px; font-size: 0.95rem; color: #666; }
        .link-highlight { color: var(--primary-color); font-weight: 700; cursor: pointer; transition: 0.2s; }
        .link-highlight:hover { text-decoration: underline; color: var(--primary-dark); }
        
        select.input-field { appearance: none; cursor: pointer; }

        /* Animations */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); scale: 0.95; } to { opacity: 1; transform: translateY(0); scale: 1; } }

        /* Responsive */
        @media (max-width: 992px) {
            .hero-content h1 { font-size: 3.5rem; }
            .about-content { grid-template-columns: 1fr; text-align: center; }
            .contact-wrapper, .footer-content { grid-template-columns: 1fr; }
            .features-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .nav-menu, .nav-right-cluster { display: none; }
            .hamburger { display: block; }
            .nav-menu.active { display: flex; flex-direction: column; position: absolute; top: var(--nav-height); left: 0; width: 100%; background: var(--secondary-color); padding: 2rem; text-align: center; }
            .mobile-link { display: block; }
            .hero-content { padding-left: 20px; margin-left: 0; }
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
                <li><a href="index.php" class="nav-link active">Home</a></li>
                <li><a href="product.php" class="nav-link">Menu</a></li>
                <li><a href="about.php" class="nav-link">About</a></li>
                <li><a href="contact.php" class="nav-link">Contact</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="my_orders.php" class="nav-link">My Orders</a></li>
                <?php endif; ?>
                
                <li class="mobile-link" style="display:none;"><a href="cart.php" class="nav-link">Cart</a></li>
                <?php if (!isset($_SESSION['user_id'])): ?>
                <li class="mobile-link" style="display:none;"><a href="#" onclick="openModal('loginModal')" class="nav-link">Login</a></li>
                <?php else: ?>
                <li class="mobile-link" style="display:none;"><a href="logout.php" class="nav-link">Logout</a></li>
                <?php endif; ?>
            </ul>
            
            <div class="nav-right-cluster">
                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['fullname'])): ?>
                    <div class="profile-dropdown">
                        <div class="profile-header">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars(explode(' ', $_SESSION['fullname'])[0]); ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 0.8rem;"></i>
                        </div>
                        <div class="profile-menu">
                            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <?php if (in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
                                <a href="Dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                            <?php endif; ?>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Log Out</a>
                        </div>
                    </div>
                <?php else: ?>
                    <button id="loginTrigger" class="login-trigger" onclick="openModal('loginModal')">Login</button>
                <?php endif; ?>
                
                <a href="cart.php" class="nav-icon-link"><i class="fas fa-shopping-cart"></i></a>
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
            <div class="hero-slider">
                <div class="hero-slide active" style="background-image: url('Cover-Photo.jpg');"></div>
                <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1509042239860-f550ce710b93?q=80&w=2574&auto=format&fit=crop');"></div>
                <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1559339352-11d035aa65de?q=80&w=2574&auto=format&fit=crop');"></div>
                <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?q=80&w=2678&auto=format&fit=crop');"></div>
            </div>
            
            <div class="hero-overlay"></div>

            <div class="hero-content">
                <h1>Welcome to <span>Cafe</span>Emmanuel</h1>
                <p>Where every cup tells a story and every meal brings people together. Experience the perfect blend of artisanal coffee, fresh food, and warm atmosphere.</p>
                <div class="hero-actions">
                    <a href="product.php" class="btn btn-primary">View Menu</a>
                    <a href="#about" class="btn btn-outline">Our Story</a>
                </div>
            </div>
        </section>

        <section id="menu" class="section-padding menu-section">
            <div class="container">
                <header class="section-header">
                    <h2 class="section-title">Our Specialties</h2>
                    <p class="section-subtitle">Handpicked favorites from our kitchen to your table. Fresh ingredients, crafted with passion.</p>
                </header>
                
                <div class="menu-tabs">
                    <button class="tab-btn active" onclick="switchTab('coffee', this)">Coffee & Drinks</button>
                    <button class="tab-btn" onclick="switchTab('food', this)">Food</button>
                    <button class="tab-btn" onclick="switchTab('desserts', this)">Desserts</button>
                </div>

                <div id="coffee" class="menu-grid active">
                    <?php if (!empty($coffee_items)): ?>
                        <?php foreach ($coffee_items as $item): ?>
                            <div class="menu-item-card">
                                <div class="card-img">
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                <div class="card-body">
                                    <div>
                                        <h3 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <p class="card-desc">A rich and aromatic blend brewed to perfection.</p>
                                    </div>
                                    <div class="card-footer">
                                        <span class="card-price">₱<?php echo number_format($item['price'], 2); ?></span>
                                        <a href="product.php" class="btn btn-primary" style="padding: 8px 15px; border-radius: 50%; font-size: 0.9rem;"><i class="fas fa-plus"></i></a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align:center; grid-column:1/-1;">No coffee items available.</p>
                    <?php endif; ?>
                </div>

                <div id="food" class="menu-grid">
                     <?php if (!empty($food_items)): ?>
                        <?php foreach ($food_items as $item): ?>
                            <div class="menu-item-card">
                                <div class="card-img">
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                <div class="card-body">
                                    <div>
                                        <h3 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <p class="card-desc">Delicious <?php echo htmlspecialchars($item['category']); ?> made with fresh ingredients.</p>
                                    </div>
                                    <div class="card-footer">
                                        <span class="card-price">₱<?php echo number_format($item['price'], 2); ?></span>
                                        <a href="product.php" class="btn btn-primary" style="padding: 8px 15px; border-radius: 50%; font-size: 0.9rem;"><i class="fas fa-plus"></i></a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align:center; grid-column:1/-1;">No food items available.</p>
                    <?php endif; ?>
                </div>

                <div id="desserts" class="menu-grid">
                     <?php if (!empty($dessert_items)): ?>
                        <?php foreach ($dessert_items as $item): ?>
                            <div class="menu-item-card">
                                <div class="card-img">
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </div>
                                <div class="card-body">
                                    <div>
                                        <h3 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                                        <p class="card-desc">A sweet treat to finish your meal.</p>
                                    </div>
                                    <div class="card-footer">
                                        <span class="card-price">₱<?php echo number_format($item['price'], 2); ?></span>
                                        <a href="product.php" class="btn btn-primary" style="padding: 8px 15px; border-radius: 50%; font-size: 0.9rem;"><i class="fas fa-plus"></i></a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align:center; grid-column:1/-1;">No desserts available.</p>
                    <?php endif; ?>
                </div>
                
                <div style="text-align: center; margin-top: 3rem;">
                    <a href="product.php" class="btn btn-dark">Explore Full Menu</a>
                </div>
            </div>
        </section>
        
        <section id="about" class="section-padding about-section">
            <div class="container">
                <div class="features-row">
                    <div class="feature-box">
                        <i class="fas fa-mug-hot"></i>
                        <h4>Artisanal Coffee</h4>
                        <p style="color: #666; margin-top: 10px;">We source beans directly from small farms and roast them in-house for the perfect cup.</p>
                    </div>
                    <div class="feature-box">
                        <i class="fas fa-heart"></i>
                        <h4>Made with Love</h4>
                        <p style="color: #666; margin-top: 10px;">Every dish and drink is prepared with care, using only the freshest ingredients.</p>
                    </div>
                    <div class="feature-box">
                        <i class="fas fa-users"></i>
                        <h4>Community Hub</h4>
                        <p style="color: #666; margin-top: 10px;">More than a cafe, we're a gathering place where neighbors become friends.</p>
                    </div>
                </div>

                <div class="about-content">
                    <div class="about-text">
                        <h4 style="color:var(--primary-color); text-transform:uppercase; letter-spacing:1px; font-size:0.9rem;">Our Story</h4>
                        <h3>A Tradition of Excellence</h3>
                        <p>What started as a simple dream to serve exceptional coffee has grown into a community cornerstone. We wanted to create a space that felt like an extension of your living room—comfortable, welcoming, and filled with the aroma of freshly roasted coffee.</p>
                        <div class="stats-row">
                            <div class="stat-item"><span class="stat-num">500+</span><span class="stat-label">Daily Cups</span></div>
                            <div class="stat-item"><span class="stat-num">6</span><span class="stat-label">Years Serving</span></div>
                            <div class="stat-item"><span class="stat-num">7</span><span class="stat-label">Days Open</span></div>
                        </div>
                    </div>
                    <div class="about-image">
                        <img src="Cover-Photo.jpg" alt="Cafe Interior" style="width:100%; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
                    </div>
                </div>
            </div>
        </section>
        
        <section id="contact" class="section-padding contact-section">
            <div class="container">
                <div class="contact-wrapper">
                    <div class="contact-details">
                        <h3>Visit Us</h3>
                        
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <strong style="display:block; margin-bottom:4px;">Location</strong>
                                <p>San Antonio, Guagua, 2003 Pampanga</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <strong style="display:block; margin-bottom:4px;">Phone</strong>
                                <p>(555) 123-CAFE</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <strong style="display:block; margin-bottom:4px;">Email</strong>
                                <p>hello@CafeEmmanuel.com</p>
                            </div>
                        </div>

                        <div class="hours-box">
                            <h4 style="color:white; margin-bottom:15px; border-bottom:1px solid rgba(255,255,255,0.2); padding-bottom:10px;">Opening Hours</h4>
                            <div class="hours-row"><span>Mon - Fri</span> <span>6:00 AM - 8:00 PM</span></div>
                            <div class="hours-row"><span>Saturday</span> <span>7:00 AM - 9:00 PM</span></div>
                            <div class="hours-row"><span>Sunday</span> <span>7:00 AM - 7:00 PM</span></div>
                        </div>
                    </div>
                    <div class="map-container">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15418.99539257606!2d120.6283333!3d14.9785!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33965f3583168961%3A0x199271167195b053!2sSan%20Antonio%2C%20Guagua%2C%20Pampanga!5e0!3m2!1sen!2sph!4v1697520335832!5m2!1sen!2sph" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-padding newsletter-section">
            <div class="container">
                <div class="newsletter-card">
                    <h2 style="font-family: var(--font-heading);">Stay Connected</h2>
                    <p style="color:#666;">Subscribe to our newsletter for special offers, new menu items, and cafe updates.</p>
                    <form class="newsletter-form">
                        <input type="email" placeholder="Enter your email address" class="newsletter-input" required>
                        <button type="submit" class="btn btn-primary">Subscribe</button>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3><span style="color:var(--primary-color);">C</span>afe Emmanuel</h3>
                    <p>Your neighborhood destination for exceptional coffee, delicious food, and warm hospitality.</p>
                    <div class="socials">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="product.php">Menu</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h4>Contact Info</h4>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt" style="margin-right:8px; color:var(--primary-color);"></i> San Antonio, Guagua</li>
                        <li><i class="fas fa-phone" style="margin-right:8px; color:var(--primary-color);"></i> (555) 123-CAFE</li>
                        <li><i class="fas fa-envelope" style="margin-right:8px; color:var(--primary-color);"></i> hello@cafeemmanuel.com</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>© 2025 Cafe Emmanuel. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <div id="loginModal" class="modal-overlay" <?php if ($login_error) echo 'style="display:flex;"'; ?>>
        <div class="modal-box">
            <span class="modal-close" onclick="closeModal('loginModal')">×</span>
            <h2 class="modal-title">Welcome Back</h2>
            <?php if ($login_error): ?><p style="color: #dc3545; text-align: center; margin-bottom: 15px; font-size: 0.9rem;"><?php echo $login_error; ?></p><?php endif; ?>
            <form method="POST" action="index.php">
                <div class="input-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="text" name="identifier" placeholder="Email or Username" class="input-field" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" placeholder="Password" class="input-field" required>
                </div>
                <div class="modal-options">
                    <label style="display:flex; align-items:center; gap:5px;"><input type="checkbox" name="remember"> Remember me</label>
                    <a href="#" onclick="switchModal('loginModal', 'forgotPasswordModal')" class="link-highlight">Forgot Password?</a>
                </div>
                <button type="submit" name="login" class="btn btn-primary modal-btn">Login</button>
            </form>
            <div class="modal-footer">
                Don't have an account? <a href="#" onclick="switchModal('loginModal', 'registerModal')" class="link-highlight">Register</a>
            </div>
        </div>
    </div>

    <div id="registerModal" class="modal-overlay" <?php if ($register_error) echo 'style="display:flex;"'; ?>>
        <div class="modal-box" style="max-width: 450px;">
            <span class="modal-close" onclick="closeModal('registerModal')">×</span>
            <h2 class="modal-title">Create Account</h2>
            <?php if ($register_error): ?><p style="color: #dc3545; text-align: center; margin-bottom: 10px; font-size: 0.9rem;"><?php echo $register_error; ?></p><?php endif; ?>
            <?php if ($register_success): ?><p style="color: #28a745; text-align: center; margin-bottom: 10px; font-size: 0.9rem;"><?php echo $register_success; ?></p><?php endif; ?>
            <form method="POST" action="index.php">
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="fullname" placeholder="Full Name" class="input-field" required minlength="8">
                </div>
                <div class="input-group">
                    <i class="fas fa-at input-icon"></i>
                    <input type="text" name="username" placeholder="Username" class="input-field" required minlength="4">
                </div>
                <div class="input-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" placeholder="Email Address" class="input-field" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-phone input-icon"></i>
                    <input type="tel" name="contact" placeholder="Contact Number" class="input-field" pattern="[0-9]{10,15}">
                </div>
                <div class="input-group">
                    <i class="fas fa-venus-mars input-icon"></i>
                    <select name="gender" class="input-field" required>
                        <option value="" disabled selected>Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Non-Binary">Non-Binary</option>
                        <option value="Prefer not to say">Prefer not to say</option>
                    </select>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" placeholder="Password (Min 8 chars, 1 Uppercase)" class="input-field" required minlength="8" pattern="^(?=.*[A-Z]).{8,}$">
                </div>
                <div class="input-group">
                    <i class="fas fa-check-circle input-icon"></i>
                    <input type="password" name="confirm_password" placeholder="Confirm Password" class="input-field" required>
                </div>
                <button type="submit" name="register" class="btn btn-primary modal-btn">Register</button>
            </form>
            <div class="modal-footer">
                Already have an account? <a href="#" onclick="switchModal('registerModal', 'loginModal')" class="link-highlight">Login</a>
            </div>
        </div>
    </div>

    <div id="otpModal" class="modal-overlay" <?php if(isset($_SESSION['show_otp_modal']) && $_SESSION['show_otp_modal']): ?>style="display:flex;"<?php endif; ?>>
        <div class="modal-box">
            <span class="modal-close" onclick="window.location.href='logout.php'">×</span>
            <h2 class="modal-title">Verify Email</h2>
            <p style="text-align: center; color: #666; margin-bottom: 1.5rem;">We've sent a code to <br><strong><?php echo htmlspecialchars($_SESSION['otp_email'] ?? ''); ?></strong></p>
            
            <?php if (isset($_SESSION['otp_resent']) && $_SESSION['otp_resent']): unset($_SESSION['otp_resent']); ?>
                <p style="color: #28a745; text-align: center; margin-bottom: 10px;">New code sent!</p>
            <?php endif; ?>
            <?php if ($otp_error): ?><p style="color: #dc3545; text-align: center; margin-bottom: 10px;"><?php echo $otp_error; ?></p><?php endif; ?>
            
            <form method="POST" action="index.php">
                <div class="input-group">
                    <i class="fas fa-key input-icon"></i>
                    <input type="text" name="otp_code" placeholder="000000" class="input-field" required maxlength="6" pattern="[0-9]{6}" style="text-align: center; letter-spacing: 8px; font-size: 1.5rem; font-weight: 700;">
                </div>
                <button type="submit" name="verify_otp" class="btn btn-primary modal-btn">Verify</button>
            </form>
            <div class="modal-options" style="margin-top: 1.5rem; justify-content: center; gap: 15px;">
                <a href="resend_otp.php" class="link-highlight">Resend Code</a>
                <a href="logout.php" style="color: #999;">Use different account</a>
            </div>
        </div>
    </div>

    <div id="forgotPasswordModal" class="modal-overlay">
        <div class="modal-box">
            <span class="modal-close" onclick="closeModal('forgotPasswordModal')">×</span>
            <h2 class="modal-title">Reset Password</h2>
            <div id="forgotPasswordContent">
                <p style="text-align:center; margin-bottom:20px; color:#666;">Please visit the reset page to recover your account.</p>
                <a href="forgot_password.php" class="btn btn-primary modal-btn" style="display:block; text-decoration:none; text-align:center;">Go to Reset Page</a>
            </div>
            <div class="modal-footer">
                Remember your password? <a href="#" onclick="switchModal('forgotPasswordModal', 'loginModal')" class="link-highlight">Login</a>
            </div>
        </div>
    </div>

    <script>
        // --- Slider Logic ---
        let currentSlide = 0;
        const slides = document.querySelectorAll('.hero-slide');
        
        function nextSlide() {
            // Remove active class from current slide
            slides[currentSlide].classList.remove('active');
            // Calculate next index
            currentSlide = (currentSlide + 1) % slides.length;
            // Add active class to next slide
            slides[currentSlide].classList.add('active');
        }

        // Change slide every 5 seconds
        setInterval(nextSlide, 5000);

        // --- Modal Functions ---
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        function switchModal(fromId, toId) {
            closeModal(fromId);
            openModal(toId);
        }

        // Login button listeners
        const loginBtns = document.querySelectorAll('#loginTrigger, .mobile-link a[href="#"]');
        loginBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                if(e.target.innerText === "Login" || e.target.id === "loginTrigger") {
                    e.preventDefault();
                    openModal('loginModal');
                }
            });
        });

        // Tab Switching
        function switchTab(tabId, btn) {
            document.querySelectorAll('.menu-grid').forEach(grid => grid.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }

        // Mobile Menu Toggle
        const hamburger = document.querySelector('.hamburger');
        const navMenu = document.querySelector('.nav-menu');
        const mobileLinks = document.querySelectorAll('.mobile-link');

        hamburger.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            mobileLinks.forEach(link => {
                link.style.display = navMenu.classList.contains('active') ? 'block' : 'none';
            });
        });

        // Close modals on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.style.display = "none";
            }
        }

        // Header scroll effect
        window.addEventListener('scroll', () => {
            const header = document.querySelector('.header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>
