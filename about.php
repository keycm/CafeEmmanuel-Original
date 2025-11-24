<?php
session_start();

// --- Registration Logic ---
$register_error = '';
$register_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
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
        $stmt = $conn_register->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $register_error = "Email or Username already exists.";
        } else {
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
    <title>About Us - Cafe Emmanuel</title>
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
            --font-hero-heading: 'Akaya Telivigala', cursive;
            --font-hero-body: 'Archivo Narrow', sans-serif;
            --font-section-heading: 'Playfair Display', serif;
            --font-body-default: 'Lato', sans-serif;
            --nav-height: 90px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; scroll-padding-top: var(--nav-height); }
        body { font-family: var(--font-body-default); color: var(--text-color); background-color: var(--white); line-height: 1.7; }
        .container { max-width: 1140px; margin: 0 auto; padding: 0 20px; }
        section { padding: 6rem 0; }
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
        .about-hero-section { background: linear-gradient(rgba(26, 18, 11, 0.7), rgba(26, 18, 11, 0.7)), url('Cover-Photo.jpg') no-repeat center center/cover; padding: 10rem 0; color: var(--white); margin-top: var(--nav-height); }
        .about-hero-content { display: grid; grid-template-columns: 1fr 1.5fr; align-items: center; gap: 4rem; }
        .about-logo img { max-width: 100%; height: auto; border-radius: 50%; border: 5px solid rgba(255, 255, 255, 0.1); }
        .about-text-content h1 { font-family: var(--font-hero-heading); font-size: 4rem; font-weight: 400; margin-bottom: 1.5rem; }
        .about-text-content h1 .highlight { color: var(--primary-color); }
        .about-text-content p { font-family: var(--font-body-default); font-size: 1.2rem; line-height: 1.8; margin-bottom: 2.5rem; max-width: 600px; color: #e0e0e0; }
        .btn-about { background-color: var(--footer-link-hover); color: var(--secondary-color); padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 1rem; transition: background-color 0.3s ease, transform 0.3s ease; display: inline-block; }
        .btn-about:hover { background-color: #e6b33a; transform: translateY(-3px); }
        #more-info { background-color: #FCFBF8; }
        .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-bottom: 5rem; }
        .feature-card { background: var(--white); padding: 2.5rem; text-align: center; border-radius: 12px; border: 1px solid var(--border-color); }
        .feature-card i { font-size: 2.8rem; color: var(--primary-color); margin-bottom: 1.5rem; }
        .feature-card h3 { font-family: var(--font-section-heading); margin-bottom: 0.75rem; font-size: 1.4rem; }
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
        .close-btn { color: #aaa; position: absolute; top: 10px; right: 20px; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; }
        .modal-content input[type="email"], .modal-content input[type="password"], .modal-content input[type="text"] { width: 100%; padding: 10px; margin: 8px 0; display: inline-block; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .modal-content button { background-color: #111; color: white; padding: 12px 20px; margin: 15px 0; border: none; border-radius: 6px; cursor: pointer; width: 100%; font-size: 16px; transition: background-color 0.3s; }
        .modal-content button:hover { background-color: #333; }
        .modal-content .options { display: flex; justify-content: space-between; align-items: center; font-size: 14px; margin: 10px 0 15px; }
        .modal-content .register a { color: #E03A3E; font-weight: bold; }
        @media (max-width: 992px) {
            .footer-grid, .features-grid { grid-template-columns: 1fr; text-align: center; }
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
            .about-hero-section { padding: 6rem 0; }
            .about-hero-content { grid-template-columns: 1fr; text-align: center; }
            .about-logo { margin-bottom: 2rem; max-width: 250px; justify-self: center; }
            .about-text-content p { margin-left: auto; margin-right: auto; }
            .about-text-content h1 { font-size: 3rem; }
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
                <li class="nav-item"><a href="about.php" class="nav-link active">About</a></li>
                <li class="nav-item"><a href="contact.php" class="nav-link">Contact</a></li>
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
        <section class="about-hero-section">
            <div class="container about-hero-content">
                <div class="about-logo">
                    <img src="logo.png" alt="Cafe Emmanuel Logo">
                </div>
                <div class="about-text-content">
                    <h1><span class="highlight">Our</span> Story</h1>
                    <p>
                        We blend local artistry with contemporary design to create pieces that are both timeless and distinctly Filipino.
                    </p>
                    <a href="#more-info" class="btn-about">Learn More</a>
                </div>
            </div>
        </section>

        <section id="more-info" class="about-section">
             <div class="container">
                <div class="features-grid">
                    <div class="feature-card">
                        <i class="fas fa-drafting-compass"></i>
                        <h3>Local Artistry</h3>
                        <p>We blend local artistry with contemporary design to create pieces that are both timeless and distinctly Filipino.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-award"></i>
                        <h3>Quality Craftsmanship</h3>
                        <p>Each pair is meticulously crafted from premium materials to ensure lasting comfort and durability for your daily journey.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-heart"></i>
                        <h3>Customer Dedication</h3>
                        <p>We are dedicated to providing an exceptional customer experience, ensuring you feel valued and inspired with every purchase.</p>
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
                    <p>Your destination for exquisite footwear, blending modern trends with classic elegance and quality craftsmanship.</p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    </div>
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
                    <p>Fortuna, Floridablanca</p>
                    <p>Pampanga, Philippines</p>
                    <p>+639 131 019 6878</p>
                    <p>CafeEmmanuel09209@gmail.com</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© 2025 Cafe Emmanuel. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <div id="loginModal" class="modal">
        <div class="modal-content">
          <span class="close-btn" id="closeLoginModal">&times;</span>
          <h2>Login to CafeEmmanuel</h2>
          <form method="POST" action="about.php">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <div class="options">
              <label><input type="checkbox" name="remember"> Remember me</label>
              <a href="#">Forgot Password?</a>
            </div>
            <button type="submit" name="login">Login</button>
            <p class="register">Don't you have an account? <a href="#" id="showRegisterModal">Register</a></p>
          </form>
        </div>
    </div>

    <div id="registerModal" class="modal">
        <div class="modal-content">
          <span class="close-btn" id="closeRegisterModal">&times;</span>
          <h2>Register to Cafe</h2>
          <?php if ($register_error): ?><p style="color:red;"><?php echo $register_error; ?></p><?php endif; ?>
          <?php if ($register_success): ?><p style="color:green;"><?php echo $register_success; ?></p><?php endif; ?>
          <form method="POST" action="about.php">
              <input type="text" name="fullname" placeholder="Full Name" required>
              <input type="text" name="username" placeholder="Username" required>
              <input type="email" name="email" placeholder="Email" required>
              <input type="password" name="password" placeholder="Password" required>
              <input type="password" name="confirm_password" placeholder="Confirm Password" required>
              <button type="submit" name="register">Register</button>
              <p class="login">Have an account? <a href="#" id="showLoginModal">Login</a></p>
          </form>
        </div>
    </div>
  
  <script>
    document.addEventListener("DOMContentLoaded", function() {
        // --- General Setup ---
        const header = document.querySelector('.header');
        const hamburger = document.querySelector('.hamburger');
        const navMenu = document.querySelector('.nav-menu');
        const navLinks = document.querySelectorAll('.nav-link');
        
        // --- Header and Navigation Logic ---
        if (header) {
            const handleScroll = () => {
                if (window.scrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            };
            window.addEventListener('scroll', handleScroll);
        }

        if (hamburger) {
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                navMenu.classList.toggle('active');
            });
        }

        navLinks.forEach(link => link.addEventListener('click', () => {
            if (hamburger) {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
            }
        }));

        // --- MODAL CONTROLS ---
        const loginModal = document.getElementById("loginModal");
        const registerModal = document.getElementById("registerModal");
        const loginBtn = document.getElementById("loginModalBtn");
        
        if (loginModal && registerModal) {
            const closeLoginModal = loginModal.querySelector(".close-btn");
            const closeRegisterModal = registerModal.querySelector(".close-btn");
            const showRegisterLink = loginModal.querySelector(".register a");
            const showLoginLink = registerModal.querySelector(".login a");

            if(loginBtn) {
                loginBtn.onclick = () => { loginModal.style.display = "block"; }
            }
            if(closeLoginModal) {
                closeLoginModal.onclick = () => { loginModal.style.display = "none"; }
            }
            if(closeRegisterModal) {
                closeRegisterModal.onclick = () => { registerModal.style.display = "none"; }
            }
            if(showRegisterLink) {
                showRegisterLink.onclick = (e) => { 
                    e.preventDefault(); 
                    loginModal.style.display = "none"; 
                    registerModal.style.display = "block"; 
                }
            }
            if(showLoginLink) {
                showLoginLink.onclick = (e) => { 
                    e.preventDefault(); 
                    registerModal.style.display = "none"; 
                    loginModal.style.display = "block"; 
                }
            }
            
            window.addEventListener('click', (event) => {
                if (event.target == loginModal) loginModal.style.display = "none";
                if (event.target == registerModal) registerModal.style.display = "none";
            });
        }

        // --- PROFILE DROPDOWN ---
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