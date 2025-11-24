<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $amount = $_POST['amount'] ?? 0;

  if ($amount < 100) {
    die("Amount must be at least PHP 100.00");
  }

  $secret_key = 'sk_test_4wnAfmzuwJANdZP9sB8Zxf1o';

  // --- DYNAMIC URL DETECTION ---
  // This tries to automatically find your current folder (e.g., localhost/saplot or localhost/CafeEmmanuel)
  $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
  $host = $_SERVER['HTTP_HOST']; // localhost
  $path = dirname($_SERVER['PHP_SELF']); // /saplot/Code (or similar)
  $base_url = $protocol . "://" . $host . $path;
  
  // If you want to hardcode it, uncomment and edit this line:
  // $base_url = "http://localhost/saplot"; 

  $data = [
    "data" => [
      "attributes" => [
        "line_items" => [[
          "currency" => "PHP",
          "amount" => intval($amount * 100), // Convert to centavos
          "name" => "CafeEmmanuel Order",
          "quantity" => 1
        ]],
        "payment_method_types" => ["gcash"],
        "description" => "GCash Order from Cafe Emmanuel",
        "success_url" => $base_url . "/success.html", // Redirect to success.html in the same folder
        "cancel_url" => $base_url . "/product.php"   // Redirect back to menu if failed
      ]
    ]
  ];

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://api.paymongo.com/v1/checkout_sessions");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ":");
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

  $response = curl_exec($ch);
  curl_close($ch);

  $result = json_decode($response, true);
  $checkout_url = $result['data']['attributes']['checkout_url'] ?? null;

  if ($checkout_url) {
    header("Location: $checkout_url");
    exit;
  } else {
    echo "Error creating GCash payment. Please try again.<br>";
    echo "PayMongo Response: ";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
  }
}
?>