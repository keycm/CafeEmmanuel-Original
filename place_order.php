<?php
session_start();
// Database connection
$conn = new mysqli("localhost", "root", "", "addproduct");
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "DB Connection failed"]));
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

$fullname = $data['fullname'];
$contact = $data['contact'];
$address = $data['address'];
$payment = $data['payment'];
$items = $data['items'];
$total = $data['total'];
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Validate inputs
if (empty($fullname) || empty($contact) || empty($address) || empty($payment) || empty($items)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$conn->begin_transaction();

// Detect if orders.user_id column exists
$hasUserId = false;
if ($res = $conn->query("SHOW COLUMNS FROM orders LIKE 'user_id'")) {
    $hasUserId = $res->num_rows > 0;
}

// Insert order (with or without user_id)
if ($hasUserId) {
    $orderStmt = $conn->prepare("INSERT INTO orders (fullname, contact, address, payment, total, created_at, user_id) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
    $orderStmt->bind_param("ssssdi", $fullname, $contact, $address, $payment, $total, $userId);
} else {
    $orderStmt = $conn->prepare("INSERT INTO orders (fullname, contact, address, payment, total, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $orderStmt->bind_param("ssssd", $fullname, $contact, $address, $payment, $total);
}

if (!$orderStmt->execute()) {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => "Failed to create order"]);
    exit;
}
$order_id = $orderStmt->insert_id;

// Prepare statements
$itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
$updateStock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
$checkStock = $conn->prepare("SELECT stock FROM products WHERE id = ?");

// Loop through items
foreach ($items as $item) {
    $product_id = (int)$item['id'];
    $quantity = (int)$item['quantity'];
    $price = (float)$item['price'];

    // Check stock availability
    $checkStock->bind_param("i", $product_id);
    $checkStock->execute();
    $result = $checkStock->get_result();
    $row = $result->fetch_assoc();

    if (!$row || $row['stock'] < $quantity) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Insufficient stock for product ID $product_id"]);
        exit;
    }

    // Insert order item
    $itemStmt->bind_param("iiid", $order_id, $product_id, $quantity, $price);
    if (!$itemStmt->execute()) { $conn->rollback(); echo json_encode(["status"=>"error","message"=>"Failed to add item"]); exit; }

    // Deduct stock
    $updateStock->bind_param("ii", $quantity, $product_id);
    if (!$updateStock->execute()) { $conn->rollback(); echo json_encode(["status"=>"error","message"=>"Failed to update stock"]); exit; }
}

$conn->commit();
echo json_encode(["status" => "success", "message" => "Order placed successfully!", "order_id" => $order_id]);

?>
