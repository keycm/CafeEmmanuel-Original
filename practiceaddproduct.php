<?php
// 1. Turn on error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'session_check.php';

// 2. Connect to Database FIRST (Moved up to ensure connection exists)
include 'db_connect.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. Define $audit_conn using the main connection
// This passes the open connection to the audit logger
$audit_conn = $conn; 

// 4. SAFELY Include Audit Log
$audit_log_error = "";
try {
    if (file_exists('audit_log.php')) {
        require_once 'audit_log.php';
    } else {
        $audit_log_error = "Warning: audit_log.php file not found.";
    }
} catch (Exception $e) {
    $audit_log_error = "Warning: Error loading audit logs: " . $e->getMessage();
}

$successMessage = "";
$errorMessage = "";
if ($audit_log_error) {
    $errorMessage = $audit_log_error;
}

// --- HANDLE SOFT DELETE REQUEST ---
if (isset($_GET['delete'])) {
    $id_to_delete = intval($_GET['delete']);
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bind_param("i", $id_to_delete);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();
        
        if ($product) {
            // Make sure 'recently_deleted_products' table exists in your DB
            $insert_stmt = $conn->prepare("INSERT INTO recently_deleted_products (original_id, name, price, stock, image, category, rating) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("isdissi", $product['id'], $product['name'], $product['price'], $product['stock'], $product['image'], $product['category'], $product['rating']);
            $insert_stmt->execute();

            $delete_stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $delete_stmt->bind_param("i", $id_to_delete);
            $delete_stmt->execute();

            $conn->commit();
            $successMessage = "✅ Menu item moved to Recycle Bin successfully!";
            
            // Log audit ONLY if function exists and connection is valid
            if (function_exists('logAdminAction') && isset($audit_conn) && $audit_conn->ping()) {
                logAdminAction(
                    $audit_conn,
                    $_SESSION['user_id'],
                    $_SESSION['fullname'],
                    'product_delete',
                    "Deleted product: {$product['name']} (ID: {$id_to_delete})",
                    'products',
                    $id_to_delete
                );
            }
        } else {
            throw new Exception("Product not found.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $errorMessage = "❌ Error deleting item: " . $e->getMessage();
    }
}

// --- HANDLE POST REQUEST (ADD OR EDIT) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if it's an EDIT request
    if (isset($_POST['product_id']) && !empty($_POST['product_id'])) {
        $id = intval($_POST['product_id']);
        $name = $_POST['name'];
        $price = $_POST['price'];
        $stock = $_POST['stock'];
        $category = $_POST['category'];

        // Handle image upload if a new one is provided
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $image_name = uniqid() . '_' . basename($_FILES["image"]["name"]);
            $target_file = $target_dir . $image_name;
            
            if(move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)){
                $stmt = $conn->prepare("UPDATE products SET name=?, price=?, stock=?, category=?, image=? WHERE id=?");
                $stmt->bind_param("sdissi", $name, $price, $stock, $category, $target_file, $id);
                
                if ($stmt->execute()) {
                    $successMessage = "✅ Menu item updated successfully!";
                    if (function_exists('logAdminAction') && isset($audit_conn)) {
                        logAdminAction($audit_conn, $_SESSION['user_id'], $_SESSION['fullname'], 'product_update', "Updated product: {$name} (ID: {$id})", 'products', $id);
                    }
                } else {
                    $errorMessage = "❌ Error updating item: " . $conn->error;
                }
                $stmt->close();
            } else {
                $errorMessage = "❌ Failed to move uploaded file.";
            }
        } else {
            // No new image, update other fields
            $stmt = $conn->prepare("UPDATE products SET name=?, price=?, stock=?, category=? WHERE id=?");
            $stmt->bind_param("sdisi", $name, $price, $stock, $category, $id);
            
            if ($stmt->execute()) {
                $successMessage = "✅ Menu item updated successfully!";
                if (function_exists('logAdminAction') && isset($audit_conn)) {
                    logAdminAction($audit_conn, $_SESSION['user_id'], $_SESSION['fullname'], 'product_update', "Updated product: {$name} (ID: {$id})", 'products', $id);
                }
            } else {
                $errorMessage = "❌ Error updating item: " . $conn->error;
            }
            $stmt->close();
        }

    // It's an ADD request
    } else {
        $name = $_POST['name'];
        $price = $_POST['price'];
        $stock = $_POST['stock'];
        $category = $_POST['category'];
        
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $image_name = uniqid() . '_' . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $image_name;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $stmt = $conn->prepare("INSERT INTO products (name, price, stock, category, image, rating) VALUES (?, ?, ?, ?, ?, 5)"); 
            $stmt->bind_param("sdiss", $name, $price, $stock, $category, $target_file);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                $successMessage = "✅ New menu item added successfully!";
                
                if (function_exists('logAdminAction') && isset($audit_conn)) {
                    logAdminAction($audit_conn, $_SESSION['user_id'], $_SESSION['fullname'], 'product_create', "Created new product: {$name}", 'products', $new_id);
                }
            } else {
                $errorMessage = "❌ Error adding item: " . $conn->error;
            }
            $stmt->close();
        } else {
            $errorMessage = "❌ Error uploading image. Ensure the 'uploads/' folder exists and has write permissions.";
        }
    }
}

// --- FETCH ALL PRODUCTS FOR DISPLAY ---
$products_result = $conn->query("SELECT * FROM products ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Menu - Cafe Emmanuel</title>
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
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .page-header h1 { font-size: 1.5rem; color: var(--text-color); margin: 0; }
    .btn { padding: 10px 20px; border-radius: 6px; border: none; font-weight: 500; cursor: pointer; transition: 0.3s; font-size: 0.9rem; }
    .btn-primary { background: var(--primary-color); color: #fff; }
    .btn-primary:hover { background: #485ec4; }
    .btn-icon { background: none; border: none; font-size: 1.1rem; color: var(--subtle-text); cursor: pointer; padding: 5px; }
    .btn-icon.edit:hover { color: var(--primary-color); }
    .btn-icon.delete:hover { color: var(--red-accent); }
    .message { text-align: center; font-weight: bold; margin-bottom: 20px; font-size: 1rem; padding: 12px; border-radius: 6px; }
    .message.success { color: #0f5132; background-color: #d1e7dd; border: 1px solid #badbcc; }
    .message.error { color: #842029; background-color: #f8d7da; border: 1px solid #f5c2c7; }

    .table-card { background: var(--card-bg); padding: 25px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .search-input { position: relative; }
    .search-input i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--subtle-text); }
    .search-input input { padding: 10px 15px 10px 40px; border: 1px solid var(--border-color); border-radius: 6px; width: 300px; }

    .products-table { width: 100%; border-collapse: collapse; }
    .products-table th, .products-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
    .products-table th { font-weight: 600; font-size: 0.8rem; color: var(--subtle-text); text-transform: uppercase; }
    .products-table td img { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; }
    .products-table .actions { display: inline-flex; gap: 10px; align-items: center; white-space: nowrap; }

    /* Modal Styles */
    .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
    .modal-content { background-color: #fefefe; margin: auto; padding: 30px; border: 1px solid #888; width: 90%; max-width: 600px; border-radius: 8px; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .modal-header h2 { margin: 0; }
    .close-btn { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 500; margin-bottom: 8px; }
    .form-group input, .form-group select { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 0.95rem; }
    .form-group.full-width { grid-column: 1 / -1; }
    .modal-footer { margin-top: 25px; text-align: right; }
</style>
</head>
<body>
<div class="admin-container">
    
    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <h1>Manage Menu Items</h1>
            <button id="showAddFormBtn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Item</button>
        </header>

        <?php if($successMessage): ?><div class="message success"><?php echo $successMessage; ?></div><?php endif; ?>
        <?php if($errorMessage): ?><div class="message error"><?php echo $errorMessage; ?></div><?php endif; ?>

        <div class="table-card">
            <div class="table-header">
                <div class="search-input">
                    <i class="fas fa-search"></i>
                    <input type="text" id="product-search" placeholder="Search menu items...">
                </div>
            </div>
            <div class="table-responsive">
                <table class="products-table">
                    <thead><tr><th>Image</th><th>Item Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php 
                    if ($products_result && $products_result->num_rows > 0) {
                        while ($row = $products_result->fetch_assoc()): 
                    ?>
                        <tr data-name="<?php echo strtolower(htmlspecialchars($row['name'])); ?>">
                            <td><img src="<?php echo htmlspecialchars($row['image']); ?>" alt="" onerror="this.src='https://via.placeholder.com/40'"></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td>₱<?php echo number_format($row['price'], 2); ?></td>
                            <td><?php echo $row['stock']; ?></td>
                            <td>
                                <div class="actions">
                                    <button class="btn-icon edit edit-btn" title="Edit"
                                        data-id="<?php echo $row['id']; ?>" data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                        data-price="<?php echo $row['price']; ?>" data-stock="<?php echo $row['stock']; ?>"
                                        data-category="<?php echo htmlspecialchars($row['category']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="practiceaddproduct.php?delete=<?php echo $row['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Move this item to the Recycle Bin?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php 
                        endwhile; 
                    } else {
                        echo "<tr><td colspan='6' style='text-align:center;'>No products found</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<div id="productModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add New Menu Item</h2>
            <span class="close-btn">&times;</span>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="product_id" id="product-id">
            <div class="form-grid">
                <div class="form-group">
                    <label>Item Name:</label><input type="text" name="name" id="product-name" required>
                </div>
                <div class="form-group">
                    <label>Category:</label>
                    <select name="category" id="product-category" required>
                        <option value="">-- Select --</option>
                        <option value="coffee">Coffee</option>
                        <option value="burger">Burger</option>
                        <option value="pizza">Pizza</option>
                        <option value="pasta">Pasta</option>
                        <option value="dessert">Dessert</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price:</label><input type="number" name="price" id="product-price" step="0.01" min="1" required>
                </div>
                <div class="form-group">
                    <label>Stock:</label><input type="number" name="stock" id="product-stock" min="0" required>
                </div>
                <div class="form-group full-width">
                    <label>Item Image:</label><input type="file" name="image" accept="image/*">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Save Item</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById('productModal');
    const showAddBtn = document.getElementById('showAddFormBtn');
    const closeBtn = modal.querySelector('.close-btn');

    // Function to open the modal for adding
    showAddBtn.onclick = function() {
        modal.querySelector('form').reset();
        document.getElementById('product-id').value = '';
        document.getElementById('modalTitle').textContent = 'Add New Menu Item';
        modal.style.display = 'flex';
    }

    // Function to open the modal for editing
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('product-id').value = button.dataset.id;
            document.getElementById('product-name').value = button.dataset.name;
            document.getElementById('product-price').value = button.dataset.price;
            document.getElementById('product-stock').value = button.dataset.stock;
            document.getElementById('product-category').value = button.dataset.category;
            document.getElementById('modalTitle').textContent = 'Edit Menu Item';
            modal.style.display = 'flex';
        });
    });

    // Close modal actions
    closeBtn.onclick = () => { modal.style.display = 'none'; }
    window.onclick = (event) => {
        if (event.target == modal) { modal.style.display = 'none'; }
    }
    
    // Search functionality
    const searchInput = document.getElementById('product-search');
    const tableRows = document.querySelectorAll('.products-table tbody tr');
    searchInput.addEventListener('input', () => {
        const searchTerm = searchInput.value.toLowerCase();
        tableRows.forEach(row => {
            const name = row.dataset.name;
            if(name) {
                row.style.display = name.includes(searchTerm) ? '' : 'none';
            }
        });
    });
});
</script>
</body>
</html>