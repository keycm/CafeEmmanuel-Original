<?php
include 'db_connect.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $action = $_POST['action'];

    $stmt = $conn->prepare("SELECT * FROM recently_deleted_products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $product = $res->fetch_assoc();
    $stmt->close();

    if ($product) {
        if ($action === "restore") {
            // Ibalik sa products
            $stmt = $conn->prepare("INSERT INTO products (product_no, name, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isid", $product['product_id'], $product['name'], $product['stock'], $product['price']);
            $stmt->execute();
            $stmt->close();

            // Tanggalin sa recently_deleted_products
            $stmt = $conn->prepare("DELETE FROM recently_deleted_products WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        } elseif ($action === "permanent") {
            $stmt = $conn->prepare("DELETE FROM recently_deleted_products WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$conn->close();
header("Location: dashboard.php");
exit;
?>
