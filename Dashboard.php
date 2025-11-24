<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'session_check.php';

// --- Database Connection ---
include 'db_connect.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Dynamic Metrics ---
$total_orders_result = $conn->query("SELECT COUNT(*) as count FROM cart");
$total_orders = $total_orders_result->fetch_assoc()['count'];

$total_delivered_result = $conn->query("SELECT COUNT(*) as count FROM cart WHERE status = 'Delivered'");
$total_delivered = $total_delivered_result->fetch_assoc()['count'];

$total_revenue_result = $conn->query("SELECT SUM(total) as sum FROM cart WHERE status = 'Delivered'");
$total_revenue = $total_revenue_result->fetch_assoc()['sum'];

// SAFETY FIX: If there is no revenue (NULL), make it 0 to prevent errors
if ($total_revenue === null) {
    $total_revenue = 0;
}

$total_canceled_result = $conn->query("SELECT COUNT(*) as count FROM cart WHERE status = 'Cancelled'");
$total_canceled = $total_canceled_result->fetch_assoc()['count'];

// --- Fetch All Orders ---
$all_orders_result = $conn->query("SELECT * FROM cart ORDER BY id DESC");
$all_orders = [];
while ($row = $all_orders_result->fetch_assoc()) {
    $all_orders[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard</title>
  <link rel="stylesheet" href="CSS/admin.css"/> 
  <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    :root {
        --primary-color: #556ee6;
        --background-color: #f8f8fb;
        --card-background: #ffffff;
        --text-color: #343a40;
        --subtle-text: #74788d;
        --border-color: #eff2f7;
        --green-accent: #34c38f;
        --red-accent: #f46a6a;
        --yellow-accent: #f1b44c;
    }
    .main-content { background-color: var(--background-color); padding: 25px; }
    .main-header { padding: 0; margin-bottom: 25px; border: none; }
    .main-header h1 { font-size: 1.5rem; font-weight: 600; color: var(--text-color); }
    .header-icons { display: flex; align-items: center; gap: 1.5rem; }
    .header-icons i { font-size: 1.2rem; color: var(--subtle-text); cursor: pointer; }
    .header-icons img { width: 40px; height: 40px; border-radius: 50%; cursor: pointer; }

    /* Metrics Cards */
    .dashboard-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .metric { background: var(--card-background); padding: 25px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .metric-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .metric-header p { margin: 0; color: var(--subtle-text); font-weight: 500; }
    .metric-header .icon-wrapper { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .metric-header .icon-orders { background-color: #e8f0fe; color: #556ee6; }
    .metric-header .icon-delivered { background-color: #eaf7f3; color: var(--green-accent); }
    .metric-header .icon-revenue { background-color: #fef8ec; color: var(--yellow-accent); }
    .metric-header .icon-canceled { background-color: #fdeeee; color: var(--red-accent); }
    .metric-body h3 { font-size: 1.8rem; margin: 0; color: var(--text-color); }
    .metric-body span { font-size: 0.9rem; }
    .metric-body .increase { color: var(--green-accent); }
    .metric-body .decrease { color: var(--red-accent); }
    
    /* Main Content Layout */
    .dashboard-main-content { display: grid; grid-template-columns: 3fr 2fr; gap: 25px; align-items: flex-start; }
    
    /* All Orders Column */
    .all-orders-card { background: var(--card-background); border-radius: 8px; padding: 25px; }
    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .card-header h2 { font-size: 1.1rem; font-weight: 600; margin: 0; }
    .filters-btn { background: #f8f8fb; border: 1px solid var(--border-color); padding: 6px 12px; border-radius: 6px; cursor: pointer; }
    .search-bar { position: relative; margin-bottom: 20px; }
    .search-bar i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--subtle-text); }
    .search-bar input { width: 75%; padding: 10px 15px 10px 40px; border-radius: 6px; border: 1px solid var(--border-color); }
    .orders-list { max-height: 500px; overflow-y: auto; padding-right: 10px; }
    .order-card { border: 1px solid var(--border-color); padding: 15px; border-radius: 8px; margin-bottom: 15px; }
    .order-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .order-id { font-weight: 600; }
    .status-tag { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 500; }
    .status-pending { background-color: #fef8ec; color: var(--yellow-accent); }
    .status-completed { background-color: #eaf7f3; color: var(--green-accent); }
    .status-cancelled { background-color: #fdeeee; color: var(--red-accent); }
    /* Added processing status style */
    .status-processing { background-color: #e8f0fe; color: var(--primary-color); } 
    .customer-name { font-weight: 600; font-size: 1.1rem; }
    .order-time { font-size: 0.9rem; color: var(--subtle-text); }
    .order-items { font-size: 0.9rem; margin-top: 10px; }
    .order-footer { display: flex; align-items: center; margin-top: 15px; gap: 10px; color: var(--subtle-text); }
    .order-footer i { color: var(--primary-color); }
    
    /* Order Details Column */
    .order-details-card { background: var(--card-background); border-radius: 8px; padding: 25px; position: sticky; top: 20px; }
    .bill-details { margin-top: 20px; }
    .bill-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 0.9rem; }
    .total-bill { border-top: 1px solid var(--border-color); margin-top: 10px; padding-top: 10px; font-weight: 600; font-size: 1rem; }

    @media (max-width: 1200px) { .dashboard-main-content { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="admin-container">
    <?php include 'admin_sidebar.php'; ?>
    <main class="main-content">
      <header class="main-header">
        <h1>Dashboard</h1>
        <div class="header-icons">
            <i class="fas fa-envelope"></i><i class="fas fa-bell"></i>
            <img src="logo.png" alt="Admin Profile">
        </div>
      </header>

      <section class="dashboard-metrics">
        <!-- SAFETY FIX: Added (float) casting to number_format calls -->
        <div class="metric"><div class="metric-header"><p>Total Orders</p><div class="icon-wrapper icon-orders"><i class="fas fa-box"></i></div></div><div class="metric-body"><h3><?php echo number_format((float)$total_orders); ?></h3></div></div>
        <div class="metric"><div class="metric-header"><p>Total Delivered</p><div class="icon-wrapper icon-delivered"><i class="fas fa-truck-fast"></i></div></div><div class="metric-body"><h3><?php echo number_format((float)$total_delivered); ?></h3></div></div>
        <div class="metric"><div class="metric-header"><p>Total Revenue</p><div class="icon-wrapper icon-revenue"><i class="fas fa-peso-sign"></i></div></div><div class="metric-body"><h3><?php echo number_format((float)$total_revenue, 2); ?></h3></div></div>
        <div class="metric"><div class="metric-header"><p>Total Canceled</p><div class="icon-wrapper icon-canceled"><i class="fas fa-ban"></i></div></div><div class="metric-body"><h3><?php echo number_format((float)$total_canceled); ?></h3></div></div>
      </section>  

      <div class="dashboard-main-content">
        <div class="all-orders-card">
            <div class="card-header">
                <h2>All Orders</h2>
                <button class="filters-btn"><i class="fas fa-filter"></i> Filters</button>
            </div>
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search Order ID...">
            </div>
            <div class="orders-list">
                <?php foreach ($all_orders as $order): 
                    $cart_items = json_decode($order['cart'], true);
                    $status_class = strtolower($order['status']);
                    $order_json = htmlspecialchars(json_encode($order), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="order-card" data-order="<?php echo $order_json; ?>">
                    <div class="order-card-header">
                        <span class="order-id">Order ID #<?php echo $order['id']; ?></span>
                        <span class="status-tag status-<?php echo $status_class; ?>"><?php echo htmlspecialchars($order['status']); ?></span>
                    </div>
                    <p class="customer-name"><?php echo htmlspecialchars($order['fullname']); ?></p>
                    <p class="order-time"><?php echo date("h:iA | d M Y", strtotime($order['created_at'])); ?></p>
                    <div class="order-items">
                        <?php 
                            if ($cart_items && is_array($cart_items)) {
                                $item_names = [];
                                foreach($cart_items as $item) {
                                    $item_names[] = ($item['quantity'] ?? 1) . ' x ' . ($item['name'] ?? 'N/A');
                                }
                                echo implode('<br>', $item_names);
                            }
                        ?>
                    </div>
                    <div class="order-footer">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($order['address']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="order-details-card" id="order-details-container">
            <div class="card-header">
                <h2>Order Details</h2>
            </div>
            <div id="order-details-content" style="display: none;">
                <p class="customer-name" id="details-customer-name" style="font-size: 1.2rem; margin-bottom: 5px;">CUSTOMER</p>
                <p class="order-time" id="details-contact" style="font-size: 1rem; margin-bottom: 10px;">CONTACT</p>
                <div class="order-footer" style="margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid var(--border-color);">
                    <i class="fas fa-map-marker-alt"></i>
                    <span id="details-address">DELIVERY ADDRESS</span>
                </div>
                
                <div class="order-card-header" style="margin-top: 15px; margin-bottom: 0;">
                    <span class="order-id" id="details-order-id" style="font-size: 1rem;">ORDER STATUS</span>
                    <span class="status-tag" id="details-status"></span>
                </div>
                
                <div class="bill-details" style="margin-top: 20px;">
                    <div class="card-header" style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color);">
                        <h2 style="font-size: 1.1rem;">Bill Details</h2>
                    </div>
                    <div id="details-items-list">
                        <!-- Items will be injected here -->
                    </div>
                    <div class="bill-row" style="margin-top: 10px;">
                        <span>Handling Charge</span>
                        <span>₱5.00</span>
                    </div>
                    <div class="bill-row">
                        <span>Delivery Fee</span>
                        <span>₱25.00</span>
                    </div>
                    <div class="bill-row total-bill">
                        <span>Total Bill</span>
                        <span id="details-total-bill">₱0.00</span>
                    </div>
                </div>
            </div>
            <div id="order-details-placeholder" style="display: block;">
                <i class="fas fa-receipt"></i>
                <p>No order selected</p>
            </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ordersListContainer = document.querySelector('.orders-list');
        const detailsContainer = document.getElementById('order-details-container');
        const detailsContent = document.getElementById('order-details-content');
        const detailsPlaceholder = document.getElementById('order-details-placeholder');

        // --- Select the first order by default on load ---
        const firstOrderCard = document.querySelector('.order-card');
        if (firstOrderCard) {
            // Show content, hide placeholder
            detailsContent.style.display = 'block';
            detailsPlaceholder.style.display = 'none';
            // Simulate a click to load its data
            updateOrderDetails(firstOrderCard);
            firstOrderCard.classList.add('active'); // Add active class to highlight
        } else {
            // No orders found, show placeholder
            detailsContent.style.display = 'none';
            detailsPlaceholder.style.display = 'block';
        }

        ordersListContainer.addEventListener('click', function(e) {
            const orderCard = e.target.closest('.order-card');
            if (orderCard) {
                // Remove active class from previously selected
                const currentActive = document.querySelector('.order-card.active');
                if (currentActive) {
                    currentActive.classList.remove('active');
                }
                
                // Add active class to clicked
                orderCard.classList.add('active');
                
                // Show content, hide placeholder
                detailsContent.style.display = 'block';
                detailsPlaceholder.style.display = 'none';
                
                updateOrderDetails(orderCard);
            }
        });

        function updateOrderDetails(orderCard) {
            try {
                const orderData = JSON.parse(orderCard.dataset.order);
                const cartItems = JSON.parse(orderData.cart || '[]');
                
                // --- Populate Details ---
                document.getElementById('details-customer-name').textContent = orderData.fullname || 'N/A';
                document.getElementById('details-contact').textContent = orderData.contact || 'N/A';
                document.getElementById('details-address').textContent = orderData.address || 'N/A';
                
                document.getElementById('details-order-id').textContent = `Order ID #${orderData.id}`;
                
                const statusTag = document.getElementById('details-status');
                const status = (orderData.status || 'pending').toLowerCase();
                statusTag.textContent = orderData.status || 'Pending';
                statusTag.className = 'status-tag status-' + status; // Resets classes and adds new one
                
                // --- Populate Bill ---
                const itemsListDiv = document.getElementById('details-items-list');
                itemsListDiv.innerHTML = ''; // Clear previous items
                
                let subtotal = 0;
                if (Array.isArray(cartItems)) {
                    cartItems.forEach(item => {
                        const itemName = item.name || 'Unknown Item';
                        const quantity = parseInt(item.quantity || 0);
                        const price = parseFloat(item.price || 0);
                        const itemTotal = quantity * price;
                        subtotal += itemTotal;
                        
                        const itemRow = document.createElement('div');
                        itemRow.className = 'bill-row';
                        itemRow.innerHTML = `
                            <span>${quantity} x ${escapeHTML(itemName)}</span>
                            <span>₱${itemTotal.toFixed(2)}</span>
                        `;
                        itemsListDiv.appendChild(itemRow);
                    });
                }
                
                // Add a subtotal row
                const subtotalRow = document.createElement('div');
                subtotalRow.className = 'bill-row';
                subtotalRow.innerHTML = `
                    <span>Subtotal</span>
                    <span>₱${subtotal.toFixed(2)}</span>
                `;
                itemsListDiv.prepend(subtotalRow);

                // --- Calculate Total ---
                // We use the 'total' from the order data, as it's the authoritative source
                const totalBill = parseFloat(orderData.total || 0);
                
                // Update total
                document.getElementById('details-total-bill').textContent = `₱${totalBill.toFixed(2)}`;
                
            } catch (error) {
                console.error('Error parsing order data:', error);
                detailsContent.style.display = 'none';
                detailsPlaceholder.style.display = 'block';
                detailsPlaceholder.innerHTML = '<i class="fas fa-exclamation-triangle"></i><p>Error loading order details.</p>';
            }
        }
        
        function escapeHTML(str) {
            if (typeof str !== 'string') return '';
            return str.replace(/[&<>"']/g, function(m) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[m];
            });
        }
        
        // Add some style for the active card and placeholder
        const style = document.createElement('style');
        style.innerHTML = `
            .order-card.active {
                border-color: var(--primary-color);
                box-shadow: 0 0 10px rgba(85, 110, 230, 0.3);
                background-color: #f8faff;
            }
            .order-card {
                cursor: pointer;
                transition: border-color 0.3s, box-shadow 0.3s, background-color 0.3s;
            }
            #order-details-placeholder {
                text-align: center; 
                padding: 80px 20px; 
                color: var(--subtle-text);
            }
            #order-details-placeholder i {
                font-size: 3rem; 
                margin-bottom: 1rem; 
                opacity: 0.4;
            }
        `;
        document.head.appendChild(style);
    });
  </script>
</body>
</html>