<?php
include 'session_check.php';
include 'db_connect.php';
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
$sql = "SELECT * FROM cart ORDER BY id DESC";
$result = $conn->query($sql);

function getInitials($name) {
    $words = explode(" ", $name);
    $initials = "";
    foreach ($words as $w) { if (!empty($w)) { $initials .= strtoupper($w[0]); } }
    return substr($initials, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Orders Dashboard</title>
<link rel="stylesheet" href="CSS/admin.css"/>
<link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<style>
    :root {
        --primary-color: #556ee6; --main-bg: #f8f8fb; --card-bg: #ffffff;
        --text-color: #495057; --subtle-text: #74788d; --border-color: #eff2f7;
        --green-accent: #34c38f; --red-accent: #f46a6a; --yellow-accent: #f1b44c;
    }
    /* Mirror admin.css layout so Orders size matches other admin pages */
    .admin-container {
        display: flex;
        height: 100vh;
        width: 100%;
    }
    .main-content {
        flex: 1;
        padding: 25px;
        overflow-y: auto;
        background: var(--main-bg);
    }
    .table-card {
        background: var(--card-bg);
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        width: 100%;
        box-sizing: border-box;
    }
    .card-header { margin-bottom: 20px; }
    .card-header h1 { font-size: 1.5rem; color: var(--text-color); margin: 0; }
    .table-responsive { overflow-x: auto; }
    .orders-table { width: 100%; border-collapse: collapse; }
    .orders-table th, .orders-table td {
        padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color);
        vertical-align: middle; white-space: nowrap; font-size: 0.9rem;
    }
    .orders-table th { font-weight: 600; color: var(--subtle-text); text-transform: uppercase; }
    .customer-cell { display: flex; align-items: center; gap: 10px; }
    .avatar { flex-shrink: 0; width: 36px; height: 36px; border-radius: 50%; background-color: #e8f0fe; color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-weight: 600; }
    .item-tags { white-space: normal; min-width: 250px; }
    .item-tag { display: block; margin-bottom: 4px; }
    .item-price { color: var(--subtle-text); font-size: 0.85rem; }
    .status-pill { padding: 5px 10px; border-radius: 20px; font-weight: 500; display: inline-flex; align-items: center; gap: 5px; }
    .status-pill::before { content: ''; display: inline-block; width: 6px; height: 6px; border-radius: 50%; }
    .status-pill.pending { background-color: #fef8ec; color: var(--yellow-accent); }
    .status-pill.pending::before { background-color: var(--yellow-accent); }
    .status-pill.confirmed { background-color: #e7f1ff; color: var(--primary-color); }
    .status-pill.confirmed::before { background-color: var(--primary-color); }
    .status-pill.processing { background-color: #e7f1ff; color: var(--primary-color); }
    .status-pill.processing::before { background-color: var(--primary-color); }
    .status-pill.out\ for\ delivery, .status-pill.out_for_delivery { background-color: #f1f5ff; color: #2851a3; }
    .status-pill.out\ for\ delivery::before, .status-pill.out_for_delivery::before { background-color: #2851a3; }
    .status-pill.delivered { background-color: #eaf7f3; color: var(--green-accent); }
    .status-pill.delivered::before { background-color: var(--green-accent); }
    .status-pill.cancelled { background-color: #fdeeee; color: var(--red-accent); }
    .status-pill.cancelled::before { background-color: var(--red-accent); }
    .action-icons { display: flex; gap: 8px; flex-wrap: nowrap; align-items: center; }
    .action-icons form { margin: 0; display: inline-flex; }
    .action-btn { background: none; border: none; cursor: pointer; font-size: 1.1rem; color: var(--subtle-text); transition: color 0.2s; padding: 4px; }
    .action-btn.view:hover { color: var(--primary-color); }
    .action-btn.print-btn:hover { color: #111; }
    .action-btn.download-btn:hover { color: #dc3545; }
.action-btn.accept:hover { color: var(--primary-color); }
    .action-btn.processing:hover { color: #ff9800; }
    .action-btn.delivery:hover { color: #2196f3; }
    .action-btn.complete:hover { color: var(--green-accent); }
    .action-btn.cancel:hover { color: var(--yellow-accent); }
    .action-btn.delete:hover { color: var(--red-accent); }

    /* Modal Styles */
    .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
    .modal-content { background-color: var(--card-bg); padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; position: relative; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px; }
    .modal-header h2 { margin: 0; font-size: 1.3rem; }
    .close-btn { font-size: 1.8rem; color: var(--subtle-text); cursor: pointer; }
    .modal-body .info-group { margin-bottom: 15px; }
    .modal-body .info-group strong { display: block; color: var(--subtle-text); font-size: 0.8rem; margin-bottom: 5px; }
    .modal-body .info-group p { margin: 0; font-size: 1rem; }
    .modal-items-table { width: 100%; margin-top: 20px; border-top: 1px solid var(--border-color); }
    .modal-items-table th, .modal-items-table td { padding: 10px 0; border-bottom: 1px solid var(--border-color); text-align: left; }
    .modal-items-table .total-row { font-weight: bold; font-size: 1.1rem; }
</style>
</head>
<body>
<div class="admin-container">
  
  <?php include 'admin_sidebar.php'; ?>

  <main class="main-content">
    <div class="card-header">
        <h1>Orders</h1>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="orders-table">
              <thead>
                <tr>
                  <th>Order ID</th> <th>Customer</th> <th>Contact</th>
                  <th>Items Ordered</th> <th>Total</th> <th>Status</th> <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $result->fetch_assoc()) : ?>
                  <?php
                    $cart_items = json_decode($row['cart'], true);
                    $status_class = strtolower($row['status'] ?? 'pending');
                    $row_data = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                  ?>
                  <tr>
                    <td><strong>ORD-00<?php echo $row['id']; ?></strong><br><small><?php echo date("d M Y, h:i A", strtotime($row['created_at'])); ?></small></td>
                    <td>
                        <div class="customer-cell">
                            <div class="avatar"><?php echo getInitials($row['fullname']); ?></div>
                            <span><?php echo htmlspecialchars($row['fullname']); ?></span>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($row['contact']); ?></td>
                    <td class="item-tags">
                        <?php
                            if ($cart_items && is_array($cart_items)) {
                                foreach ($cart_items as $item) {
                                    echo '<span class="item-tag">' . ($item['quantity'] ?? 1) . ' x ' . htmlspecialchars($item['name'] ?? 'N/A') . ' <span class="item-price">(₱' . number_format($item['price'] ?? 0, 2) . ' each)</span></span>';
                                }
                            }
                        ?>
                    </td>
                    <td><strong>₱<?php echo number_format($row['total'], 2); ?></strong></td>
                    <td>
                        <?php 
                          $label = $status_class;
                          if ($status_class === 'delivered') { $label = 'Delivered'; }
                          elseif ($status_class === 'out for delivery' || $status_class === 'out_for_delivery') { $label = 'Out for Delivery'; }
                          elseif ($status_class === 'processing') { $label = 'Processing'; }
                          elseif ($status_class === 'confirmed') { $label = 'Confirmed'; }
                          elseif ($status_class === 'pending') { $label = 'Pending'; }
                        ?>
                        <div class="status-pill <?php echo str_replace(' ', '_', $status_class); ?>"><?php echo htmlspecialchars(ucwords($label)); ?></div>
                    </td>
                    <td>
                      <div class="action-icons">
                        <button class="action-btn view view-btn" title="View Details" data-order='<?php echo $row_data; ?>'><i class="fas fa-eye"></i></button>
                        <button class="action-btn print-btn" title="Print Receipt" data-order='<?php echo $row_data; ?>' onclick="printReceipt(this)"><i class="fas fa-print"></i></button>
                        <button class="action-btn download-btn" title="Download PDF" data-order='<?php echo $row_data; ?>' onclick="downloadPDF(this)"><i class="fas fa-file-pdf"></i></button>
                        
                        <?php if ($status_class === 'pending') : ?>
                            <!-- Step 2: Confirm order -->
                            <form method="POST" action="update_order.php" onsubmit="return confirm('Confirm this order?');">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="action" value="accept">
                                <button type="submit" class="action-btn accept" title="Confirm Order"><i class="fas fa-check-circle"></i></button>
                            </form>
                            <form method="POST" action="update_order.php" onsubmit="return confirm('Cancel this order? This will restock items.');">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button type="submit" class="action-btn cancel" title="Cancel Order"><i class="fas fa-times-circle"></i></button>
                            </form>
                        <?php elseif ($status_class === 'confirmed') : ?>
                            <!-- Step 3: Food Preparing / Processing -->
                            <form method="POST" action="update_order.php" onsubmit="return confirm('Mark as food preparing / processing?');">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="action" value="processing">
                                <button type="submit" class="action-btn processing" title="Food Preparing"><i class="fas fa-utensils"></i></button>
                            </form>
                        <?php elseif ($status_class === 'processing') : ?>
                            <!-- Step 4: Out for delivery -->
                            <form method="POST" action="update_order.php" onsubmit="return confirm('Mark this order as out for delivery?');">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="action" value="out_for_delivery">
                                <button type="submit" class="action-btn delivery" title="Out for Delivery"><i class="fas fa-truck"></i></button>
                            </form>
                        <?php elseif ($status_class === 'out for delivery' || $status_class === 'out_for_delivery') : ?>
                            <!-- Optional: mark delivered from admin side -->
                            <form method="POST" action="update_order.php" onsubmit="return confirm('Mark this order as delivered? This should be used by delivery staff.');">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="action" value="completed">
                                <button type="submit" class="action-btn complete" title="Mark as Delivered"><i class="fas fa-check-double"></i></button>
                            </form>
                        <?php endif; ?>

                        <form method="POST" action="update_order.php" onsubmit="return confirm('Move this order to the recycle bin?');">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="action-btn delete" title="Delete Order"><i class="fas fa-trash-alt"></i></button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
        </div>
    </div>
  </main>
</div>

<div id="orderDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalOrderId">Order Details</h2>
            <span class="close-btn">&times;</span>
        </div>
        <div class="modal-body">
            <div class="info-group">
                <strong>CUSTOMER</strong>
                <p id="modalCustomerName"></p>
            </div>
            <div class="info-group">
                <strong>CONTACT</strong>
                <p id="modalCustomerContact"></p>
            </div>
            <div class="info-group">
                <strong>DELIVERY ADDRESS</strong>
                <p id="modalCustomerAddress"></p>
            </div>

            <!-- Status timeline -->
            <div class="info-group">
                <strong>ORDER STATUS</strong>
                <div id="modalStatusTimeline" class="status-timeline"></div>
            </div>

            <table class="modal-items-table">
                <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
                <tbody id="modalItemsTbody"></tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3">Grand Total</td>
                        <td id="modalGrandTotal"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById('orderDetailsModal');
    const closeBtn = modal.querySelector('.close-btn');
    const viewBtns = document.querySelectorAll('.view-btn');

    function renderStatusTimeline(container, status) {
        const normalized = (status || '').toLowerCase();
        const steps = [
            { key: 'placed', label: 'Order Placed' },
            { key: 'pending', label: 'Pending' },
            { key: 'confirmed', label: 'Confirmed' },
            { key: 'processing', label: 'Processing' },
            { key: 'delivered', label: 'Delivered' }
        ];

        let currentIndex = 0;
        if (normalized === 'pending') currentIndex = 1;
        else if (normalized === 'confirmed') currentIndex = 2;
        else if (normalized === 'processing') currentIndex = 3;
        else if (normalized === 'out for delivery' || normalized === 'out_for_delivery') currentIndex = 3; // still processing/on the way
        else if (normalized === 'delivered') currentIndex = 4;

        let html = '';
        steps.forEach((step, idx) => {
            const done = idx <= currentIndex;
            html += `
                <div class="timeline-row">
                    <div class="timeline-dot ${done ? 'done' : ''}">
                        ${done ? '<i class="fas fa-check"></i>' : ''}
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

    viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const orderData = JSON.parse(this.getAttribute('data-order'));
            const cartItems = JSON.parse(orderData.cart);
            
            document.getElementById('modalOrderId').innerText = `Order #ORD-00${orderData.id}`;
            document.getElementById('modalCustomerName').innerText = orderData.fullname;
            document.getElementById('modalCustomerContact').innerText = orderData.contact;
            document.getElementById('modalCustomerAddress').innerText = orderData.address;

            // Render status timeline
            const timelineContainer = document.getElementById('modalStatusTimeline');
            renderStatusTimeline(timelineContainer, orderData.status || 'pending');

            const itemsTbody = document.getElementById('modalItemsTbody');
            itemsTbody.innerHTML = ''; // Clear previous items
            cartItems.forEach(item => {
                const itemTotal = (item.price || 0) * (item.quantity || 0);
                const row = `
                    <tr>
                        <td>${item.name}</td>
                        <td>${item.quantity}</td>
                        <td>₱${Number(item.price).toFixed(2)}</td>
                        <td>₱${itemTotal.toFixed(2)}</td>
                    </tr>`;
                itemsTbody.innerHTML += row;
            });

            document.getElementById('modalGrandTotal').innerText = `₱${Number(orderData.total).toFixed(2)}`;
            
            modal.style.display = 'flex';
        });
    });

    closeBtn.onclick = () => modal.style.display = 'none';
    window.onclick = (event) => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
});

// Timeline styles injected via JS-created classes
</script>
<style>
.status-timeline { margin-top: 10px; padding-left: 4px; }
.timeline-row { display: flex; align-items: center; margin-bottom: 6px; }
.timeline-dot { width: 18px; height: 18px; border-radius: 50%; border: 2px solid #d0d4e4; display:flex; align-items:center; justify-content:center; background:#fff; color:#fff; font-size:10px; }
.timeline-dot.done { background:#34c38f; border-color:#34c38f; color:#fff; }
.timeline-line { width:2px; height:16px; background:#d0d4e4; margin:0 8px 6px 8px; }
.timeline-text { margin-left:8px; font-size:0.85rem; color:#495057; }
.timeline-label { font-weight:500; }
</style>

<script>
function printReceipt(btn) {
    const orderData = JSON.parse(btn.getAttribute('data-order'));
    const items = JSON.parse(orderData.cart || '[]');
    const orderDate = new Date(orderData.created_at.replace(' ', 'T'));
    const dateStr = orderDate.toLocaleDateString('en-US', {weekday:'long', year:'numeric', month:'long', day:'numeric'});
    
    let html = `<html><head><title>Receipt #${orderData.id}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&display=swap');
        body{font-family:'Courier Prime',Courier,monospace; padding:40px; max-width:400px; margin:auto; background:#fff; color:#333; font-size:13px; line-height:1.4;}
        .receipt{background:#f9f9f9; padding:30px; box-shadow:0 0 20px rgba(0,0,0,0.1);}
        .header{text-align:center; margin-bottom:20px;}
        .brand{font-size:28px; font-weight:700; letter-spacing:2px; margin-bottom:5px;}
        .divider{border-top:1px dashed #999; margin:15px 0;}
        .order-info{margin-bottom:15px; font-size:12px;}
        .customer-box{background:#fff; padding:10px; margin:15px 0; border:1px dashed #ccc;}
        .items-header{display:flex; justify-content:space-between; font-weight:700; font-size:11px; text-transform:uppercase; margin-bottom:8px; padding-bottom:5px; border-bottom:1px dashed #999;}
        .item-row{display:flex; justify-content:space-between; margin-bottom:8px; font-size:12px;}
        .summary{margin-top:15px; padding-top:15px; border-top:1px dashed #999;}
        .total-row{display:flex; justify-content:space-between; font-size:16px; font-weight:700; margin-top:10px; padding-top:10px; border-top:2px solid #333;}
        @media print{body{padding:0;} .receipt{box-shadow:none;}}
    </style></head><body>
    <div class="receipt">
        <div class="header"><div class="brand">CAFE EMMANUEL</div></div>
        <div class="divider"></div>
        <div class="order-info">
            <div>ORDER #${String(orderData.id).padStart(4,'0')} FOR ${(orderData.fullname||'').toUpperCase()}</div>
            <div>${dateStr.toUpperCase()}</div>
        </div>
        <div class="divider"></div>
        <div class="customer-box">
            <div><strong>${orderData.fullname || 'N/A'}</strong></div>
            <div>${orderData.contact || 'N/A'}</div>
            <div>${orderData.address || 'N/A'}</div>
        </div>
        <div class="items-header">
            <div style="width:30px;">Qty</div>
            <div style="flex:1;">Item</div>
            <div style="width:60px; text-align:right;">Amt</div>
        </div>`;
    
    let total = 0;
    items.forEach((it, idx) => {
        const qty = Number(it.quantity||1);
        const price = Number(it.price||0);
        const line = qty * price;
        total += line;
        html += `<div class="item-row">
                    <div style="width:30px;">${String(idx+1).padStart(2,'0')}</div>
                    <div style="flex:1;">
                        <div style="font-weight:700;">${(it.name||'').toUpperCase()}</div>
                        <div style="color:#666; font-size:11px;">₱${price.toFixed(2)} each</div>
                    </div>
                    <div style="width:60px; text-align:right; font-weight:700;">₱${line.toFixed(0)}</div>
                </div>`;
    });
    
    html += `<div class="divider"></div>
            <div class="summary">
                <div class="total-row"><span>TOTAL:</span><span>₱${Number(orderData.total||total).toFixed(0)}</span></div>
            </div>
            <div style="text-align:center; margin-top:20px; padding-top:15px; border-top:1px dashed #999; font-size:11px;">
                <div style="margin-bottom:5px;">THANK YOU FOR VISITING!</div>
                <div style="font-size:10px; color:#666;">San Antonio, Guagua, 2003 Pampanga</div>
            </div>
        </div>
    </body></html>`;
    
    const win = window.open('','','width=500,height=700');
    win.document.write(html);
    win.document.close();
    win.print();
}

function downloadPDF(btn) {
    const orderData = JSON.parse(btn.getAttribute('data-order'));
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const items = JSON.parse(orderData.cart || '[]');
    
    const darkGray = [51, 51, 51];
    const medGray = [102, 102, 102];
    
    doc.setFillColor(249, 249, 249);
    doc.rect(0, 0, 210, 297, 'F');
    
    let yPos = 35;
    
    doc.setFont('courier', 'bold');
    doc.setFontSize(22);
    doc.setTextColor(...darkGray);
    doc.text('CAFE EMMANUEL', 105, yPos, { align: 'center' });
    yPos += 10;
    
    doc.setLineDash([2, 2]);
    doc.setDrawColor(153, 153, 153);
    doc.line(35, yPos, 175, yPos);
    yPos += 8;
    doc.setLineDash([]);
    
    doc.setFont('courier', 'normal');
    doc.setFontSize(9);
    const orderDate = new Date(orderData.created_at.replace(' ', 'T'));
    const dateStr = orderDate.toLocaleDateString('en-US', {weekday:'long', year:'numeric', month:'long', day:'numeric'}).toUpperCase();
    doc.text(`ORDER #${String(orderData.id).padStart(4, '0')} FOR ${(orderData.fullname || 'CUSTOMER').toUpperCase()}`, 105, yPos, { align: 'center' });
    yPos += 5;
    doc.text(dateStr, 105, yPos, { align: 'center' });
    yPos += 10;
    
    doc.setFillColor(249, 249, 249);
    doc.rect(35, yPos, 140, 18, 'F');
    yPos += 5;
    doc.setFontSize(7);
    doc.setTextColor(...medGray);
    doc.text('CUSTOMER DETAILS', 40, yPos);
    yPos += 4;
    doc.setFontSize(9);
    doc.setTextColor(...darkGray);
    doc.text(orderData.fullname || 'N/A', 40, yPos);
    yPos += 4;
    doc.text(orderData.contact || 'N/A', 40, yPos);
    yPos += 4;
    doc.text((orderData.address || 'N/A').substring(0, 50), 40, yPos);
    yPos += 10;
    
    doc.setFont('courier', 'bold');
    doc.setFontSize(8);
    doc.text('QTY', 40, yPos);
    doc.text('ITEM', 55, yPos);
    doc.text('AMT', 170, yPos, { align: 'right' });
    yPos += 6;
    
    doc.setFont('courier', 'normal');
    doc.setFontSize(9);
    let total = 0;
    
    items.forEach((it, idx) => {
        const qty = Number(it.quantity || 1);
        const price = Number(it.price || 0);
        const line = qty * price;
        total += line;
        
        doc.text(String(idx + 1).padStart(2, '0'), 40, yPos);
        doc.setFont('courier', 'bold');
        doc.text((it.name || 'N/A').toUpperCase().substring(0, 30), 55, yPos);
        doc.text(`${Math.round(line)}`, 170, yPos, { align: 'right' });
        yPos += 4;
        doc.setFont('courier', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(...medGray);
        doc.text(`₱${price.toFixed(2)} each`, 55, yPos);
        yPos += 6;
        doc.setFontSize(9);
        doc.setTextColor(...darkGray);
    });
    
    yPos += 5;
    doc.setFont('courier', 'bold');
    doc.setFontSize(12);
    doc.text('TOTAL:', 40, yPos);
    doc.text(`₱${Math.round(Number(orderData.total || total))}`, 170, yPos, { align: 'right' });
    
    doc.save(`CafeEmmanuel_Receipt_${orderData.id}.pdf`);
}
</script>
</body>
</html>
<?php $conn->close(); ?>