<?php
include 'session_check.php';

// --- Database Connection ---
include 'db_connect.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Fetch Recent Customers ---
$customers_result = $conn->query("SELECT * FROM cart WHERE status = 'Delivered' ORDER BY created_at DESC LIMIT 5"); // FIX: Changed to 'Delivered'
$customers_data = [];
while ($row = $customers_result->fetch_assoc()) {
    $customers_data[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sales Reports</title>
  <link rel="stylesheet" href="CSS/admin.css"/>
  <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
        --main-bg: #f8f8fb;
        --card-bg: #ffffff;
        --text-color: #495057;
        --subtle-text: #74788d;
        --border-color: #eff2f7;
        --chart-blue-1: #556ee6;
        --chart-blue-2: #8297f0;
        --chart-blue-3: #acbcf5;
        --chart-blue-4: #d6dff9;
    }
    .main-content {
        background-color: var(--main-bg);
    }
    .main-header {
        background: none;
        box-shadow: none;
        padding: 0;
    }
    .charts-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }
    .chart-card, .table-card {
        background: var(--card-bg);
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .card-header h2 {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0;
        color: var(--text-color);
    }
    .date-dropdown {
        border: 1px solid var(--border-color);
        padding: 6px 12px;
        border-radius: 6px;
        font-family: 'Poppins';
        color: var(--subtle-text);
    }
    .customers-table {
        width: 100%;
        border-collapse: collapse;
    }
    .customers-table th, .customers-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }
    .customers-table th {
        font-weight: 600;
        font-size: 0.8rem;
        color: var(--subtle-text);
        text-transform: uppercase;
    }
    .customers-table td {
        font-size: 0.9rem;
        color: var(--text-color);
    }
    .customers-table tbody tr:last-child td {
        border-bottom: none;
    }

    @media (max-width: 992px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }
  </style>
</head>
<body>
  <div class="admin-container">
    
    <?php include 'admin_sidebar.php'; ?>

    <main class="main-content">
        <header class="main-header">
            <h1>Monthly Reports</h1>
            <div class="header-icons">
                <i class="fas fa-envelope"></i>
                <i class="fas fa-bell"></i>
                <img src="logo.png" alt="Admin Profile">
            </div>
        </header>
        
        <div class="charts-grid">
            <div class="chart-card">
                <canvas id="sales-chart"></canvas>
            </div>
            <div class="chart-card">
                <canvas id="sales-order-chart"></canvas>
            </div>
        </div>

        <div class="table-card">
            <div class="card-header">
                <h2>Recent Customers</h2>
                <select class="date-dropdown">
                    <option>Last 30 Days</option>
                    <option>Last 6 Months</option>
                </select>
            </div>
            <table class="customers-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Customer Order</th>
                        <th>Total Bill</th>
                        <th>Billed on</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($customers_data)): ?>
                        <?php foreach ($customers_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($row['address']); ?></td>
                                <td>
                                    <?php
                                        $cart_items = json_decode($row['cart'], true);
                                        if ($cart_items) {
                                            $item_names = [];
                                            foreach($cart_items as $item) {
                                                $item_names[] = $item['quantity'] . ' x ' . $item['name'];
                                            }
                                            echo implode(', ', $item_names);
                                        }
                                    ?>
                                </td>
                                <td>₱<?php echo number_format($row['total'], 2); ?></td>
                                <td><?php echo date("d M Y", strtotime($row['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No recent customer data found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
  </div>

<script>
  window.onload = function () {
    // --- Bar Chart ---
    fetch('get_sales_data.php')
      .then(response => response.json())
      .then(data => {
        const ctx1 = document.getElementById("sales-chart").getContext("2d");
        new Chart(ctx1, { 
          type: "bar",
          data: { 
            labels: data.labels, 
            datasets: [
              { 
                label: "Revenue", 
                data: data.values, 
                backgroundColor: 'rgba(85, 110, 230, 0.8)',
                borderColor: 'rgba(85, 110, 230, 1)',
                borderWidth: 1
              }
            ] 
          }, 
          options: { 
            responsive: true,
            plugins: { legend: { display: false } }, 
            scales: { 
                y: { beginAtZero: true },
                x: { grid: { display: false } }
            } 
          } 
        });
      });

    // --- Doughnut Chart ---
    fetch('get_top_selling.php')
      .then(response => response.json())
      .then(result => {
        if (result.success && result.data.length > 0) { // Check if data exists
          const labels = result.data.map(item => item.product_name);
          const data = result.data.map(item => item.total_sold);
          
          const ctx2 = document.getElementById("sales-order-chart").getContext("2d");
          new Chart(ctx2, { 
            type: "doughnut", 
            data: { 
              labels: labels, 
              datasets: [{ 
                data: data, 
                // FIX: Updated colors to match your image
                backgroundColor: [
                    "#4C5AA8", // Dark Blue/Purple
                    "#F04A46", // Red
                    "#13C0CE", // Bright Cyan
                    "#2596F8", // Bright Blue
                    "#98C053"  // Light Green
                ],
                borderColor: 'var(--card-bg)',
                borderWidth: 4
              }] 
            }, 
            options: { 
              responsive: true, 
              maintainAspectRatio: false,
              plugins: { 
                  legend: { display: true, position: 'bottom' }
              } 
            } 
          });
        } else {
            // Optional: Show a message if no data
            const ctx2 = document.getElementById("sales-order-chart").getContext("2d");
            ctx2.font = "16px 'Poppins'";
            ctx2.fillStyle = "#74788d";
            ctx2.textAlign = "center";
            ctx2.fillText("No top-selling products found.", ctx2.canvas.width / 2, ctx2.canvas.height / 2);
        }
      });
  };
</script>
</body>
</html>