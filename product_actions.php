<?php
session_start(); // Start session for admin info and feedback messages
require_once 'audit_log.php'; // Include the audit log helper

// Connection for products
$conn = new mysqli("localhost", "root", "", "addproduct");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Connection for audit logs
$audit_conn = new mysqli("localhost", "root", "", "login_system");
if ($audit_conn->connect_error) {
    die("Audit connection failed: " . $audit_conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    $conn->begin_transaction();
    try {
        // Get the deleted product record from the 'recently_deleted_products' table
        $stmt = $conn->prepare("SELECT * FROM recently_deleted_products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        if (!$product) {
            throw new Exception("Product not found in recently deleted items.");
        }

        if ($action === 'restore') {
            // Restore: Insert it back into the main 'products' table.
            $insert_stmt = $conn->prepare("INSERT INTO products (name, price, stock, image, category, rating) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("sdissi", $product['name'], $product['price'], $product['stock'], $product['image'], $product['category'], $product['rating']);
            $insert_stmt->execute();
            $new_product_id = $conn->insert_id; // Get the new ID it was restored to

            // Now, delete the record from the 'recently_deleted_products' table
            $delete_stmt = $conn->prepare("DELETE FROM recently_deleted_products WHERE id = ?");
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
            
            $_SESSION['success_message'] = "Product restored successfully!";

            // --- ADDED AUDIT LOG ---
            logAdminAction(
                $audit_conn,
                $_SESSION['user_id'] ?? 0,
                $_SESSION['fullname'] ?? 'Admin',
                'product_restore',
                "Restored product: {$product['name']} (New ID: {$new_product_id}, Original Deleted ID: {$product['original_id']})",
                'products',
                $new_product_id
            );
            // --- END OF LOG ---

        } elseif ($action === 'permanent_delete') {
            // Permanently delete: first, remove the image file from the server
            if (file_exists($product['image'])) {
                @unlink($product['image']); // Use @ to suppress errors if file not found
            }
            // Then, delete the record from the 'recently_deleted_products' table permanently
            $delete_stmt = $conn->prepare("DELETE FROM recently_deleted_products WHERE id = ?");
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();
            
            $_SESSION['success_message'] = "Product permanently deleted!";

            // --- ADDED AUDIT LOG ---
            logAdminAction(
                $audit_conn,
                $_SESSION['user_id'] ?? 0,
                $_SESSION['fullname'] ?? 'Admin',
                'product_delete_permanent',
                "Permanently deleted product: {$product['name']} (Original ID: {$product['original_id']})",
                'recently_deleted_products',
                $product['id'] // Log against the ID from the deleted table
            );
            // --- END OF LOG ---
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        // Store an error message to display on the next page
        $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    }
}

// Close connections
$conn->close();
$audit_conn->close();

// Redirect back to the unified 'recently_deleted.php' page
header("Location: recently_deleted.php");
exit();
?>