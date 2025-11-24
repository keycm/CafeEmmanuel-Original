<?php
session_start();
require_once 'config.php'; // Use the central config file for DB connection
require_once 'audit.php';

// Check database connection from config.php
if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? 'Database configuration error'));
}

// --- Fetch Products from Database ---
$products = [];
// Note: Ensure your 'products' table exists in the database
$result = $conn->query("SELECT * FROM products WHERE stock > 0 ORDER BY category, name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// --- Registration Logic ---
$register_error = '';
$register_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $contact  = trim($_POST['contact'] ?? '');
    $gender   = trim($_POST['gender'] ?? '');
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!preg_match("/^[a-zA-Z\s]+$/", $fullname)) {
        $register_error = "Full name must contain only letters and spaces.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $register_error = "Invalid email format.";
    } elseif (!empty($contact) && !preg_match("/^[0-9]{10,15}$/", $contact)) {
        $register_error = "Contact number must be 10-15 digits.";
    } elseif (strlen($password) < 8 || !preg_match("/[A-Z]/", $password)) {
        $register_error = "Password must be at least 8 characters and have a capital letter.";
    } elseif ($password !== $confirm) {
        $register_error = "Passwords do not match!";
    } else {
        // Check if email or username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $register_error = "Email or Username already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Prepare INSERT statement
            $insert_stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, contact, gender) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("ssssss", $username, $hashed_password, $fullname, $email, $contact, $gender);

            if ($insert_stmt->execute()) {
                $new_user_id = $insert_stmt->insert_id;
                $register_success = "Registration successful! You can now login.";
                
                // Log the registration if audit system is available
                if (function_exists('audit')) {
                     audit($new_user_id, 'user_registered', 'users', $new_user_id, ['username' => $username]);
                }
            } else {
                $register_error = "Error: " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
}

// --- Login Logic (Optional: Handle login directly here if needed, or rely on index.php) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Redirect to index.php to handle login, passing POST data is tricky with redirect, 
    // better to include the login logic or keep the form pointing to index.php
    // For this fix, we will assume the form action points to index.php or handles it here.
    // If your login modal form action is "product.php", add login logic here:
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
             // Reload page to update UI
            header("Location: product.php");
            exit();
        } else {
             $login_error = "Invalid password.";
        }
    } else {
         $login_error = "User not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - Cafe Emmanuel</title>
    
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
        body { font-family: var(--font-body-default); padding-top: var(--nav-height); background-color: #fff; }
        .header { position: fixed; width: 100%; top: 0; z-index: 1000; height: var(--nav-height); transition: background-color 0.4s ease, backdrop-filter 0.4s ease; background: rgba(26, 18, 11, 0.85); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
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
        .nav-button { background-color: var(--footer-link-hover); color: var(--secondary-color); padding: 10px 22px; border-radius: 8px; text-decoration: none; font-weight: bold; transition: background-color 0.3s ease; border: none; cursor: pointer; }
        .nav-button:hover { background-color: #e6b33a; }
        .hamburger { display: none; margin-left: 1rem; cursor: pointer; }
        .bar { display: block; width: 25px; height: 3px; margin: 5px auto; transition: all 0.3s ease-in-out; background-color: var(--white); }
        .profile-dropdown { position: relative; display: inline-block; cursor: pointer; }
        .profile-info { display: flex; align-items: center; gap: 8px; color: var(--white); }
        .profile-info span { font-weight: 500; font-size: 16px; font-family: var(--font-nav); color: #E0E0E0; transition: color 0.3s ease; }
        .profile-info i { font-size: 1.1rem; color: #E0E0E0; transition: color 0.3s ease; }
        .profile-dropdown:hover .profile-info span, .profile-dropdown:hover .profile-info i { color: var(--footer-link-hover); }
        .dropdown-content { display: none; position: absolute; right: 0; top: 150%; background-color: #fff; min-width: 180px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1); z-index: 1001; border-radius: 8px; overflow: hidden; border: 1px solid #eee; }
        .dropdown-content a { color: black; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 500; text-align: left; transition: background-color 0.2s; }
        .dropdown-content a:hover { background-color: #f1f1f1; color: #B95A4B; }
        .profile-dropdown.active .dropdown-content { display: block; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); padding-top: 60px; align-items: flex-start; justify-content: center; }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 30px; border: 1px solid #888; width: 90%; max-width: 360px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); text-align: center; position: relative; animation: fadeIn 0.5s; }
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
        .container { max-width: 1140px; margin: 0 auto; padding: 0 20px; }
        .section-title { font-family: 'Playfair Display', serif; text-align: center; font-size: 2.8rem; color: #1F1F1F; margin-bottom: 1rem; }
        .section-subtitle { text-align: center; max-width: 650px; margin: 0 auto 3.5rem auto; color: #666; font-size: 1.1rem; }
        .menu-page-section { padding-top: 4rem; padding-bottom: 6rem; }
        .menu-filters { display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem; margin-bottom: 3rem; }
        .filter-btn { font-family: 'Lato', sans-serif; background-color: #f0f0f0; color: #555; border: none; padding: 10px 25px; border-radius: 50px; cursor: pointer; font-weight: bold; font-size: 0.95rem; transition: all 0.3s ease; text-decoration: none; }
        .filter-btn:hover { background-color: #ddd; }
        .filter-btn.active { background-color: #B95A4B; color: #fff; box-shadow: 0 4px 10px rgba(185, 90, 75, 0.3); }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
        .menu-item-card { background-color: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); transition: transform 0.3s ease; display: flex; flex-direction: column; height: 400px; cursor: pointer; }
        .menu-item-card:hover { transform: translateY(-8px); }
        .card-img-wrapper { width: 100%; height: 70%; overflow: hidden; position: relative; background-color: #f0f0f0; }
        .card-img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; }
        .menu-item-card:hover .card-img-wrapper img { transform: scale(1.05); }
        .card-info-overlay { background-color: #2b3542; color: #fff; padding: 1rem 1.5rem; height: 30%; display: flex; flex-direction: column; justify-content: center; position: relative; }
        .card-info-overlay h3 { font-family: 'Playfair Display', serif; color: #fff; font-size: 1.4rem; margin-bottom: 0.5rem; line-height: 1.2; }
        .card-info-overlay .price { font-size: 1.1rem; color: #FFC94A; font-weight: bold; }
        .cart-btn { position: absolute; bottom: 1rem; right: 1.5rem; background-color: #B95A4B; color: #fff; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 1rem; transition: background-color 0.3s ease, transform 0.3s ease; z-index: 3; border: none; cursor: pointer; }
        .cart-btn:hover { background-color: #a14436; transform: scale(1.1); }
        @media (max-width: 850px) {
            .nav-menu { position: fixed; left: -100%; top: var(--nav-height); flex-direction: column; background-color: var(--secondary-color); width: 100%; text-align: center; transition: 0.3s; padding: 2rem 0; }
            .nav-menu.active { left: 0; }
            .nav-item { margin: 1rem 0; }
            .hamburger { display: block; }
            .nav-right-cluster { display: none; }
            .hamburger { display: block; }
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
            <li class="nav-item"><a href="product.php" class="nav-link active">Menu</a></li>
            <li class="nav-item"><a href="about.php" class="nav-link">About</a></li>
            <li class="nav-item"><a href="contact.php" class="nav-link">Contact</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item"><a href="my_orders.php" class="nav-link">My Orders</a></li>
                <?php endif; ?>
            <li class="nav-item" style="display: none;"><a href="cart.php" class="nav-link">My Cart</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item" style="display: none;"><a href="logout.php" class="nav-link">Log Out</a></li>
            <?php else: ?>
                <li class="nav-item" style="display: none;"><a href="#" id="loginModalBtnMobile" class="nav-link">Login/Register</a></li>
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

<div id="loginModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" id="closeLoginModal">&times;</span>
      <h2>Login to Cafe Emmanuel</h2>
      <?php if (isset($login_error)): ?><p style="color:red;"><?php echo $login_error; ?></p><?php endif; ?>
      <form method="POST" action="product.php">
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
      <h2>Register to CafeEmmanuel</h2>
       <?php if (isset($register_error) && $register_error): ?><p style="color:red;"><?php echo $register_error; ?></p><?php endif; ?>
       <?php if (isset($register_success) && $register_success): ?><p style="color:green;"><?php echo $register_success; ?></p><?php endif; ?>
      <form method="POST" action="product.php">
          <input type="text" name="fullname" placeholder="Full Name" required minlength="8">
          <input type="text" name="username" placeholder="Username" required minlength="4">
          <input type="email" name="email" placeholder="Email" required>
          <input type="password" name="password" placeholder="Password" required minlength="8" pattern="^(?=.*[A-Z]).{8,}$" title="Password must be at least 8 characters and contain one capital letter">
          <input type="password" name="confirm_password" placeholder="Confirm Password" required>
          <button type="submit" name="register">Register</button>
          <p class="login">Have an account? <a href="#" id="showLoginModal">Login</a></p>
      </form>
    </div>
</div>

<main>
    <section class="menu-page-section">
        <div class="container">
            <h2 class="section-title">Our Menu</h2>
            <p class="section-subtitle">Discover our carefully curated selection of artisanal coffee, fresh food, and delicious treats.</p>
            
            <div class="menu-filters">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="burger">Burger</button>
                <button class="filter-btn" data-filter="pizza">Pizza</button>
                <button class="filter-btn" data-filter="pasta">Pasta</button>
                <button class="filter-btn" data-filter="coffee">Coffee</button>
                <button class="filter-btn" data-filter="dessert">Dessert</button>
            </div>

            <div class="menu-grid">
                <?php
                    foreach ($products as $product) {
                        $product_data = htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8');

                        $onclick_action = isset($_SESSION['user_id'])
                            ? "viewProduct(" . $product_data . ")"
                            : "document.getElementById('loginModal').style.display='flex'";
                        
                        echo '
                        <div class="menu-item-card" data-category="'. htmlspecialchars($product['category']) .'" onclick="'. $onclick_action .'">
                            <div class="card-img-wrapper">
                                <img src="' . htmlspecialchars($product['image']) . '" alt="' . htmlspecialchars($product['name']) . '">
                            </div>
                            <div class="card-info-overlay">
                                <h3>' . htmlspecialchars($product['name']) . '</h3>
                                <p class="price">₱' . number_format($product['price'], 2) . '</p>
                                <button class="cart-btn"><i class="fas fa-shopping-cart"></i></button>
                            </div>
                        </div>';
                    }
                ?>
            </div>
        </div>
    </section>
</main>

<script>
    function viewProduct(productData) {
      localStorage.setItem('selectedProduct', JSON.stringify(productData));
      window.location.href = 'quantity.php';
    }

    document.addEventListener("DOMContentLoaded", function() {
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
        
        // --- MODAL CONTROLS ---
        const loginModal = document.getElementById("loginModal");
        const registerModal = document.getElementById("registerModal");
        const loginBtn = document.getElementById("loginModalBtn");
        const loginBtnMobile = document.getElementById("loginModalBtnMobile");
        const closeLoginModal = document.getElementById("closeLoginModal");
        const closeRegisterModal = document.getElementById("closeRegisterModal");
        const showRegisterModal = document.getElementById("showRegisterModal");
        const showLoginModal = document.getElementById("showLoginModal");

        if(loginBtn) loginBtn.onclick = () => { loginModal.style.display = "flex"; }
        if(loginBtnMobile) loginBtnMobile.onclick = (e) => { e.preventDefault(); loginModal.style.display = "flex"; }
        if(closeLoginModal) closeLoginModal.onclick = () => { loginModal.style.display = "none"; }
        if(closeRegisterModal) closeRegisterModal.onclick = () => { registerModal.style.display = "none"; }
        
        window.onclick = (event) => {
            if (event.target == loginModal) loginModal.style.display = "none";
            if (event.target == registerModal) registerModal.style.display = "none";
        }

        if(showRegisterModal) showRegisterModal.onclick = (e) => { e.preventDefault(); loginModal.style.display = "none"; registerModal.style.display = "flex"; }
        if(showLoginModal) showLoginModal.onclick = (e) => { e.preventDefault(); registerModal.style.display = "none"; loginModal.style.display = "flex"; }
        
        // --- NAVIGATION & FILTER SCRIPT ---
        const hamburger = document.querySelector('.hamburger');
        const navMenu = document.querySelector('.nav-menu');

        if (hamburger) {
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                navMenu.classList.toggle('active');
                
                const mobileItems = navMenu.querySelectorAll('.nav-item[style*="display: none"]');
                mobileItems.forEach(item => {
                    item.style.display = navMenu.classList.contains('active') ? 'block' : 'none';
                });
            });
        }

        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === 'product.php') {
                link.classList.add('active');
            }
        });

        const filterButtons = document.querySelectorAll('.filter-btn');
        const menuItems = document.querySelectorAll('.menu-item-card');

        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                filterButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                const filterValue = button.getAttribute('data-filter');
                menuItems.forEach(item => {
                    if (filterValue === 'all' || item.getAttribute('data-category') === filterValue) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    });
</script>

</body>
</html>