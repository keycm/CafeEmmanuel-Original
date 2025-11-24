const cartBtn = document.getElementById('cartBtn');
const cartSidebar = document.getElementById('cartSidebar');
const closeCart = document.getElementById('closeCart');
const cartItemsContainer = document.getElementById('cartItems');
const cartTotal = document.getElementById('cartTotal');
const addButtons = document.querySelectorAll('.add');
const addToCartBtn = document.querySelector('.add-to-cart');
const clearCartBtn = document.getElementById('clearCart');

let cart = JSON.parse(localStorage.getItem('cart')) || [];
updateCartDisplay();

// Open Cart Sidebar
cartBtn.addEventListener('click', () => {
  cartSidebar.classList.add('open');
  updateCartDisplay();
});

// Close Cart Sidebar
closeCart.addEventListener('click', () => {
  cartSidebar.classList.remove('open');
});




addToCartBtn.addEventListener('click', () => {
  const productName = document.querySelector('.product-details h1').textContent;
  const price = parseFloat(document.querySelector('.price').textContent.replace('₱', '').replace(',', ''));
  const quantity = parseInt(document.querySelector('.quantity-selector input').value);
  const selectedSize = document.querySelector('.sizes button.active');
  const selectedColor = document.querySelector('.color.active');
  const imageSrc = document.querySelector('.product-image img').src;

  if (!selectedSize || !selectedColor) {
    alert('Please select size and color');
    return;
  }

  const item = {
    name: productName,
    price: price,
    quantity: quantity,
    size: selectedSize.textContent,
    color: selectedColor.classList.contains('blue') ? 'Blue' : 'Black',
    image: imageSrc
  };

  const existingIndex = cart.findIndex(c => c.name === item.name && c.size === item.size && c.color === item.color);
  if (existingIndex > -1) {
    cart[existingIndex].quantity += item.quantity;
  } else {
    cart.push(item);
  }

  localStorage.setItem('cart', JSON.stringify(cart));
  updateCartDisplay();
});

function updateCartDisplay() {
  cartItemsContainer.innerHTML = '';
  let total = 0;
  cart.forEach((item, index) => {
    total += item.price * item.quantity;

    const cartItem = document.createElement('div');
    cartItem.classList.add('cart-item');
    cartItem.innerHTML = `
      <img src="${item.image}" alt="${item.name}">
      <div class="text">
        <p><strong>${item.name}</strong></p>
        <p>₱${item.price.toFixed(2)} x ${item.quantity}</p>
        <p>Size: ${item.size} | Color: ${item.color}</p>
        <div>
          <button onclick="changeQuantity(${index}, -1)">-</button>
          <span>${item.quantity}</span>
          <button onclick="changeQuantity(${index}, 1)">+</button>
        </div>
      </div>
    `;
    cartItemsContainer.appendChild(cartItem);
  });
  cartTotal.textContent = total.toFixed(2);
}

function changeQuantity(index, change) {
  if (cart[index].quantity + change <= 0) {
    cart.splice(index, 1);
  } else {
    cart[index].quantity += change;
  }
  localStorage.setItem('cart', JSON.stringify(cart));
  updateCartDisplay();
}

function removeItem(index) {
  cart.splice(index, 1);
  localStorage.setItem('cart', JSON.stringify(cart));
  updateCartDisplay();
}

clearCartBtn.addEventListener('click', () => {
  cart = [];
  localStorage.removeItem('cart');
  updateCartDisplay();
});

// Size & Color Selection (Make them selectable)
document.querySelectorAll('.sizes button').forEach(button => {
  button.addEventListener('click', () => {
    document.querySelectorAll('.sizes button').forEach(btn => btn.classList.remove('active'));
    button.classList.add('active');
  });
});

document.querySelectorAll('.color').forEach(color => {
  color.addEventListener('click', () => {
    document.querySelectorAll('.color').forEach(clr => clr.classList.remove('active'));
    color.classList.add('active');
  });
});


const product = JSON.parse(localStorage.getItem('selectedProduct'));

if (product) {
  document.querySelector('.product-details h1').textContent = product.name;
  document.querySelector('.price').textContent = `₱${product.price.toLocaleString()}`;
  document.querySelector('.product-image img').src = product.image;

  // Display rating stars dynamically
  const stars = '★'.repeat(product.rating) + '☆'.repeat(5 - product.rating);
  document.querySelector('.rating').textContent = stars;
} else {
  // If no product in storage, redirect back to products page
  window.location.href = 'product.php';
}



function goTocart() {
  const productName = document.querySelector('.product-details h1').textContent;
  const price = parseFloat(document.querySelector('.price').textContent.replace('₱', '').replace(',', ''));
  const quantity = parseInt(document.querySelector('.quantity-selector input').value);
  const selectedSize = document.querySelector('.sizes button.active');
  const selectedColor = document.querySelector('.color.active');
  const imageSrc = document.querySelector('.product-image img').src;

  if (!selectedSize || !selectedColor) {
    alert('Please select size and color before buying');
    return;
  }

  const item = {
    name: productName,
    price: price,
    quantity: quantity,
    size: selectedSize.textContent,
    color: selectedColor.classList.contains('blue') ? 'Blue' : 'Black',
    image: imageSrc
  };

  let cart = JSON.parse(localStorage.getItem('cart')) || [];
  const existingIndex = cart.findIndex(c => c.name === item.name && c.size === item.size && c.color === item.color);
  if (existingIndex > -1) {
    cart[existingIndex].quantity += item.quantity;
  } else {
    cart.push(item);
  }

  localStorage.setItem('cart', JSON.stringify(cart));
  window.location.href = "cart.php";
}

function changeMainImage(element) {
  document.querySelector('.product-image img').src = element.src;
}

