<?php
include 'session_check.php';
require_once 'audit_log.php';

// Only super_admin can view audit logs
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

include 'db_connect.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filter parameters
$filter_action = $_GET['action_filter'] ?? ''; // Use non-null default
$limit = 100;

// Get audit logs
$logs = getAuditLogs($conn, $limit, null, $filter_action === '' ? null : $filter_action);

// --- MODIFICATION: Get all unique actions from the DB for the filter dropdown ---
$actions_result = $conn->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");
$available_actions = [];
if ($actions_result) {
    while ($row = $actions_result->fetch_assoc()) {
        $available_actions[] = $row['action'];
    }
}
// --- END OF MODIFICATION ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Audit Logs - Cafe Emmanuel</title>
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
        --blue-accent: #2196F3;
        --yellow-accent: #f1b44c;
        --purple-accent: #6a1b9a;
        --orange-accent: #e65100;
    }
    .main-content { background-color: var(--main-bg); }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .page-header h1 { font-size: 1.5rem; color: var(--text-color); margin: 0; }
    
    .filters { background: var(--card-bg); padding: 20px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; gap: 15px; align-items: center; }
    .filters select { padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.9rem; }
    .filters label { font-weight: 500; color: var(--text-color); }
    
    .table-card { background: var(--card-bg); padding: 25px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .logs-table { width: 100%; border-collapse: collapse; }
    .logs-table th, .logs-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
    .logs-table th { font-weight: 600; font-size: 0.8rem; color: var(--subtle-text); text-transform: uppercase; }
    .logs-table td { font-size: 0.9rem; }
    
    .action-badge { padding: 5px 12px; border-radius: 20px; font-weight: 500; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; text-transform: capitalize; }
    .action-badge.create { background-color: #e8f5e9; color: #2e7d32; }
    .action-badge.update { background-color: #e3f2fd; color: #1565c0; }
    .action-badge.delete { background-color: #ffebee; color: #c62828; }
    .action-badge.restore { background-color: #fff3e0; color: var(--orange-accent); }
    .action-badge.role { background-color: #f3e5f5; color: var(--purple-accent); }
    .action-badge.order { background-color: #e8f0fe; color: var(--primary-color); }
    .action-badge.login { background-color: #e0f2f1; color: #00695c; }
    .action-badge.stock { background-color: #fce4ec; color: #ad1457; }
    .action-badge.inquiry { background-color: #e0f7fa; color: #006064; }
    .action-badge.failed { background-color: #fdeeee; color: var(--red-accent); }
    .action-badge.default { background-color: #f5f5f5; color: #616161; }
    .action-badge i { font-size: 0.75rem; }
    
    .time-cell { color: var(--subtle-text); font-size: 0.85rem; }
    .admin-cell { font-weight: 500; color: var(--text-color); }
    .description-cell { color: var(--subtle-text); max-width: 400px; white-space: pre-wrap; word-break: break-word; }
</style>
</head>
<body>
<div class="admin-container">
    <?php include 'admin_sidebar.php'; ?>
    
    <main class="main-content">
        <header class="page-header">
            <h1><i class="fas fa-clipboard-list"></i> Audit Logs</h1>
        </header>
        
        <div class="filters">
            <label for="actionFilter">Filter by Action:</label>
            <select id="actionFilter" onchange="filterByAction(this.value)">
                <option value="">All Actions</option>
                <?php foreach ($available_actions as $action): ?>
                    <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $filter_action === $action ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars(str_replace('_', ' ', ucwords($action, '_'))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="table-card">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="time-cell"><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                                <td class="admin-cell"><?php echo htmlspecialchars($log['admin_name']); ?></td>
                                <td>
                                    <?php
                                    $action = $log['action'];
                                    $badge_class = 'default';
                                    $icon = 'fa-info-circle';
                                    
                                    if (strpos($action, 'create') !== false || strpos($action, 'created') !== false) {
                                        $badge_class = 'create'; $icon = 'fa-plus';
                                    } elseif (strpos($action, 'update') !== false) {
                                        $badge_class = 'update'; $icon = 'fa-edit';
                                    } elseif (strpos($action, 'delete') !== false) {
                                        $badge_class = 'delete'; $icon = 'fa-trash';
                                    } elseif (strpos($action, 'restore') !== false) {
                                        $badge_class = 'restore'; $icon = 'fa-undo';
                                    } elseif (strpos($action, 'role') !== false) {
                                        $badge_class = 'role'; $icon = 'fa-user-shield';
                                    } elseif (strpos($action, 'order') !== false) {
                                        $badge_class = 'order'; $icon = 'fa-shopping-bag';
                                    } elseif (strpos($action, 'login') !== false) {
                                        $badge_class = 'login'; $icon = 'fa-key';
                                    } elseif (strpos($action, 'stock') !== false) {
                                        $badge_class = 'stock'; $icon = 'fa-warehouse';
                                    } elseif (strpos($action, 'inquiry') !== false) {
                                        $badge_class = 'inquiry'; $icon = 'fa-envelope';
                                    } elseif (strpos($action, 'failed') !== false) {
                                        $badge_class = 'failed'; $icon = 'fa-exclamation-triangle';
                                    }
                                    
                                    $display_action = str_replace('_', ' ', $action);
                                    ?>
                                    <span class="action-badge <?php echo $badge_class; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                        <?php echo $display_action; ?>
                                    </span>
                                </td>
                                <td class="description-cell"><?php echo htmlspecialchars($log['description']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: #999;">
                                <i class="fas fa-clipboard-list" style="font-size: 48px; margin-bottom: 12px; display: block; opacity: 0.3;"></i>
                                No audit logs found<?php echo $filter_action ? ' for this filter' : ''; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
function filterByAction(action) {
    const url = new URL(window.location.href);
    if (action) {
        url.searchParams.set('action_filter', action);
    } else {
        url.searchParams.delete('action_filter');
    }
    window.location.href = url.toString();
}
</script>
</body>
</html>
<?php $conn->close(); ?>