<?php
header('Content-Type: application/json');
include 'db_connect.php';
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => $conn->connect_error]);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'No order ID']);
    exit;
}

$id = intval($_GET['id']);

// Step 1: Get order from cart table
$stmt = $conn->prepare("SELECT * FROM cart WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

// Step 2: Insert into recently_deleted
$stmt = $conn->prepare("
    INSERT INTO recently_deleted 
    (order_id, fullname, contact, address, cart, total, status, created_at, deleted_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param(
    "issssdss",
    $order['id'],
    $order['fullname'],
    $order['contact'],
    $order['address'],
    $order['cart'],
    $order['total'],
    $order['status'],
    $order['created_at']
);
$stmt->execute();
$stmt->close();

// Step 3: Delete from cart
$stmt = $conn->prepare("DELETE FROM cart WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
$conn->close();
?>
