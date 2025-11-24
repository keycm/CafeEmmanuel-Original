<?php
include 'session_check.php';
include 'db_connect.php';
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Handle actions (Mark as Read / Delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);
    if ($action == 'read') {
        $stmt = $conn->prepare("UPDATE inquiries SET status = 'Read' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    } elseif ($action == 'delete') {
        $stmt = $conn->prepare("DELETE FROM inquiries WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: admin_inquiries.php");
    exit();
}

$inquiries_result = $conn->query("SELECT * FROM inquiries ORDER BY received_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Customer Inquiries</title>
<link rel="stylesheet" href="CSS/admin.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet">
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

  .inquiries-list {
      display: flex;
      flex-direction: column;
      gap: 20px;
  }

  .inquiry-card {
      background: var(--card-bg);
      border-radius: 8px;
      box-shadow: 0 1px 2px rgba(0,0,0,0.05);
      border-left: 4px solid transparent;
      transition: border-color 0.3s;
  }
  .inquiry-card.status-new {
      border-left-color: var(--primary-color);
  }
  .inquiry-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 20px;
      border-bottom: 1px solid var(--border-color);
  }
  .inquiry-sender {
      font-weight: 600;
      color: var(--text-color);
  }
  .inquiry-sender .email {
      font-weight: 400;
      color: var(--subtle-text);
      font-size: 0.9rem;
      margin-left: 8px;
  }
  .inquiry-meta {
      font-size: 0.85rem;
      color: var(--subtle-text);
  }
  .inquiry-body {
      padding: 20px;
      line-height: 1.7;
      color: var(--text-color);
  }
  .inquiry-actions {
      display: flex;
      gap: 10px;
      padding: 0 20px 15px;
  }
  .btn-action {
      padding: 8px 15px;
      border-radius: 6px;
      border: 1px solid var(--border-color);
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.2s, color 0.2s;
      font-size: 0.85rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background-color: var(--card-bg);
      color: var(--text-color);
  }
  .btn-action:hover {
      background-color: #f8f8fb;
  }
  .btn-action.btn-read {
      color: var(--green-accent);
      border-color: var(--green-accent);
  }
  .btn-action.btn-read:hover {
      background-color: var(--green-accent);
      color: #fff;
  }
  .btn-action.btn-delete {
      color: var(--red-accent);
      border-color: var(--red-accent);
  }
  .btn-action.btn-delete:hover {
      background-color: var(--red-accent);
      color: #fff;
  }
  .no-inquiries {
      background: var(--card-bg);
      padding: 40px;
      border-radius: 8px;
      text-align: center;
      color: var(--subtle-text);
  }
</style>
</head>
<body>
<div class="admin-container">
  <?php include 'admin_sidebar.php'; ?>
  <main class="main-content">
    <header class="card-header">
      <h1>Customer Inquiries</h1>
    </header>

    <div class="inquiries-list">
        <?php if ($inquiries_result->num_rows > 0): ?>
            <?php while ($row = $inquiries_result->fetch_assoc()): ?>
                <div class="inquiry-card <?php echo strtolower($row['status']) === 'new' ? 'status-new' : '' ?>">
                    <div class="inquiry-header">
                        <div class="inquiry-sender">
                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                            <span class="email">&lt;<?php echo htmlspecialchars($row['email']); ?>&gt;</span>
                        </div>
                        <div class="inquiry-meta">
                            Received: <?php echo date("M d, Y h:i A", strtotime($row['received_at'])); ?>
                        </div>
                    </div>
                    <div class="inquiry-body">
                        <?php echo nl2br(htmlspecialchars($row['message'])); ?>
                    </div>
                    <div class="inquiry-actions">
                        <?php if (strtolower($row['status']) === 'new'): ?>
                            <a href="?action=read&id=<?php echo $row['id']; ?>" class="btn-action btn-read"><i class="fas fa-check-circle"></i> Mark as Read</a>
                        <?php endif; ?>
                        <a href="?action=delete&id=<?php echo $row['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this inquiry?')"><i class="fas fa-trash-alt"></i> Delete</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-inquiries">
                <p>You have no new inquiries.</p>
            </div>
        <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>