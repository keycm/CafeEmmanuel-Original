document.addEventListener("DOMContentLoaded", () => {
  const cartContainer = document.querySelector(".cart-items");
  const totalItemsElement = document.querySelector(".summary-info p:first-child span");
  const shippingFee = 50;
  const discount = 20;
  const totalElement = document.querySelector(".total-section p span");

  let cart = JSON.parse(localStorage.getItem("cart")) || [];

  const checkoutBtn = document.querySelector(".checkout-btn");
  const deliveryModal = document.getElementById("deliveryModal");
  const deliveryForm = document.getElementById("deliveryForm");

  // --- NEW: Ensure each cart item has an 'id' for stock deduction ---
  cart = cart.map(item => {
    if (!item.id) {
      // If your product object has a database ID, use that
      item.id = item.db_id || Math.floor(Math.random() * 100000); // fallback random id
    }
    return item;
  });
  localStorage.setItem("cart", JSON.stringify(cart));
  // --- END NEW ---

  function toggleCheckoutButton() {
    if (!cart || cart.length === 0) {
      checkoutBtn.disabled = true;
      checkoutBtn.style.opacity = "0.5";
      checkoutBtn.style.cursor = "not-allowed";
    } else {
      checkoutBtn.disabled = false;
      checkoutBtn.style.opacity = "1";
      checkoutBtn.style.cursor = "pointer";
    }
  }

  function renderCart() {
    cartContainer.innerHTML = ""; // Clear existing cart

    let itemsTotal = 0;

    if (cart.length === 0) {
      cartContainer.innerHTML = "<p>No items in cart.</p>";
      totalItemsElement.textContent = "₱0";
      totalElement.textContent = "₱0";
      toggleCheckoutButton();
      return;
    }

    cart.forEach((item, index) => {
      const lineTotal = item.price * item.quantity;
      itemsTotal += lineTotal;

      const cartCard = document.createElement("div");
      cartCard.classList.add("cart-card");
      cartCard.innerHTML = `
        <img src="${item.image || 'assets/no-image.png'}" alt="${item.name}">
        <div class="cart-details">
          <p class="product-name">${item.name} (${item.color || ''} ${item.size || ''})</p>
          <p class="line-total">${item.quantity} × ₱${item.price.toLocaleString()} = <strong>₱${lineTotal.toLocaleString()}</strong></p>
          <div class="qty-controls">
            <button onclick="updateQuantity(${index}, -1)">-</button>
            <span>${item.quantity}</span>
            <button onclick="updateQuantity(${index}, 1)">+</button>
          </div>
        </div>
      `;
      cartContainer.appendChild(cartCard);
    });

    totalItemsElement.textContent = `₱${itemsTotal.toLocaleString()}`;
    const finalTotal = itemsTotal + shippingFee - discount;
    totalElement.textContent = `₱${finalTotal.toLocaleString()}`;

    // Update button state
    toggleCheckoutButton();
  }

  window.updateQuantity = function (index, change) {
    cart[index].quantity += change;
    if (cart[index].quantity <= 0) {
      cart.splice(index, 1);
    }
    localStorage.setItem("cart", JSON.stringify(cart));
    renderCart();
  };

  renderCart(); // initial call

  // Checkout button click event
  checkoutBtn.addEventListener("click", () => {
    if (!cart || cart.length === 0) {
      alert("Your cart is empty. Please add items before proceeding to checkout.");
      return;
    }
    deliveryModal.style.display = "block";
  });

  window.closeModal = function () {
    deliveryModal.style.display = "none";
  };

  deliveryForm.addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(deliveryForm);
    const deliveryInfo = {
      fullname: formData.get("fullname"),
      contact: formData.get("contact"),
      address: formData.get("address"),
      payment: formData.get("payment"),
      cart: cart // <-- Send cart with IDs to save_order.php
    };

    console.log("Order Confirmed:", deliveryInfo);

    // --- Send to PHP for stock deduction ---
    fetch("save_order.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(deliveryInfo)
    })
      .then(res => res.json())
      .then(result => {
        if (result.success) {
          alert("Thank you! Your order has been placed.");
          localStorage.removeItem("cart");
          window.location.href = "product.php";
        } else {
          alert("Error: " + result.error);
        }
      })
      .catch(err => {
        console.error(err);
        alert("Failed to place order.");
      });
    // --- END PHP call ---
  });
});