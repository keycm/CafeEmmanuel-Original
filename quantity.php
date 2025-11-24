<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Product Details - Cafe Emmanuel</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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
        --footer-link-hover: #FFC94A;
        --font-logo-cafe: 'Archivo Black', sans-serif;
        --font-logo-emmanuel: 'Birthstone Bounce', cursive;
        --font-nav: 'Inknut Antiqua', serif;
        --font-body-default: 'Lato', sans-serif;
        --nav-height: 90px;
    }

    /* --- Base & Reset --- */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Lato', sans-serif; color: var(--text-color); background-color: #fcfbf8; line-height: 1.7; padding-top: var(--nav-height); }
    .container { max-width: 1140px; margin: 0 auto; padding: 0 20px; }

    /* --- Header & Navigation --- */
    .header { position: fixed; width: 100%; top: 0; z-index: 1000; height: var(--nav-height); background: rgba(26, 18, 11, 0.85); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
    .navbar { display: flex; justify-content: space-between; align-items: center; height: 100%; width: 100%; padding: 0 40px; }
    .nav-logo { text-decoration: none; color: var(--white); display: flex; align-items: center; }
    .logo-cafe { font-family: var(--font-logo-cafe); font-size: 40px; }
    .logo-emmanuel { font-family: var(--font-logo-emmanuel); font-size: 40px; font-weight: 500; margin-left: 10px; }
    .first-letter { color: #932432; }
    .nav-right-wrapper { display: flex; align-items: center; gap: 2.5rem; }
    .nav-menu { display: flex; list-style: none; gap: 2.5rem; }
    .nav-link { font-family: var(--font-nav); font-size: 16px; font-weight: 600; color: #E0E0E0; text-decoration: none; }
    .nav-link:hover, .nav-link.active { color: var(--footer-link-hover); }
    .nav-right-cluster { display: flex; align-items: center; gap: 1.5rem; }
    .nav-cart-link { color: var(--white); font-size: 1.2rem; text-decoration: none; }
    .nav-button { background-color: var(--footer-link-hover); color: var(--secondary-color); padding: 10px 22px; border-radius: 8px; text-decoration: none; font-weight: bold; border: none; cursor: pointer; }
    .hamburger { display: none; margin-left: 1rem; cursor: pointer; }
    .bar { display: block; width: 25px; height: 3px; margin: 5px auto; background-color: var(--white); }
    .profile-dropdown { position: relative; display: inline-block; cursor: pointer; }
    .profile-info { display: flex; align-items: center; gap: 8px; }
    .profile-info i, .profile-info span { color: white !important; font-size: 1.1rem; }
    
    /* --- Product Details Section --- */
    .product-section { padding: 4rem 0 6rem; }
    .product-details-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 3rem; align-items: flex-start; background-color: var(--white); padding: 3rem; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); }
    .product-image-container img { width: 100%; border-radius: 12px; background-color: var(--white); }
    .product-details-content h1 { font-family: 'Playfair Display', serif; font-size: 2.8rem; color: var(--heading-color); margin-bottom: 0.5rem; }
    .product-details-content .rating { color: #f0ad4e; margin-bottom: 1rem; font-size: 1.1rem; }
    .product-details-content .price { font-family: 'Playfair Display', serif; font-size: 2.5rem; font-weight: bold; color: var(--primary-color); margin-bottom: 1.5rem; }
    .product-details-content .description { color: #666; margin-bottom: 2rem; line-height: 1.8; }
    .selector-group { margin-bottom: 1.5rem; }
    .selector-group label { display: block; font-weight: bold; margin-bottom: 0.75rem; font-size: 1.1rem; }
    .quantity-selector { display: flex; align-items: center; border: 1px solid var(--border-color); border-radius: 50px; overflow: hidden; width: fit-content; }
    .quantity-selector button { background-color: var(--white); border: none; width: 40px; height: 40px; font-size: 1.2rem; cursor: pointer; }
    .quantity-selector input { width: 50px; height: 40px; text-align: center; border: none; border-left: 1px solid var(--border-color); border-right: 1px solid var(--border-color); font-size: 1.1rem; -moz-appearance: textfield; }
    input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 2rem; }
    .action-btn { padding: 15px 20px; border: none; border-radius: 8px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: all 0.3s; }
    .add-to-cart-btn { background-color: var(--secondary-color); color: var(--white); }
    .buy-now-btn { background-color: var(--primary-color); color: var(--white); }
    
    @media (max-width: 950px) {
        .nav-menu, .nav-right-cluster { display: none; }
        .hamburger { display: block; }
    }
    @media (max-width: 768px) {
        .product-details-grid { grid-template-columns: 1fr; padding: 2rem; }
    }
  </style>
</head>
<body>
  
    <header class="header header-page"> 
        <nav class="navbar">
            <a href="index.php" class="nav-logo">
                <span class="logo-cafe"><span class="first-letter">C</span>afe</span>
                <span class="logo-emmanuel"><span class="first-letter">E</span>mmanuel</span>
            </a>
            
            <div class="nav-right-wrapper">
                <ul class="nav-menu">
                    <li class="nav-item"><a href="index.php" class="nav-link">Home</a></li>
                    <li class="nav-item"><a href="product.php" class="nav-link active">Menu</a></li>
                    <li class="nav-item"><a href="about.php" class="nav-link">About</a></li>
                    <li class="nav-item"><a href="contact.php" class="nav-link">Contact</a></li>
                </ul>

                <div class="nav-right-cluster">
                    <a href="cart.php" class="nav-cart-link"><i class="fas fa-shopping-cart"></i></a>
                    <?php if (isset($_SESSION['user_id']) && isset($_SESSION['fullname'])): ?>
                        <div class="profile-dropdown">
                            <div class="profile-info">
                                <i class="fa fa-user-circle"></i>
                                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <i class="fa fa-caret-down"></i>
                            </div>
                        </div>
                    <?php else: ?>
                        <button id="loginModalBtn" class="nav-button">Sign In / Sign Up</button>
                    <?php endif; ?>
                </div>
                
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>
  
  <main class="container">
    <section class="product-section">
        <div class="product-details-grid">
          <div class="product-image-container">
            <img src="assets/placeholder.jpg" alt="Product Image" id="main-product-image">
          </div>

          <div class="product-details-content">
            <h1 id="product-name">Product Name</h1>
            <div class="rating" id="product-rating">★★★★☆</div>
            <p class="price" id="product-price">₱0.00</p>
            <p class="description" id="product-description">Product description will be loaded here.</p>
            
            <div class="selector-group">
                <label for="quantity">Quantity</label>
                <div class="quantity-selector">
                  <button id="qty-minus">-</button>
                  <input type="number" id="quantity-input" value="1" min="1" max="10" readonly />
                  <button id="qty-plus">+</button>
                </div>
            </div>

            <div class="actions">
              <button class="action-btn add-to-cart-btn">Add to Cart</button>
              <button class="action-btn buy-now-btn" onclick="buyNow()">Buy Now</button>
            </div>
          </div>
        </div>
    </section>
  </main>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const product = JSON.parse(localStorage.getItem('selectedProduct'));

    if (!product) {
        window.location.href = 'product.php';
        return;
    }

    // Populate the page with the selected product's data
    document.getElementById('main-product-image').src = product.image;
    document.getElementById('product-name').textContent = product.name;
    document.getElementById('product-price').textContent = `₱${parseFloat(product.price).toLocaleString()}`;
    document.getElementById('product-rating').textContent = '★'.repeat(product.rating) + '☆'.repeat(5 - product.rating);
    document.getElementById('product-description').textContent = product.description;

    const quantityInput = document.getElementById('quantity-input');
    const qtyPlus = document.getElementById('qty-plus');
    const qtyMinus = document.getElementById('qty-minus');
    const addToCartBtn = document.querySelector('.add-to-cart-btn');

    qtyPlus.addEventListener('click', () => {
        let currentQty = parseInt(quantityInput.value);
        if (currentQty < 10) quantityInput.value = currentQty + 1;
    });

    qtyMinus.addEventListener('click', () => {
        let currentQty = parseInt(quantityInput.value);
        if (currentQty > 1) quantityInput.value = currentQty - 1;
    });

    addToCartBtn.addEventListener('click', () => {
        const item = {
            id: product.id,
            name: product.name,
            price: product.price,
            image: product.image,
            quantity: parseInt(quantityInput.value),
            size: 'Standard',
            color: 'N/A'
        };

        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        const existingItemIndex = cart.findIndex(cartItem => cartItem.id === item.id);

        if (existingItemIndex > -1) {
            cart[existingItemIndex].quantity += item.quantity;
        } else {
            cart.push(item);
        }

        localStorage.setItem('cart', JSON.stringify(cart));
        alert(`${item.name} has been added to your cart!`);
    });
});

function buyNow() {
    document.querySelector('.add-to-cart-btn').click(); 
    window.location.href = 'cart.php';
}
</script>
</body>
</html>