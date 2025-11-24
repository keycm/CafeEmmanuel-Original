<?php
include 'db_connect.php';

if ($conn->connect_error) {
  die(json_encode(["success" => false, "error" => "DB connection failed."]));
}

$data = json_decode(file_get_contents("php://input"), true);

$fullname = $conn->real_escape_string($data['fullname']);
$contact = $conn->real_escape_string($data['contact']);
$address = $conn->real_escape_string($data['address']);
$total = (float)$data['total'];
$cartItems = json_encode($data['cart'], JSON_UNESCAPED_UNICODE);

$sql = "INSERT INTO orders (fullname, contact, address, cart_items, total, order_date)
        VALUES ('$fullname', '$contact', '$address', '$cartItems', $total, NOW())";

if ($conn->query($sql)) {
  echo json_encode(["success" => true]);
} else {
  echo json_encode(["success" => false, "error" => $conn->error]);
}

$conn->close();
?>