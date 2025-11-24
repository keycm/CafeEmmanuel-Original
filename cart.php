<?php 
include 'user_session_check.php'; 
// We get the user's fullname from the session to pre-fill the form
$fullname = isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - Cafe Emmanuel</title>
    
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
            --font-logo-cafe: 'Archivo Black', sans-serif;
            --font-logo-emmanuel: 'Birthstone Bounce', cursive;
            --font-nav: 'Inknut Antiqua', serif;
            --font-section-heading: 'Playfair Display', serif;
            --font-body-default: 'Lato', sans-serif;
            --nav-height: 90px;
        }

        /* --- Base & Reset --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-body-default); color: var(--text-color); background-color: #fcfbf8; line-height: 1.7; }
        .container { max-width: 1140px; margin: 0 auto; padding: 0 20px; }
        .btn { padding: 12px 28px; border-radius: 8px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-block; }

        /* --- Header & Navigation --- */
        .header-page { background: rgba(26, 18, 11, 0.85); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); position: sticky; top: 0; z-index: 1000; height: var(--nav-height); }
        .navbar { display: flex; justify-content: space-between; align-items: center; height: 100%; }
        .nav-logo { text-decoration: none; color: var(--white); display: flex; align-items: center; }
        .logo-cafe { font-family: var(--font-logo-cafe); font-size: 40px; }
        .logo-emmanuel { font-family: var(--font-logo-emmanuel); font-size: 40px; font-weight: 500; margin-left: 10px; }
        .first-letter { color: #932432; }

        /* --- Cart Page Styles --- */
        .order-section { padding: 4rem 0 6rem; }
        .order-container { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: flex-start; }
        .basket-items, .order-summary { background: var(--white); padding: 2rem; border-radius: 12px; border: 1px solid var(--border-color); }
        
        /* --- THIS IS THE FIX --- */
        .basket-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
        .basket-header h2 { font-family: var(--font-section-heading); margin: 0; }
        .continue-shopping-link { font-family: var(--font-body-default); font-weight: 600; color: var(--primary-color); text-decoration: none; font-size: 0.9rem; }
        .continue-shopping-link:hover { text-decoration: underline; }
        .order-summary h2 { font-family: var(--font-section-heading); margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
        /* --- END OF FIX --- */

        .basket-item { display: flex; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .basket-item:last-child { border-bottom: none; margin-bottom: 0; }
        .basket-item img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; margin-right: 1.5rem; }
        .item-details { flex-grow: 1; }
        .item-details h3 { font-family: var(--font-section-heading); font-size: 1.2rem; margin-bottom: 0.25rem; }
        .item-details .item-meta { font-size: 0.9rem; color: #777; }
        .quantity-controls { display: flex; align-items: center; gap: 0.75rem; margin-top: 0.5rem; }
        .quantity-btn { background-color: #f0f0f0; border: 1px solid #ddd; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-weight: bold; }
        .item-price-actions { text-align: right; }
        .item-price { font-weight: bold; font-size: 1.1rem; margin-bottom: 1rem; }
        .remove-btn { background: none; border: none; font-size: 1rem; color: #aaa; cursor: pointer; transition: color 0.2s; }
        .remove-btn:hover { color: var(--primary-color); }
        #emptyCartView { text-align: center; padding: 3rem 0; }
        #emptyCartView i { font-size: 3rem; color: #ddd; margin-bottom: 1rem; }
        #emptyCartView h3 { margin-bottom: 1rem; }
        .order-summary { position: sticky; top: calc(var(--nav-height) + 2rem); }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 1rem; }
        .summary-row.total { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); font-weight: bold; font-size: 1.2rem; }
        .checkout-btn { width: 100%; margin-top: 1rem; background-color: var(--primary-color); color: var(--white); border: none; }
        .checkout-btn:hover { background-color: #a14436; }
        .checkout-btn:disabled { background-color: #ccc; cursor: not-allowed; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
        .modal-content { background-color: #fefefe; margin: auto; padding: 2.5rem; border-radius: 12px; width: 90%; max-width: 450px; position: relative; box-shadow: 0 5px 20px rgba(0,0,0,0.2); }
        .close-modal-btn { color: #aaa; position: absolute; top: 15px; right: 25px; font-size: 28px; font-weight: bold; cursor: pointer; transition: color 0.2s; }
        .close-modal-btn:hover { color: #333; }
        .modal-content h2 { font-family: var(--font-section-heading); margin-bottom: 1rem; text-align: center; }
        .delivery-note { background-color: #fdf5f5; color: #c0392b; padding: 0.75rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 1rem; text-align: left; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 0.5rem; font-size: 0.9rem; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 1rem; font-family: 'Lato', sans-serif; transition: border-color 0.3s, box-shadow 0.3s; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(185, 90, 75, 0.1); }
        .payment-options { display: flex; gap: 1.5rem; margin-top: 1.5rem; align-items: center; }
        .payment-options label { display: flex; align-items: center; gap: 0.5rem; }
        .submit-order-btn { width: 100%; margin-top: 1.5rem; background-color: var(--secondary-color); color: var(--white); border: none; }
        .submit-order-btn:hover { background-color: #2a1e17; }

        @media (max-width: 992px) { .order-container { grid-template-columns: 1fr; } .order-summary { position: static; } }
    </style>
</head>
<body>
    <header class="header header-page">
        <nav class="navbar container">
            <a href="index.php" class="nav-logo">
                <span class="logo-cafe"><span class="first-letter">C</span>afe</span>
                <span class="logo-emmanuel"><span class="first-letter">E</span>mmanuel</span>
            </a>
        </nav>
    </header>

    <main>
        <section class="order-section">
            <div class="container order-container">
                <div class="basket-items">
                    <div class="basket-header">
                        <h2>Your Cart</h2>
                        <a href="product.php" class="continue-shopping-link">&larr; Continue Shopping</a>
                    </div>
                    <div id="cartItemsContainer"></div>
                    <div id="emptyCartView" style="display: none;">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your Cart is Empty</h3>
                        <p>Looks like you haven't added anything yet.</p>
                        <a href="product.php" class="btn checkout-btn" style="margin-top: 1.5rem; display: inline-block; width: auto;">Start Shopping</a>
                    </div>
                </div>

                <div class="order-summary" id="orderSummaryContainer" style="display: none;">
                    <h2>Order Summary</h2>
                    <div id="summaryInfo"></div>
                    <div id="totalSection" class="summary-row total"></div>
                    <button class="btn checkout-btn" id="checkoutBtn">Proceed to Checkout</button>
                </div>
            </div>
        </section>
    </main>

  <div class="modal" id="deliveryModal">
    <div class="modal-content">
      <span onclick="closeModal()" class="close-modal-btn">&times;</span>
      <h2>Delivery Information</h2>
      <p class="delivery-note">Note: Delivery is available for Pampanga only.</p>
      <form id="deliveryForm">
        <div class="form-group">
            <label for="fullname">Full Name</label>
            <input type="text" name="fullname" id="fullname" value="<?php echo $fullname; ?>" required />
        </div>
        <div class="form-group">
            <label for="contact">Contact Number</label>
            <input type="tel" name="contact" id="contact" required />
        </div>
        <div class="form-group">
            <label for="address">Address</label>
            <textarea name="address" id="address" rows="3" required></textarea>
        </div>
        <div class="form-group">
            <label>Payment Method</label>
            <div class="payment-options">
                <label><input type="radio" name="payment" value="COD" checked> Cash On Delivery</label>
                <label><input type="radio" name="payment" value="GCash"> GCash</label>
            </div>
        </div>
        <button type="submit" class="btn submit-order-btn">Confirm Order</button>
      </form>
    </div>
  </div>

<script>
let cartItems = JSON.parse(localStorage.getItem("cart") || "[]");
const cartContainer = document.getElementById("cartItemsContainer");
const summaryInfo = document.getElementById("summaryInfo");
const totalSection = document.getElementById("totalSection");
const checkoutBtn = document.getElementById("checkoutBtn");
const emptyCartView = document.getElementById("emptyCartView");
const orderSummaryContainer = document.getElementById("orderSummaryContainer");

function closeModal() {
  document.getElementById("deliveryModal").style.display = "none";
}
function updateLocalStorage() {
  localStorage.setItem("cart", JSON.stringify(cartItems));
}

function showCart() {
  if (cartItems.length === 0) {
    cartContainer.style.display = 'none';
    orderSummaryContainer.style.display = 'none';
    emptyCartView.style.display = 'block';
    return 0; 
  }
  
  cartContainer.style.display = 'block';
  orderSummaryContainer.style.display = 'block';
  emptyCartView.style.display = 'none';

  let subtotal = 0;
  cartContainer.innerHTML = cartItems.map((item, index) => {
    const lineTotal = item.price * item.quantity;
    subtotal += lineTotal;
    return `
      <div class="basket-item">
        <img src="${item.image || 'assets/no-image.png'}" alt="${item.name}">
        <div class="item-details">
          <h3>${item.name}</h3>
          <div class="quantity-controls">
            <button class="quantity-btn" onclick="updateQuantity(${index}, -1)">-</button>
            <span>${item.quantity}</span>
            <button class="quantity-btn" onclick="updateQuantity(${index}, 1)">+</button>
          </div>
        </div>
        <div class="item-price-actions">
            <p class="item-price">₱${lineTotal.toLocaleString()}</p>
            <button class="remove-btn" onclick="removeItem(${index})"><i class="fas fa-trash-alt"></i></button>
        </div>
      </div>
    `;
  }).join("");

  const shipping = 50;
  const discount = 20;
  const total = subtotal + shipping - discount;

  summaryInfo.innerHTML = `
    <div class="summary-row">
        <span>Subtotal</span> <span>₱${subtotal.toLocaleString()}</span>
    </div>
    <div class="summary-row">
        <span>Shipping Fee</span> <span>₱${shipping.toLocaleString()}</span>
    </div>
    <div class="summary-row">
        <span>Discount</span> <span>-₱${discount.toLocaleString()}</span>
    </div>
  `;
  totalSection.innerHTML = `<span>Total</span> <span>₱${total.toLocaleString()}</span>`;
  checkoutBtn.disabled = false;
  
  return total;
}

function updateQuantity(index, change) {
  let newQty = cartItems[index].quantity + change;
  if (newQty < 1) { newQty = 1; } 
  else if (newQty > 10) { newQty = 10; alert("Maximum of 10 items allowed per product."); }
  cartItems[index].quantity = newQty;
  updateLocalStorage();
  finalTotal = showCart();
}

function removeItem(index) {
    if (confirm('Are you sure you want to remove this item from your cart?')) {
        cartItems.splice(index, 1);
        updateLocalStorage();
        finalTotal = showCart();
    }
}

let finalTotal = showCart();

checkoutBtn.addEventListener("click", () => {
  document.getElementById("deliveryModal").style.display = "flex";
});

document.getElementById("deliveryForm").addEventListener("submit", async function(e) {
  e.preventDefault();
  const data = {
      fullname: this.fullname.value,
      contact: this.contact.value,
      address: this.address.value,
      payment: this.payment.value,
      cart: cartItems,
      total: finalTotal
  };

  try {
    const res = await fetch("save_order.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data)
    });
    const result = await res.json();
    if (result.success) {
      localStorage.removeItem("cart");
      alert("Order placed successfully!");
      window.location.href = "product.php";
    } else {
      alert("Error placing order: " + result.error);
    }
  } catch (error) {
    console.error('Submission Error:', error);
    alert("Failed to send order to the server.");
  }
});

document.querySelector('input[name="payment"][value="GCash"]').addEventListener("change", function() {
  if (this.checked) {
    if (finalTotal <= 0) { 
      alert("Your cart is empty. Please add items before paying with GCash.");
      document.querySelector('input[name="payment"][value="COD"]').checked = true;
      return; 
    }
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "create_payment.php";
    const inputAmount = document.createElement("input");
    inputAmount.type = "hidden";
    inputAmount.name = "amount";
    inputAmount.value = finalTotal;
    form.appendChild(inputAmount);
    document.body.appendChild(form);
    form.submit();
  }
});
</script>
</body>
</html>