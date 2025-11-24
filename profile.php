<?php
session_start();
require_once 'config.php';
require_once 'audit.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle Profile Picture Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $fileTmp = $_FILES['profile_picture']['tmp_name'];
        $fileSize = $_FILES['profile_picture']['size'];
        $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($fileExt, $allowed)) {
            $error_message = "Only JPG, JPEG, PNG, and GIF files are allowed.";
        } elseif ($fileSize > 5000000) { // 5MB limit
            $error_message = "File size must be less than 5MB.";
        } else {
            // Create uploads directory if not exists
            $uploadDir = __DIR__ . '/uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate unique filename
            $newFilename = 'user_' . $userId . '_' . time() . '.' . $fileExt;
            $destination = $uploadDir . $newFilename;
            
            if (move_uploaded_file($fileTmp, $destination)) {
                // Delete old profile picture if exists
                $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $oldData = $result->fetch_assoc();
                $stmt->close();
                
                if ($oldData && $oldData['profile_picture'] && file_exists(__DIR__ . '/' . $oldData['profile_picture'])) {
                    @unlink(__DIR__ . '/' . $oldData['profile_picture']);
                }
                
                // Update database
                $relativePath = 'uploads/profiles/' . $newFilename;
                $updateStmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $updateStmt->bind_param("si", $relativePath, $userId);
                
                if ($updateStmt->execute()) {
                    $success_message = "Profile picture updated successfully!";
                    audit($userId, 'profile_picture_updated', 'users', $userId, ['filename' => $newFilename]);
                } else {
                    $error_message = "Database update failed.";
                }
                $updateStmt->close();
            } else {
                $error_message = "Failed to upload file.";
            }
        }
    } else {
        $error_message = "Please select a valid image file.";
    }
}

// Handle Profile Information Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_info'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($fullname) || empty($email)) {
        $error_message = "Full name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } else {
        // Check if email already exists for another user
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkStmt->bind_param("si", $email, $userId);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            $error_message = "Email already in use by another account.";
        } else {
            $updateStmt = $conn->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ?");
            $updateStmt->bind_param("ssi", $fullname, $email, $userId);
            
            if ($updateStmt->execute()) {
                $_SESSION['fullname'] = $fullname;
                $_SESSION['email'] = $email;
                $success_message = "Profile information updated successfully!";
                audit($userId, 'profile_info_updated', 'users', $userId, ['fullname' => $fullname, 'email' => $email]);
            } else {
                $error_message = "Failed to update profile information.";
            }
            $updateStmt->close();
        }
        $checkStmt->close();
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error_message = "All password fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $error_message = "New passwords do not match.";
    } elseif (!preg_match("/^(?=.*[A-Z]).{8,}$/", $newPassword)) {
        $error_message = "Password must be at least 8 characters with one capital letter.";
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user && password_verify($currentPassword, $user['password'])) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $hashedPassword, $userId);
            
            if ($updateStmt->execute()) {
                $success_message = "Password changed successfully!";
                audit($userId, 'password_changed', 'users', $userId, []);
            } else {
                $error_message = "Failed to change password.";
            }
            $updateStmt->close();
        } else {
            $error_message = "Current password is incorrect.";
        }
    }
}



// Fetch current user data
$stmt = $conn->prepare("SELECT username, fullname, email, role, profile_picture, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Cafe Emmanuel</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Akaya+Telivigala&family=Archivo+Black&family=Archivo+Narrow:wght@400;700&family=Birthstone+Bounce:wght@500&family=Inknut+Antiqua:wght@600&family=Playfair+Display:wght@700&family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --primary-color: #B95A4B;
            --secondary-color: #3C2A21;
            --text-color: #333;
            --heading-color: #1F1F1F;
            --white: #FFFFFF;
            --border-color: #EAEAEA;
            --footer-bg-color: #1a120b;
            --footer-text-color: #ccc;
            --footer-link-hover: #FFC94A;
            --font-logo-cafe: 'Archivo Black', sans-serif;
            --font-logo-emmanuel: 'Birthstone Bounce', cursive;
            --font-nav: 'Inknut Antiqua', serif;
            --font-body-default: 'Lato', sans-serif;
            --nav-height: 90px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-body-default); padding-top: var(--nav-height); background-color: #f5f5f5; }
        
        /* Header */
        .header { position: fixed; width: 100%; top: 0; z-index: 1000; height: var(--nav-height); background: rgba(26, 18, 11, 0.85); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 100%; max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .nav-logo { text-decoration: none; color: var(--white); display: flex; align-items: center; }
        .logo-cafe { font-family: var(--font-logo-cafe); font-size: 40px; }
        .logo-emmanuel { font-family: var(--font-logo-emmanuel); font-size: 40px; font-weight: 500; margin-left: 10px; }
        .first-letter { color: #932432; }
        .nav-menu { display: flex; list-style: none; gap: 3rem; }
        .nav-link { font-family: var(--font-nav); font-size: 16px; font-weight: 600; color: #E0E0E0; text-decoration: none; transition: color 0.3s ease; }
        .nav-link:hover, .nav-link.active { color: var(--footer-link-hover); }
        .nav-right-cluster { display: flex; align-items: center; gap: 1.5rem; }
        .nav-cart-link { color: var(--white); font-size: 1.2rem; text-decoration: none; transition: color 0.3s ease; }
        .nav-cart-link:hover { color: var(--footer-link-hover); }
        .profile-dropdown { position: relative; display: inline-block; cursor: pointer; }
        .profile-info { display: flex; align-items: center; gap: 8px; color: var(--white); }
        .profile-info span { font-weight: 500; font-size: 16px; font-family: var(--font-nav); color: #E0E0E0; transition: color 0.3s ease; }
        .profile-info i { font-size: 1.1rem; color: #E0E0E0; transition: color 0.3s ease; }
        .profile-dropdown:hover .profile-info span, .profile-dropdown:hover .profile-info i { color: var(--footer-link-hover); }
        .dropdown-content { display: none; position: absolute; right: 0; top: 150%; background-color: #fff; min-width: 180px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1); z-index: 1001; border-radius: 8px; overflow: hidden; border: 1px solid #eee; }
        .dropdown-content a { color: black; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 500; text-align: left; transition: background-color 0.2s; }
        .dropdown-content a:hover { background-color: #f1f1f1; color: #B95A4B; }
        .profile-dropdown.active .dropdown-content { display: block; }
        
        /* Main Container */
        .container { max-width: 900px; margin: 2rem auto; padding: 0 20px; }
        .back-button { display: inline-flex; align-items: center; gap: 8px; background: transparent; border: none; color: #666; font-size: 16px; cursor: pointer; padding: 10px 15px; margin-bottom: 20px; text-decoration: none; transition: background-color 0.3s; border-radius: 6px; }
        .back-button:hover { background: #f5f5f5; color: #333; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 2.5rem; color: var(--heading-color); margin-bottom: 2rem; }
        
        /* Alert Messages */
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Profile Card */
        .profile-card { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .profile-header { display: flex; align-items: center; gap: 2rem; padding-bottom: 2rem; border-bottom: 2px solid #eee; margin-bottom: 2rem; }
        .profile-picture-container { position: relative; }
        .profile-picture { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-color); }
        .profile-picture-placeholder { width: 150px; height: 150px; border-radius: 50%; background: #e0e0e0; display: flex; align-items: center; justify-content: center; font-size: 4rem; color: #999; border: 4px solid #ddd; }
        .profile-details h2 { font-family: 'Playfair Display', serif; font-size: 2rem; margin-bottom: 0.5rem; }
        .profile-badge { display: inline-block; background: var(--primary-color); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem; }
        .profile-meta { color: #666; font-size: 0.95rem; }
        
        /* Form Sections */
        .form-section { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .form-section h3 { font-family: 'Playfair Display', serif; font-size: 1.5rem; margin-bottom: 1.5rem; color: var(--heading-color); display: flex; align-items: center; gap: 10px; }
        .form-section h3 i { color: var(--primary-color); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: var(--primary-color); }
        .file-input-wrapper { position: relative; overflow: hidden; display: inline-block; }
        .file-input-label { display: inline-block; padding: 10px 20px; background: var(--primary-color); color: white; border-radius: 6px; cursor: pointer; font-weight: 600; transition: background 0.3s; }
        .file-input-label:hover { background: #a14436; }
        .file-input-wrapper input[type="file"] { position: absolute; left: -9999px; }
        .file-name { margin-left: 15px; color: #666; font-style: italic; }
        .btn { padding: 12px 30px; border: none; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-primary:hover { background: #a14436; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        
        /* Delete Account Section */
        .danger-zone { border: 2px solid #dc3545; border-radius: 12px; padding: 2rem; background: #fff5f5; margin-top: 2rem; }
        .danger-zone h3 { color: #dc3545; margin-bottom: 1rem; }
        .danger-zone p { color: #666; margin-bottom: 1.5rem; }
        
        /* Modal */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); padding-top: 60px; }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 30px; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative; }
        .close-btn { color: #aaa; position: absolute; top: 10px; right: 20px; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover { color: black; }
        
        @media (max-width: 768px) {
            .profile-header { flex-direction: column; text-align: center; }
            .nav-menu { display: none; }
        }
    </style>
</head>
<body>

<header class="header">
    <nav class="navbar">
        <a href="index.php" class="nav-logo">
            <span class="logo-cafe"><span class="first-letter">C</span>afe</span>
            <span class="logo-emmanuel"><span class="first-letter">E</span>mmanuel</span>
        </a>
        <ul class="nav-menu">
            <li class="nav-item"><a href="index.php" class="nav-link">Home</a></li>
            <li class="nav-item"><a href="product.php" class="nav-link">Menu</a></li>
            <li class="nav-item"><a href="about.php" class="nav-link">About</a></li>
            <li class="nav-item"><a href="contact.php" class="nav-link">Contact</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item"><a href="my_orders.php" class="nav-link">My Orders</a></li>
            <?php endif; ?>
        </ul>
        <div class="nav-right-cluster">
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
            <a href="cart.php" class="nav-cart-link"><i class="fas fa-shopping-cart"></i></a>
            <a href="#" class="nav-cart-link"><i class="fas fa-bell"></i></a>
        </div>
    </nav>
</header>

<div class="container">
    <a href="<?php echo ($_SESSION['role'] === 'admin') ? 'Dashboard.php' : 'index.php'; ?>" class="back-button">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Back
    </a>
    
    <h1 class="page-title">My Profile</h1>
    
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <!-- Profile Overview -->
    <div class="profile-card">
        <div class="profile-header">
            <div class="profile-picture-container">
                <?php if ($user['profile_picture'] && file_exists(__DIR__ . '/' . $user['profile_picture'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" class="profile-picture">
                <?php else: ?>
                    <div class="profile-picture-placeholder">
                        <i class="fa fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <span class="profile-badge"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
                <h2><?php echo htmlspecialchars($user['fullname']); ?></h2>
                <p class="profile-meta">
                    <i class="fa fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?><br>
                    <i class="fa fa-user"></i> @<?php echo htmlspecialchars($user['username']); ?><br>
                    <i class="fa fa-calendar"></i> Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                </p>
            </div>
        </div>
        
        <!-- Upload Profile Picture Form -->
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label><i class="fa fa-camera"></i> Update Profile Picture</label>
                <div class="file-input-wrapper">
                    <label for="profile_picture" class="file-input-label">
                        <i class="fa fa-upload"></i> Choose Image
                    </label>
                    <input type="file" name="profile_picture" id="profile_picture" accept="image/*" onchange="displayFileName(this)">
                    <span class="file-name" id="file-name">No file chosen</span>
                </div>
                <small style="display: block; margin-top: 8px; color: #666;">Max file size: 5MB. Allowed formats: JPG, PNG, GIF</small>
            </div>
            <button type="submit" name="upload_picture" class="btn btn-primary">
                <i class="fa fa-upload"></i> Upload Picture
            </button>
        </form>
    </div>
    
    <!-- Edit Profile Information -->
    <div class="form-section">
        <h3><i class="fa fa-edit"></i> Edit Profile Information</h3>
        <form method="POST">
            <div class="form-group">
                <label for="fullname">Full Name</label>
                <input type="text" name="fullname" id="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="form-group">
                <label>Username (Cannot be changed)</label>
                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
            </div>
            <button type="submit" name="update_info" class="btn btn-primary">
                <i class="fa fa-save"></i> Save Changes
            </button>
        </form>
    </div>
    
    <!-- Change Password -->
    <div class="form-section">
        <h3><i class="fa fa-lock"></i> Change Password</h3>
        <form method="POST">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" name="current_password" id="current_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" name="new_password" id="new_password" required minlength="8">
                <small style="display: block; margin-top: 5px; color: #666;">Must be at least 8 characters with one capital letter</small>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required>
            </div>
            <button type="submit" name="change_password" class="btn btn-primary">
                <i class="fa fa-key"></i> Change Password
            </button>
        </form>
    </div>

<script>
    // Profile dropdown toggle
    const profileDropdown = document.querySelector('.profile-dropdown');
    if (profileDropdown) {
        profileDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
        
        document.addEventListener('click', function() {
            if(profileDropdown.classList.contains('active')) {
                profileDropdown.classList.remove('active');
            }
        });
    }
    
    // Display selected file name
    function displayFileName(input) {
        const fileName = input.files[0] ? input.files[0].name : 'No file chosen';
        document.getElementById('file-name').textContent = fileName;
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('deleteModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
</script>

</body>
</html>
