<?php
// This line gets the current page's filename, e.g., "Dashboard.php"
$current_page = basename($_SERVER['SCRIPT_NAME']);
require_once __DIR__ . '/config.php';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Birthstone+Bounce:wght@500&display=swap" rel="stylesheet">

<aside class="sidebar">
   <a href="Dashboard.php" class="sidebar-logo-link">
       <div class="sidebar-logo-text">
            <span class="logo-main">Cafe</span>
            <span class="logo-sub">Emmanuel</span>
       </div>
   </a>

    <nav class="sidebar-nav">
        <!-- Dashboard - All admins can access -->
        <a href="Dashboard.php" class="nav-item <?php if ($current_page == 'Dashboard.php') echo 'active'; ?>">
            <i class="fas fa-home fa-fw"></i><span>Dashboard</span>
        </a>
        
        <!-- Reports - All admins can access -->
        <a href="Sales.php" class="nav-item <?php if ($current_page == 'Sales.php') echo 'active'; ?>">
            <i class="fas fa-chart-bar fa-fw"></i><span>Reports</span>
        </a>
        
        <!-- Orders - All admins can access -->
        <a href="Orders.php" class="nav-item <?php if ($current_page == 'Orders.php') echo 'active'; ?>">
            <i class="fas fa-box-open fa-fw"></i><span>Orders</span>
        </a>
        
        <!-- Inquiries - All admins can access -->
        <a href="admin_inquiries.php" class="nav-item <?php if ($current_page == 'admin_inquiries.php') echo 'active'; ?>">
            <i class="fas fa-envelope fa-fw"></i><span>Inquiries</span>
        </a>
        
        <!-- Manage Stock - All admins can access -->
        <a href="add_stock.php" class="nav-item <?php if ($current_page == 'add_stock.php') echo 'active'; ?>">
            <i class="fas fa-warehouse fa-fw"></i><span>Manage Stock</span>
        </a>
        
        <!-- Manage Products - All admins can access -->
        <a href="practiceaddproduct.php" class="nav-item <?php if ($current_page == 'practiceaddproduct.php') echo 'active'; ?>">
            <i class="fas fa-tasks fa-fw"></i><span>Manage Products</span>
        </a>
        
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
        <!-- User Accounts - Super Admin only -->
        <a href="user_accounts.php" class="nav-item <?php if ($current_page == 'user_accounts.php') echo 'active'; ?>">
            <i class="fas fa-users fa-fw"></i><span>User Accounts</span>
        </a>
        
        <!-- Recycle Bin - Super Admin only -->
        <a href="recently_deleted.php" class="nav-item <?php if ($current_page == 'recently_deleted.php') echo 'active'; ?>">
            <i class="fas fa-trash-alt fa-fw"></i><span>Recycle Bin</span>
        </a>
        
        <!-- Audit Logs - Super Admin only -->
        <a href="audit_logs_page.php" class="nav-item <?php if ($current_page == 'audit_logs_page.php') echo 'active'; ?>">
            <i class="fas fa-clipboard-list fa-fw"></i><span>Audit Logs</span>
        </a>
        <?php endif; ?>
        
        <a href="logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt fa-fw"></i><span>Log Out</span>
        </a>
    </nav>
</aside>
