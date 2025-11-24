<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json'); // Return JSON

session_start();
include 'db_connect.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (
    !$data ||
    empty($data['fullname']) ||
    empty($data['contact']) ||
    empty($data['address']) ||
    empty($data['cart']) ||
    !isset($data['total'])
) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid input."]);
    exit;
}

$fullname = $conn->real_escape_string($data['fullname']);
$contact  = $conn->real_escape_string($data['contact']);
$address  = $conn->real_escape_string($data['address']);
$cart     = $data['cart']; // Array of cart items
$total    = floatval($data['total']);

// Check stock before saving
foreach ($cart as $item) {
    $quantity = intval($item['quantity'] ?? 0);
    if ($quantity <= 0) continue;

    if (isset($item['id'])) {
        $product_id = intval($item['id']);
        $res = $conn->query("SELECT stock, name FROM products WHERE id = $product_id LIMIT 1");
    } elseif (isset($item['name'])) {
        $name = $conn->real_escape_string($item['name']);
        $res = $conn->query("SELECT id, stock, name FROM products WHERE name = '$name' LIMIT 1");
    } else {
        continue;
    }

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $stock = intval($row['stock']);
        if ($stock <= 0) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Product '{$row['name']}' is out of stock."
            ]);
            exit;
        } elseif ($stock < $quantity) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "error" => "Not enough stock for '{$row['name']}'. Only $stock left."
            ]);
            exit;
        }
    }
}

// Insert into cart table (instead of orders)
$hasUserId = false;
if ($res = $conn->query("SHOW COLUMNS FROM cart LIKE 'user_id'")) { $hasUserId = $res->num_rows > 0; }
$cart_json = json_encode($cart);
if ($hasUserId && isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO cart (fullname, contact, address, cart, total, status, created_at, user_id) VALUES (?, ?, ?, ?, ?, 'Pending', NOW(), ?)");
    $stmt->bind_param("ssssdi", $fullname, $contact, $address, $cart_json, $total, $uid);
} else {
    $stmt = $conn->prepare("INSERT INTO cart (fullname, contact, address, cart, total, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
    $stmt->bind_param("ssssd", $fullname, $contact, $address, $cart_json, $total);
}

if ($stmt->execute()) {
    // Deduct stock in products table
    foreach ($cart as $item) {
        $quantity = intval($item['quantity'] ?? 0);
        if ($quantity <= 0) continue;

        if (isset($item['id'])) {
            $product_id = intval($item['id']);
        } elseif (isset($item['name'])) {
            $name = $conn->real_escape_string($item['name']);
            $res = $conn->query("SELECT id FROM products WHERE name = '$name' LIMIT 1");
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $product_id = intval($row['id']);
            } else {
                continue;
            }
        }

        // Deduct stock
        if ($product_id) {
            $conn->query("UPDATE products SET stock = stock - $quantity WHERE id = $product_id");
        }
    }

    echo json_encode(["success" => true, "message" => "Order saved in cart successfully."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $conn->error]);
}

$conn->close();
?>
