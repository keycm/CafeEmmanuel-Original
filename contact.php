<?php
session_start();

// --- Registration Logic ---
$register_error = '';
$register_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Establish a new connection for registration
    $conn_register = new mysqli("localhost", "u763865560_Mancave", "ManCave2025", "u763865560_EmmanuelCafeDB");
    if ($conn_register->connect_error) {
        die("Connection failed: " . $conn_register->connect_error);
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!preg_match("/^[a-zA-Z\s]+$/", $fullname)) {
        $register_error = "Full name must contain only letters and spaces.";
    } elseif (!preg_match("/^[\w\.\-]+@(gmail\.com|email\.com)$/", $email)) {
        $register_error = "Email must be either @gmail.com or @email.com.";
    } elseif (!preg_match("/^(?=.*[A-Z]).{8,}$/", $password)) {
        $register_error = "Password must be at least 8 characters and have a capital letter.";
    } elseif ($password !== $confirm) {
        $register_error = "Passwords do not match!";
    } else {
        // Check if email or username already exists
        $stmt = $conn_register->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $register_error = "Email or Username already exists.";
        } else {
            // If user does not exist, proceed with insertion
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_stmt = $conn_register->prepare("INSERT INTO users (username, password, fullname, email) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("ssss", $username, $hashed_password, $fullname, $email);

            if ($insert_stmt->execute()) {
                $register_success = "Registration successful! You can now login.";
            } else {
                $register_error = "Error: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
    $conn_register->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Contact Us - Cafe Emmanuel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Akaya+Telivigala&family=Archivo+Black&family=Archivo+Narrow:wght@400;700&family=Birthstone+Bounce:wght@500&family=Inknut+Antiqua:wght@600&family=Playfair+Display:wght@700&family=Lato:wght@400;700&display=swap" rel="stylesheet">
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
            --font-section-heading: 'Playfair Display', serif;
            --font-body-default: 'Lato', sans-serif;
            --nav-height: 90px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-body-default); color: var(--text-color); background-color: var(--white); line-height: 1.7; }
        .container { max-width: 1140px; margin: 0 auto; padding: 0 20px; }
        .header { position: fixed; width: 100%; top: 0; z-index: 1000; height: var(--nav-height); background: rgba(26, 18, 11, 0.85); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 100%; }
        .nav-logo { text-decoration: none; color: var(--white); display: flex; align-items: center; }
        .logo-cafe { font-family: var(--font-logo-cafe); font-size: 40px; }
        .logo-emmanuel { font-family: var(--font-logo-emmanuel); font-size: 40px; font-weight: 500; margin-left: 10px; }
        .first-letter { color: #932432; }
        .nav-menu { display: flex; list-style: none; gap: 3rem; }
        .nav-link { font-family: var(--font-nav); font-size: 16px; font-weight: 600; color: #E0E0E0; text-decoration: none; transition: color 0.3s ease; }
        .nav-link:hover, .nav-link.active { color: var(--footer-link-hover); }
        .nav-button { background-color: var(--footer-link-hover); color: var(--secondary-color); padding: 10px 22px; border-radius: 8px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease; border: none; cursor: pointer; }
        .nav-button:hover { background-color: #e6b33a; }
        .hamburger { display: none; }
        .bar { display: block; width: 25px; height: 3px; margin: 5px auto; transition: all 0.3s ease-in-out; background-color: var(--white); }
        .nav-right-cluster { display: flex; align-items: center; gap: 1.5rem; }
        .nav-cart-link { color: var(--white); font-size: 1.2rem; text-decoration: none; transition: color 0.3s ease; }
        .nav-cart-link:hover { color: var(--footer-link-hover); }
        .profile-dropdown { position: relative; display: inline-block; cursor: pointer; }
        .profile-info { display: flex; align-items: center; gap: 8px; color: var(--white); }
        .profile-info span { font-weight: 500; font-size: 16px; font-family: var(--font-nav); color: #E0E0E0; transition: color 0.3s ease; }
        .profile-info i { font-size: 1.1rem; color: #E0E0E0; transition: color 0.3s ease; }
        .profile-dropdown:hover .profile-info span, .profile-dropdown:hover .profile-info i { color: var(--footer-link-hover); }
        .dropdown-content { display: none; position: absolute; right: 0; top: 150%; background-color: var(--white); min-width: 180px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 1001; border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); }
        .dropdown-content a { color: var(--text-color); padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 500; text-align: left; transition: background-color 0.2s, color 0.2s; }
        .dropdown-content a:hover { background-color: #f1f1f1; color: var(--primary-color); }
        .profile-dropdown.active .dropdown-content { display: block; }
        .contact-page-section { padding-top: calc(var(--nav-height) + 6rem); padding-bottom: 6rem; background-color: var(--white); }
        .contact-page-header { text-align: center; margin-bottom: 4rem; }
        .contact-page-header h1 { font-family: var(--font-section-heading); font-size: 2.8rem; color: var(--heading-color); margin-bottom: 1rem; }
        .contact-page-header p { max-width: 600px; margin: 0 auto; color: #666; font-size: 1.1rem; }
        .contact-page-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 3rem; align-items: flex-start; }
        .contact-info-block { display: flex; gap: 1.5rem; padding-bottom: 1.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .contact-info-block:last-child { border-bottom: none; }
        .contact-info-block i { font-size: 1.5rem; color: var(--primary-color); margin-top: 5px; }
        .info-text h3 { font-family: var(--font-section-heading); font-size: 1.3rem; margin-bottom: 0.5rem; color: var(--heading-color); }
        .info-text p { color: #666; line-height: 1.6; margin: 0; }
        .contact-form-wrapper { background-color: #fcfcfc; padding: 2.5rem; border-radius: 12px; border: 1px solid var(--border-color); }
        .contact-form-wrapper h2 { font-family: var(--font-section-heading); margin-bottom: 2rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid #ddd; font-family: var(--font-body-default); font-size: 1rem; transition: border-color 0.3s, box-shadow 0.3s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(185, 90, 75, 0.1); }
        .btn-form-submit { width: 100%; padding: 15px; background-color: var(--secondary-color); color: var(--white); border: none; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: background-color 0.3s; }
        .btn-form-submit:hover { background-color: #2a1e17; }
        .form-message { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .form-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .form-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .new-footer { background-color: var(--footer-bg-color); color: var(--footer-text-color); padding: 4rem 0 1rem; font-size: 0.95rem; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 2rem; margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .footer-about .footer-logo { font-family: var(--font-section-heading); font-size: 1.8rem; color: var(--white); margin-bottom: 1rem; }
        .footer-about p { color: #a0a0a0; line-height: 1.6; margin-bottom: 1.5rem; }
        .social-links { display: flex; gap: 15px; margin-top: 1rem; }
        .social-links a { color: var(--footer-text-color); font-size: 1.3rem; transition: color 0.3s ease; }
        .social-links a:hover { color: var(--footer-link-hover); }
        .footer-links h4, .footer-contact h4 { font-family: var(--font-body-default); font-size: 1.1rem; font-weight: bold; color: var(--white); margin-bottom: 1.2rem; }
        .footer-links ul, .footer-contact ul { list-style: none; }
        .footer-links li { margin-bottom: 0.8rem; }
        .footer-links a, .footer-contact a { color: var(--footer-text-color); text-decoration: none; transition: color 0.3s ease; }
        .footer-links a:hover, .footer-contact a:hover { color: var(--footer-link-hover); }
        .footer-contact p { margin-bottom: 0.8rem; }
        .footer-bottom { text-align: center; padding-top: 1rem; font-size: 0.85rem; color: #888; }
        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); padding-top: 60px; }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 30px; border: 1px solid #888; width: 90%; max-width: 380px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); text-align: center; position: relative; animation: fadeIn 0.5s; }
        .close-btn { color: #aaa; position: absolute; top: 10px; right: 20px; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; }
        .modal-content h2 { margin-bottom: 20px; }
        .modal-content input[type="email"], .modal-content input[type="password"], .modal-content input[type="text"] { width: 100%; padding: 10px; margin: 8px 0; display: inline-block; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .modal-content button { background-color: #111; color: white; padding: 12px 20px; margin: 15px 0; border: none; border-radius: 6px; cursor: pointer; width: 100%; font-size: 16px; transition: background-color 0.3s; }
        .modal-content button:hover { background-color: #333; }
        .modal-content .options { display: flex; justify-content: space-between; align-items: center; font-size: 14px; margin-top: 10px; margin-bottom: 15px; }
        .modal-content .register a, .modal-content .login a { color: #E03A3E; font-weight: bold; text-decoration: none; }
        .modal-content .register a:hover, .modal-content .login a:hover { text-decoration: underline; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @media (max-width: 992px) {
            .contact-page-grid, .footer-grid { grid-template-columns: 1fr; }
            .footer-grid { text-align: center; }
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
            .nav-button, .nav-right-cluster { display: none; }
            .hamburger { display: block; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
</head>
<body>

    <header class="header header-page"> 
        <nav class="navbar container">
            <a href="index.php" class="nav-logo">
                <span class="logo-cafe"><span class="first-letter">C</span>afe</span>
                <span class="logo-emmanuel"><span class="first-letter">E</span>mmanuel</span>
            </a>
            <ul class="nav-menu">
                <li class="nav-item"><a href="index.php" class="nav-link">Home</a></li>
                <li class="nav-item"><a href="product.php" class="nav-link">Menu</a></li>
                <li class="nav-item"><a href="about.php" class="nav-link">About</a></li>
                <li class="nav-item"><a href="contact.php" class="nav-link active">Contact</a></li>
                <!-- Add My Orders link if logged in -->
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
                            <?php if (in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
                                <a href="Dashboard.php"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
                            <?php endif; ?>
                            <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Log Out</a>
                        </div>
                    </div>
                <?php else: ?>
                    <button id="loginModalBtn" class="nav-button">Sign In / Sign Up</button>
                <?php endif; ?>
                
                <a href="cart.php" class="nav-cart-link"><i class="fas fa-shopping-cart"></i></a>
                <a href="#" class="nav-cart-link"><i class="fas fa-bell"></i></a>
            </div>
            <div class="hamburger">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </nav>
    </header>

    <main>
        <section class="contact-page-section">
            <div class="container">
                <div class="contact-page-header">
                    <h1>Get in Touch</h1>
                    <p>We'd love to hear from you! Have a question or need help with an order? Fill out the form and our team will get back to you within 24 hours.</p>
                </div>

                <div class="contact-page-grid">
                    <div class="contact-details">
                        <div class="contact-info-block">
                            <i class="fas fa-map-marker-alt"></i>
                            <div class="info-text">
                                <h3>Location</h3>
                                <p>San Antonio, Guagua, 2003 Pampanga</p>
                            </div>
                        </div>
                        <div class="contact-info-block">
                            <i class="fas fa-phone"></i>
                            <div class="info-text">
                                <h3>Phone</h3>
                                <p>+639 131 019 6878</p>
                            </div>
                        </div>
                        <div class="contact-info-block">
                            <i class="fas fa-envelope"></i>
                            <div class="info-text">
                                <h3>Email</h3>
                                <p>CafeEmmanuel09209@gmail.com</p>
                            </div>
                        </div>
                    </div>

                    <div class="contact-form-wrapper">
                        <h2>Send us a Message</h2>
                        <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                            <div class="form-message form-success">✓ Thank you for your message! We will get back to you shortly.</div>
                        <?php elseif(isset($_GET['status']) && $_GET['status'] == 'error'): ?>
                            <div class="form-message form-error">✗ There was an error sending your message. Please try again.</div>
                        <?php endif; ?>

                        <form action="submit_inquiry.php" method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="fname">First Name</label>
                                    <input type="text" id="fname" name="fname" placeholder="First Name" required>
                                </div>
                                <div class="form-group">
                                    <label for="lname">Last Name</label>
                                    <input type="text" id="lname" name="lname" placeholder="Last Name" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" placeholder="name@example.com" required>
                            </div>
                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea id="message" name="message" rows="5" placeholder="Tell us what's on your mind..." required></textarea>
                            </div>
                            <button type="submit" class="btn-form-submit">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <footer class="new-footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <h4 class="footer-logo">Cafe Emmanuel</h4>
                    <p>Your go-to destination for exquisite footwear, blending modern trends with timeless style.</p>
                </div>
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="product.php">Menu</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-contact">
                    <h4>Contact Info</h4>
                    <p>Fortuna, Floridablanca, Pampanga</p>
                    <p>+639 131 019 6878</p>
                    <p><a href="mailto:Cafe09209@gmail.com">CafeEmmanuel09209@gmail.com</a></p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© 2025 Cafe Emmanuel. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <div id="loginModal" class="modal">
        <div class="modal-content">
          <span class="close-btn">&times;</span>
          <h2>Login to Cafe Emmanuel</h2>
          <form method="POST" action="contact.php">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <div class="options">
              <label><input type="checkbox" name="remember"> Remember me</label>
              <a href="#">Forgot Password?</a>
            </div>
            <button type="submit" name="login">Login</button>
            <p class="register">Don't you have an account? <a href="#">Register</a></p>
          </form>
        </div>
    </div>

    <div id="registerModal" class="modal">
        <div class="modal-content">
          <span class="close-btn">&times;</span>
          <h2>Register to CafeEmmanuel</h2>
          <?php if ($register_error): ?><p style="color:red;"><?php echo $register_error; ?></p><?php endif; ?>
          <?php if ($register_success): ?><p style="color:green;"><?php echo $register_success; ?></p><?php endif; ?>
          <form method="POST" action="contact.php">
              <input type="text" name="fullname" placeholder="Full Name" required minlength="8">
              <input type="text" name="username" placeholder="Username" required minlength="4">
              <input type="email" name="email" placeholder="Email" required>
              <input type="password" name="password" placeholder="Password" required minlength="8" pattern="^(?=.*[A-Z]).{8,}$" title="Password must be at least 8 characters and contain one capital letter">
              <input type="password" name="confirm_password" placeholder="Confirm Password" required>
              <button type="submit" name="register">Register</button>
              <p class="login">Have an account? <a href="#">Login</a></p>
          </form>
        </div>
    </div>
  
  <script>
    document.addEventListener("DOMContentLoaded", function() {
        const header = document.querySelector('.header');
        const hamburger = document.querySelector('.hamburger');
        const navMenu = document.querySelector('.nav-menu');

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

        const loginModal = document.getElementById("loginModal");
        const registerModal = document.getElementById("registerModal");
        const loginBtn = document.getElementById("loginModalBtn");
        
        if (loginModal && registerModal) {
            const closeLoginModal = loginModal.querySelector(".close-btn");
            const closeRegisterModal = registerModal.querySelector(".close-btn");
            const showRegisterLink = loginModal.querySelector(".register a");
            const showLoginLink = registerModal.querySelector(".login a");

            if(loginBtn) loginBtn.onclick = () => { loginModal.style.display = "block"; }
            if(closeLoginModal) closeLoginModal.onclick = () => { loginModal.style.display = "none"; }
            if(closeRegisterModal) closeRegisterModal.onclick = () => { registerModal.style.display = "none"; }
            if(showRegisterLink) {
                showRegisterLink.onclick = (e) => { e.preventDefault(); loginModal.style.display = "none"; registerModal.style.display = "block"; }
            }
            if(showLoginLink) {
                showLoginLink.onclick = (e) => { e.preventDefault(); registerModal.style.display = "none"; loginModal.style.display = "block"; }
            }
            
            window.addEventListener('click', (event) => {
                if (event.target == loginModal) loginModal.style.display = "none";
                if (event.target == registerModal) registerModal.style.display = "none";
            });
        }
        
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
    });
  </script>

</body>
</html>