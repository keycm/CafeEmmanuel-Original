<?php
// my_orders.php
session_start();

// 1. Enable Error Reporting (Temporarily, to see errors instead of white screen)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Check Login
if (!isset($_SESSION['user_id'])) { 
    header('Location: index.php?action=login'); 
    exit; 
}

$fullname = $_SESSION['fullname'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

// 3. Database Connection (USE THE SHARED FILE)
// Do not create a new 'new mysqli' connection here manually. Use the one from db_connect.php
include 'db_connect.php'; 

// Check if connection worked
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please check db_connect.php");
}

// 4. Fetch Orders
// Prefer user_id match; fall back to legacy rows that only have fullname (cart-based orders)
// We use $conn instead of $menu because that is the variable name in db_connect.php
$stmt = $conn->prepare("
    SELECT id, fullname, contact, address, total, status, created_at, cart, cancel_reason 
    FROM cart 
    WHERE (user_id = ? OR (user_id IS NULL AND fullname = ?)) 
    ORDER BY id DESC
");

if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

$stmt->bind_param('is', $userId, $fullname);
$stmt->execute();
$orders = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Orders</title>
  <style>
    body { font-family: system-ui, Arial; background:#fafafa; }
    .container{ max-width: 900px; margin: 30px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.06); }
    .back-button { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; background: transparent; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; color: #333; font-weight: 500; margin-bottom: 20px; transition: background 0.2s; }
    .back-button:hover { background: #f5f5f5; }
    .back-button svg { width: 20px; height: 20px; }
    table { width:100%; border-collapse: collapse; }
    th, td { padding:12px; border-bottom:1px solid #eee; text-align:left; }
    .pill{ padding:4px 10px; border-radius:16px; font-size:.85rem; }
    .pending{ background:#fff6e5; color:#a36a00; }
    .confirmed{ background:#e7f1ff; color:#2851a3; }
    .processing{ background:#e7f1ff; color:#2851a3; }
    .out_for_delivery{ background:#f1f5ff; color:#2851a3; }
    .delivered{ background:#e8f7ef; color:#1b7f4c; }
    .completed{ background:#e8f7ef; color:#1b7f4c; }
    .cancelled{ background:#fdeaea; color:#b42318; }
    .btn{ padding:8px 12px; border:none; border-radius:6px; cursor:pointer; }
    .btn-cancel{ background:#111; color:#fff; }
    .msg{ margin-bottom:10px; }
    
    /* Responsive Table */
    @media (max-width: 600px) {
        th, td { padding: 8px; font-size: 0.9rem; }
        .container { padding: 10px; width: 95%; }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="index.php" class="back-button">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Back
    </a>
    <h2>My Orders</h2>
    <?php if (isset($_GET['msg']) && $_GET['msg']==='cancelled'): ?><p class="msg" style="color:green">Order cancelled.</p><?php endif; ?>
    <?php if (isset($_GET['err'])): ?><p class="msg" style="color:#b00020">Action failed.</p><?php endif; ?>
    <table>
      <thead>
        <tr><th>Order #</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php while ($o = $orders->fetch_assoc()): ?>
          <tr>
            <td>#<?php echo (int)$o['id']; ?></td>
            <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($o['created_at']))); ?></td>
            <td>
              <?php 
                // Safety check for JSON decoding
                $items = json_decode($o['cart'] ?? '[]', true);
                if (!is_array($items)) $items = []; // Ensure array

                $names = array_map(function($it){ return htmlspecialchars($it['name'] ?? 'Unknown'); }, array_slice($items,0,3)); 
                echo implode(', ', $names); 
                if (count($items)>3) echo '…'; 
              ?>
            </td>
            <td>₱<?php echo number_format((float)$o['total'], 2); ?></td>
            <td>
              <?php $s = strtolower(str_replace(' ', '_', $o['status'])); ?>
              <span class="pill <?php echo $s; ?>"><?php echo htmlspecialchars($o['status']); ?></span>
            </td>
            <td>
              <?php 
                 // Prepare JSON for the modal safely
                 $orderData = [
                     'id' => $o['id'],
                     'fullname' => $o['fullname'],
                     'contact' => $o['contact'],
                     'address' => $o['address'],
                     'created_at' => $o['created_at'],
                     'total' => $o['total'],
                     'status' => $o['status'],
                     'cart' => $o['cart'],
                     'cancel_reason' => $o['cancel_reason'] ?? null
                 ];
                 $orderJson = htmlspecialchars(json_encode($orderData), ENT_QUOTES, 'UTF-8'); 
              ?>
              <button class="btn" onclick="openDetails(this)" data-order='<?php echo $orderJson; ?>'>View Details</button>
              <?php if (strtolower($o['status'])==='pending'): ?>
                <button class="btn btn-cancel" onclick="openCancelModal(<?php echo (int)$o['id']; ?>)">Cancel</button>
              <?php elseif (strtolower($o['status'])==='out for delivery' || strtolower($o['status'])==='out_for_delivery'): ?>
                <!-- Customer confirms they received the order -->
                <form method="POST" action="update_order.php" style="display:inline-block;" onsubmit="return confirm('Confirm that you received this order?');">
                  <input type="hidden" name="id" value="<?php echo (int)$o['id']; ?>" />
                  <input type="hidden" name="action" value="completed" />
                  <button type="submit" class="btn" style="background:#1b7f4c; color:#fff;">Order Receive</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($orders->num_rows === 0): ?>
          <tr><td colspan="6">No orders yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  
  <!-- Cancel Modal -->
  <div id="cancelModal" class="modal" style="display:none; position:fixed; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,.6); align-items:center; justify-content:center;">
    <div class="modal-content" style="background:#fff; padding:20px; border-radius:10px; width:90%; max-width:420px; position:relative;">
      <h3>Cancel Order</h3>
      <form method="POST" action="cancel_cart_order.php" onsubmit="return verifyCancel()">
        <input type="hidden" name="order_id" id="cancelOrderId" />
        <div class="form-group" style="margin:10px 0">
          <label>Reason</label>
          <select name="reason" required style="width:100%; padding:10px">
            <option value="">Select reason</option>
            <option>Ordered by mistake</option>
            <option>Wrong item selected</option>
            <option>Found a better price</option>
            <option>Change of mind</option>
            <option>Other</option>
          </select>
        </div>
        <div class="form-group" style="margin:10px 0">
          <label>Type "Cancel Order" to confirm</label>
          <input type="text" name="confirm_text" id="confirmText" placeholder="Cancel Order" required style="width:94%; padding:10px" />
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end">
          <button type="button" onclick="closeCancelModal()" class="btn">Close</button>
          <button type="submit" class="btn btn-cancel">Confirm Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Order Details Drawer/Modal -->
  <div id="detailsOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); opacity:0; transition:opacity 0.3s;">
    <div id="detailsDrawer" style="position:absolute; right:0; top:0; height:100%; width:420px; background:#fff; box-shadow:-4px 0 16px rgba(0,0,0,.15); padding:20px; overflow:auto; transform:translateX(100%); transition:transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
        <h3 style="margin:0">Order Details</h3>
        <button onclick="closeDetails()" class="btn">✕</button>
      </div>
      <div id="detailsMeta" style="font-size:.9rem; color:#555; margin-bottom:12px;"></div>

      <!-- Status timeline -->
      <div style="margin:10px 0 16px 0;">
        <div style="font-size:0.8rem; color:#888; margin-bottom:6px;">Order status</div>
        <div id="detailsTimeline" class="status-timeline"></div>
      </div>

      <table style="width:100%; border-collapse:collapse;">
        <thead><tr><th style="text-align:left; padding:6px 0; border-bottom:1px solid #eee;">Item</th><th style="text-align:right; padding:6px 0; border-bottom:1px solid #eee;">Qty</th><th style="text-align:right; padding:6px 0; border-bottom:1px solid #eee;">Price</th></tr></thead>
        <tbody id="detailsItems"></tbody>
        <tfoot>
          <tr><td colspan="3" id="detailsTotal" style="text-align:right; padding-top:10px; font-weight:bold;"></td></tr>
        </tfoot>
      </table>
      <div id="detailsCancelReason" style="margin-top:12px; color:#a00; display:none;"></div>
    </div>
  </div>

  <script>
    function openCancelModal(id){ document.getElementById('cancelOrderId').value = id; document.getElementById('cancelModal').style.display = 'flex'; }
    function closeCancelModal(){ document.getElementById('cancelModal').style.display = 'none'; }
    function verifyCancel(){ return document.getElementById('confirmText').value.trim().toLowerCase() === 'cancel order'; }

    let currentOrderData = null;

    function renderTimeline(container, status){
      const normalized = (status || '').toLowerCase();
      const steps = [
        { key: 'placed', label: 'Order Placed' },
        { key: 'pending', label: 'Pending' },
        { key: 'confirmed', label: 'Confirmed' },
        { key: 'processing', label: 'Processing' },
        { key: 'delivered', label: 'Delivered' },
      ];

      let currentIndex = 0;
      if (normalized === 'pending') currentIndex = 1;
      else if (normalized === 'confirmed') currentIndex = 2;
      else if (normalized === 'processing') currentIndex = 3;
      else if (normalized === 'out for delivery' || normalized === 'out_for_delivery') currentIndex = 3; // on the way
      else if (normalized === 'delivered') currentIndex = 4;
      else if (normalized === 'completed') currentIndex = 4; 

      let html = '';
      steps.forEach((step, idx) => {
        const done = idx <= currentIndex;
        html += `
          <div class="timeline-row">
            <div class="timeline-dot ${done ? 'done' : ''}">
              ${done ? '<span style="font-size:10px;">✓</span>' : ''}
            </div>
            <div class="timeline-text">
              <div class="timeline-label">${step.label}</div>
            </div>
          </div>`;
        if (idx < steps.length - 1) {
          html += '<div class="timeline-line"></div>';
        }
      });
      container.innerHTML = html;
    }

    function openDetails(btn){
      const data = JSON.parse(btn.getAttribute('data-order'));
      currentOrderData = data;
      const overlay = document.getElementById('detailsOverlay');
      const drawer = document.getElementById('detailsDrawer');
      
      let items = [];
      try {
          items = JSON.parse(data.cart || '[]');
      } catch (e) {
          items = [];
      }
      
      const tbody = document.getElementById('detailsItems');
      tbody.innerHTML = '';
      let total = 0;
      const timelineContainer = document.getElementById('detailsTimeline');
      renderTimeline(timelineContainer, data.status || 'pending');

      items.forEach(it => {
        const qty = Number(it.quantity||1);
        const price = Number(it.price||0);
        total += qty*price;
        const tr = document.createElement('tr');
        const name = (it.name || 'Item').replace(/</g,'&lt;');
        tr.innerHTML = `<td style="padding:6px 0; border-bottom:1px solid #f3f3f3;">${name}</td>
                        <td style="text-align:right; padding:6px 0; border-bottom:1px solid #f3f3f3;">${qty}</td>
                        <td style="text-align:right; padding:6px 0; border-bottom:1px solid #f3f3f3;">₱${price.toFixed(2)}</td>`;
        tbody.appendChild(tr);
      });
      
      document.getElementById('detailsTotal').textContent = 'Total: ₱' + Number(data.total||total).toFixed(2);
      document.getElementById('detailsMeta').textContent = `#${data.id} • ${new Date(data.created_at.replace(' ','T')).toLocaleString()} • ${data.status}`;
      
      const cr = document.getElementById('detailsCancelReason');
      if ((data.status||'').toLowerCase() === 'cancelled' && data.cancel_reason) { 
          cr.style.display='block'; 
          cr.textContent = 'Cancel reason: ' + data.cancel_reason; 
      } else { 
          cr.style.display='none'; 
          cr.textContent=''; 
      }
      
      overlay.style.display = 'block';
      setTimeout(()=>{ overlay.style.opacity='1'; drawer.style.transform='translateX(0)'; }, 10);
      overlay.onclick = (e)=>{ if(e.target===overlay) closeDetails(); };
    }

    function closeDetails(){
      const overlay = document.getElementById('detailsOverlay');
      const drawer = document.getElementById('detailsDrawer');
      overlay.style.opacity='0';
      drawer.style.transform='translateX(100%)';
      setTimeout(()=>{ overlay.style.display='none'; }, 300);
    }
  </script>
  <style>
    .status-timeline { margin-top: 4px; padding-left: 4px; }
    .timeline-row { display:flex; align-items:center; margin-bottom:6px; }
    .timeline-dot { width:18px; height:18px; border-radius:50%; border:2px solid #d0d4e4; display:flex; align-items:center; justify-content:center; background:#fff; color:#fff; font-size:10px; }
    .timeline-dot.done { background:#34c38f; border-color:#34c38f; color:#fff; }
    .timeline-line { width:2px; height:16px; background:#d0d4e4; margin:0 8px 6px 8px; }
    .timeline-text { margin-left:8px; font-size:0.85rem; color:#495057; }
    .timeline-label { font-weight:500; }
  </style>
</body>
</html>