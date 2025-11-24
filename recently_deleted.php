<?php
// 1. Turn on error reporting to see issues instead of white screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'session_check.php';

// 2. Connect to Database ONCE
include 'db_connect.php';

// Check if the main connection variable exists (usually $conn)
if (!isset($conn)) {
    die("Error: Database connection variable \$conn is missing in db_connect.php");
}
if ($conn->connect_error) { 
    die("Connection failed: " . $conn->connect_error); 
}

// 3. Map the connection to the variables your code expects
// Since you are on Hostinger, everything is in ONE database.
$conn_addproduct = $conn;
$conn_login_system = $conn;

// Fetch recently deleted orders
// Ensure table 'recently_deleted' exists
$orders_result = $conn_addproduct->query("SELECT * FROM recently_deleted ORDER BY deleted_at DESC");
if (!$orders_result) {
    // Fail gracefully if table doesn't exist
    $orders_error = $conn_addproduct->error;
}

// Fetch recently deleted products
$products_result = $conn_addproduct->query("SELECT * FROM recently_deleted_products ORDER BY deleted_at DESC");

// Fetch recently deleted users
$users_result = $conn_login_system->query("SELECT * FROM recently_deleted_users ORDER BY deleted_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Recycle Bin</title>
<link rel="stylesheet" href="CSS/admin.css"/>
<link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
    :root {
        --primary-color: #556ee6;
        --main-bg: #f8f8fb;
        --card-bg: #ffffff;
        --text-color: #495057;
        --subtle-text: #74788d;
        --border-color: #eff2f7;
        --green-accent: #34c38f;
        --red-accent: #f46a6a;
    }
    .main-content { background-color: var(--main-bg); }
    .card-header { margin-bottom: 25px; }
    .card-header h1 { font-size: 1.5rem; color: var(--text-color); margin: 0; }
    
    .tabs-container {
        display: flex;
        border-bottom: 2px solid var(--border-color);
        margin-bottom: 25px;
    }
    .tab-link {
        padding: 12px 20px;
        cursor: pointer;
        font-weight: 500;
        color: var(--subtle-text);
        border-bottom: 2px solid transparent;
        margin-bottom: -2px; /* Align with container border */
        transition: color 0.2s, border-color 0.2s;
    }
    .tab-link:hover {
        color: var(--text-color);
    }
    .tab-link.active {
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
    }

    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }

    .table-card { background: var(--card-bg); padding: 25px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
    .data-table th { font-weight: 600; font-size: 0.8rem; color: var(--subtle-text); text-transform: uppercase; }
    .data-table td { font-size: 0.9rem; }
    .data-table td img { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; }

    .action-icons { display: inline-flex; gap: 10px; align-items: center; white-space: nowrap; }
    .action-icons form { display: inline; margin: 0; }
    .action-btn { background: none; border: none; cursor: pointer; font-size: 1.1rem; color: var(--subtle-text); transition: color 0.2s; padding: 5px; }
    .action-btn.restore:hover { color: var(--green-accent); }
    .action-btn.delete:hover { color: var(--red-accent); }
</style>
</head>
<body>
<div class="admin-container">

  <?php include 'admin_sidebar.php'; ?>

  <main class="main-content">
    <header class="card-header">
        <h1>Recycle Bin</h1>
    </header>

    <div class="tabs-container">
        <div class="tab-link active" onclick="openTab(event, 'orders')">Deleted Orders</div>
        <div class="tab-link" onclick="openTab(event, 'products')">Deleted Products</div>
        <div class="tab-link" onclick="openTab(event, 'users')">Deleted Users</div>
    </div>

    <div id="orders" class="tab-content active">
        <div class="table-card">
            <?php if (isset($orders_error)): ?>
                <p style="color:red;">Error fetching orders: <?php echo htmlspecialchars($orders_error); ?></p>
            <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Order ID</th><th>Customer Name</th><th>Deleted At</th><th>Actions</th></tr></thead>
                <tbody>
                  <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                      <?php while ($row = $orders_result->fetch_assoc()) : ?>
                        <tr>
                          <td>#<?php echo $row['order_id']; ?></td>
                          <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                          <td><?php echo date("d M Y, h:i A", strtotime($row['deleted_at'])); ?></td>
                          <td>
                            <div class="action-icons">
                              <form method="POST" action="restore_delete.php" onsubmit="return confirm('Restore this order?');">
                                  <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                  <input type="hidden" name="action" value="restore">
                                  <button type="submit" class="action-btn restore" title="Restore"><i class="fas fa-undo"></i></button>
                              </form>
                              <form method="POST" action="restore_delete.php" onsubmit="return confirm('Permanently delete this order? This cannot be undone.');">
                                  <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                  <input type="hidden" name="action" value="permanent">
                                  <button type="submit" class="action-btn delete" title="Permanent Delete"><i class="fas fa-trash-alt"></i></button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      <?php endwhile; ?>
                  <?php else: ?>
                      <tr><td colspan="4" style="text-align:center;">No deleted orders found.</td></tr>
                  <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div id="products" class="tab-content">
        <div class="table-card">
            <table class="data-table">
                <thead><tr><th>Image</th><th>Product Name</th><th>Deleted At</th><th>Actions</th></tr></thead>
                <tbody>
                  <?php if ($products_result && $products_result->num_rows > 0): ?>
                      <?php while ($row = $products_result->fetch_assoc()) : ?>
                        <tr>
                          <td><img src="<?php echo htmlspecialchars($row['image']); ?>" alt="" onerror="this.src='https://via.placeholder.com/40'"></td>
                          <td><?php echo htmlspecialchars($row['name']); ?></td>
                          <td><?php echo date("d M Y, h:i A", strtotime($row['deleted_at'])); ?></td>
                          <td>
                            <div class="action-icons">
                              <form method="POST" action="product_actions.php" onsubmit="return confirm('Restore this product?');">
                                  <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                  <input type="hidden" name="action" value="restore">
                                  <button type="submit" class="action-btn restore" title="Restore"><i class="fas fa-undo"></i></button>
                              </form>
                              <form method="POST" action="product_actions.php" onsubmit="return confirm('Permanently delete this product? This cannot be undone.');">
                                  <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                  <input type="hidden" name="action" value="permanent_delete">
                                  <button type="submit" class="action-btn delete" title="Permanent Delete"><i class="fas fa-trash-alt"></i></button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      <?php endwhile; ?>
                   <?php else: ?>
                      <tr><td colspan="4" style="text-align:center;">No deleted products found.</td></tr>
                   <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="users" class="tab-content">
        <div class="table-card">
            <table class="data-table">
                <thead><tr><th>Full Name</th><th>Username</th><th>Email</th><th>Deleted At</th><th>Actions</th></tr></thead>
                <tbody>
                  <?php if ($users_result && $users_result->num_rows > 0): ?>
                      <?php while ($row = $users_result->fetch_assoc()) : ?>
                        <tr>
                          <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                          <td><?php echo htmlspecialchars($row['username']); ?></td>
                          <td><?php echo htmlspecialchars($row['email']); ?></td>
                          <td><?php echo date("d M Y, h:i A", strtotime($row['deleted_at'])); ?></td>
                          <td>
                            <div class="action-icons">
                              <form method="POST" action="user_restore_actions.php" onsubmit="return confirm('Restore this user?');">
                                  <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                  <input type="hidden" name="action" value="restore">
                                  <button type="submit" class="action-btn restore" title="Restore"><i class="fas fa-undo"></i></button>
                              </form>
                              <form method="POST" action="user_restore_actions.php" onsubmit="return confirm('Permanently delete this user? This cannot be undone.');">
                                  <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                  <input type="hidden" name="action" value="permanent_delete">
                                  <button type="submit" class="action-btn delete" title="Permanent Delete"><i class="fas fa-trash-alt"></i></button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      <?php endwhile; ?>
                  <?php else: ?>
                      <tr><td colspan="5" style="text-align:center;">No deleted users found.</td></tr>
                  <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
  </main>

</div>

<script>
    function openTab(evt, tabName) {
        // Get all elements with class="tab-content" and hide them
        const tabcontent = document.getElementsByClassName("tab-content");
        for (let i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
            tabcontent[i].classList.remove("active");
        }

        // Get all elements with class="tab-link" and remove the class "active"
        const tablinks = document.getElementsByClassName("tab-link");
        for (let i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }

        // Show the current tab, and add an "active" class to the button that opened the tab
        document.getElementById(tabName).style.display = "block";
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("active");
    }
</script>
</body>
</html>
<?php 
// Close connection safely
if (isset($conn)) $conn->close(); 
?>