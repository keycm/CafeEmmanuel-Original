<?php
include 'session_check.php';
// Connect to the correct database for users
include 'db_connect.php';
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Fetch all users
$sql = "SELECT id, fullname, username, email, role FROM users ORDER BY fullname ASC";
$result = $conn->query($sql);

// Function to get initials from a name for the avatar
function getInitials($name) {
    $words = explode(" ", $name);
    $initials = "";
    foreach ($words as $w) {
        if (!empty($w)) {
            $initials .= strtoupper($w[0]);
        }
    }
    return substr($initials, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Accounts - Saplot de Manila</title>
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
        --yellow-accent: #f1b44c;
    }
    .main-content { background-color: var(--main-bg); }
    .table-card { background: var(--card-bg); padding: 25px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); overflow-x: auto; }
    .card-header { margin-bottom: 20px; }
    .card-header h1 { font-size: 1.5rem; color: var(--text-color); margin: 0; }
    
    .users-table { width: 100%; border-collapse: collapse; }
    .users-table th, .users-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
    .users-table th { font-weight: 600; font-size: 0.8rem; color: var(--subtle-text); text-transform: uppercase; }
    .users-table td { font-size: 0.9rem; }
    .users-table td:nth-child(4) { white-space: nowrap; }
    
    .user-cell { display: flex; align-items: center; gap: 12px; }
    .avatar { flex-shrink: 0; width: 38px; height: 38px; border-radius: 50%; background-color: #e8f0fe; color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-weight: 600; }
    .user-info .fullname { font-weight: 600; color: var(--text-color); }
    .user-info .username { font-size: 0.85rem; color: var(--subtle-text); }
    
    .role-pill { padding: 5px 12px; border-radius: 20px; font-weight: 500; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; }
    .role-user { background-color: #f8f8fb; color: var(--subtle-text); }
    .role-admin { background-color: #e8f0fe; color: var(--primary-color); }
    .role-super_admin { background-color: #fff3e0; color: #e65100; }
    .role-pill i { font-size: 0.75rem; }

    .action-icons { display: inline-flex; gap: 8px; white-space: nowrap; align-items: center; }
    .action-btn { background: none; border: none; cursor: pointer; transition: all 0.2s; white-space: nowrap; width: 70px; text-align: center; }
    .action-btn.edit { background: var(--green-accent); color: white; padding: 6px 0; border-radius: 5px; font-size: 0.8rem; font-weight: 500; text-decoration: none; display: inline-block; }
    .action-btn.edit:hover { background: #2ca87b; }
    .action-btn.delete { background: var(--red-accent); color: white; padding: 4px 0; border-radius: 5px; font-size: 0.8rem; font-weight: 500; text-decoration: none; display: inline-block; }
    .action-btn.delete:hover { background: #e64a4a; }
    .current-user-text { font-style: italic; color: var(--subtle-text); font-size: 0.9rem; white-space: nowrap; }
    .alert { padding: 12px 18px; margin-bottom: 20px; border-radius: 6px; font-size: 0.9rem; }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    
    .add-admin-btn { background: var(--primary-color); color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; transition: all 0.3s; box-shadow: 0 2px 4px rgba(85,110,230,0.3); }
    .add-admin-btn:hover { background: #4758c7; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(85,110,230,0.4); }
    .add-admin-btn i { font-size: 14px; }
    
    /* Modal Styles */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; padding: 20px; }
    .modal-overlay.active { display: flex; }
    .modal-container { background: white; border-radius: 12px; width: 100%; max-width: 450px; max-height: 90vh; overflow-y: auto; overflow-x: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; }
    .modal-header h2 { font-size: 18px; font-weight: 600; color: #212121; margin: 0; }
    .close-modal { background: none; border: none; font-size: 24px; color: #757575; cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: background 0.2s; }
    .close-modal:hover { background: #f5f5f5; }
    .modal-body { padding: 20px 24px 24px 24px; }
    .user-info-modal { background: #f5f7fa; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e8ebed; }
    .user-info-modal .label { font-size: 11px; color: #8b95a5; margin-bottom: 5px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
    .user-info-modal .value { font-size: 15px; font-weight: 600; color: #2d3748; }
    .role-option-modal { background: white; border: 2px solid #e0e4e8; border-radius: 10px; padding: 16px 18px; margin-bottom: 14px; cursor: pointer; transition: all 0.25s ease; position: relative; overflow: hidden; }
    .role-option-modal:hover { border-color: #b8c1cc; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .role-option-modal input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
    .role-option-modal.selected { border-color: #2196F3; background: #f0f7ff; box-shadow: 0 2px 12px rgba(33,150,243,0.15); }
    .role-header-modal { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
    .role-title-modal { font-size: 16px; font-weight: 700; color: #2d3748; display: flex; align-items: center; gap: 8px; }
    .role-title-modal i { font-size: 16px; }
    .check-icon-modal { display: none; color: #2196F3; font-size: 22px; }
    .role-option-modal.selected .check-icon-modal { display: block; }
    .permissions-modal { margin-top: 10px; font-size: 13px; color: #64748b; line-height: 1.7; list-style: none; padding-left: 0; }
    .permissions-modal li { margin-bottom: 6px; padding-left: 2px; }
    .button-group-modal { display: flex; gap: 12px; margin-top: 24px; }
    .btn-modal { flex: 1; padding: 13px 24px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.25s; border: none; text-align: center; }
    .btn-save-modal { background: #2196F3; color: white; box-shadow: 0 2px 8px rgba(33,150,243,0.3); }
    .btn-save-modal:hover { background: #1976D2; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(33,150,243,0.4); }
    .btn-cancel-modal { background: #f1f3f5; color: #495057; border: 1px solid #dee2e6; }
    .btn-cancel-modal:hover { background: #e9ecef; }
</style>
</head>
<body>
<div class="admin-container">
  
  <?php include 'admin_sidebar.php'; ?>

  <main class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h1 style="font-size: 1.5rem; color: var(--text-color); margin: 0;">User Accounts</h1>
      <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
        <button onclick="openAddAdminModal()" class="add-admin-btn">
          <i class="fas fa-plus"></i> Add New Admin
        </button>
      <?php endif; ?>
    </div>

    <?php if (isset($_GET['success'])): ?>
      <div class="alert alert-success">
        <?php 
        if ($_GET['success'] === 'role_changed') {
            echo '<i class="fas fa-check-circle"></i> User role has been updated successfully.';
        } elseif ($_GET['success'] === 'user_deleted') {
            echo '<i class="fas fa-check-circle"></i> User has been deleted successfully.';
        } elseif ($_GET['success'] === 'admin_created') {
            echo '<i class="fas fa-check-circle"></i> Admin account has been created successfully.';
        }
        ?>
      </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['admin_create_error'])): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['admin_create_error']); ?>
      </div>
      <?php unset($_SESSION['admin_create_error']); ?>
    <?php endif; ?>

    <div class="table-card">
        <table class="users-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Email</th>
              <th>Role</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()) : ?>
              <tr>
                <td>
                    <div class="user-cell">
                        <div class="avatar"><?php echo getInitials($row['fullname']); ?></div>
                        <div class="user-info">
                            <div class="fullname"><?php echo htmlspecialchars($row['fullname']); ?></div>
                            <div class="username">@<?php echo htmlspecialchars($row['username']); ?></div>
                        </div>
                    </div>
                </td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td>
                    <?php 
                    $roleClass = 'role-' . $row['role'];
                    $roleIcon = '';
                    $roleLabel = '';
                    
                    switch($row['role']) {
                        case 'super_admin':
                            $roleIcon = '<i class="fas fa-crown"></i>';
                            $roleLabel = 'Super Admin (Owner)';
                            break;
                        case 'admin':
                            $roleIcon = '<i class="fas fa-user-shield"></i>';
                            $roleLabel = 'Admin (Staff)';
                            break;
                        case 'user':
                        default:
                            $roleIcon = '<i class="fas fa-user"></i>';
                            $roleLabel = 'User';
                            break;
                    }
                    ?>
                    <span class="role-pill <?php echo $roleClass; ?>">
                        <?php echo $roleIcon . ' ' . $roleLabel; ?>
                    </span>
                </td>
                <td>
                    <div class="action-icons">
                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin'): ?>
                                <button onclick="openRoleModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['fullname'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['username'], ENT_QUOTES); ?>', '<?php echo $row['role']; ?>')" class="action-btn edit" title="Edit Role">
                                    Edit
                                </button>
                            <?php endif; ?>
                            <a href="user_actions.php?action=delete&id=<?php echo $row['id']; ?>" class="action-btn delete" title="Delete User" onclick="return confirm('Are you sure you want to delete this user?')">
                                Delete
                            </a>
                        <?php else: ?>
                            <span class="current-user-text">(You)</span>
                        <?php endif; ?>
                    </div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
    </div>
  </main>
</div>

<!-- Role Change Modal -->
<div id="roleModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h2>Change Role and Permission</h2>
            <button class="close-modal" onclick="closeRoleModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="user-info-modal">
                <div class="label">Username</div>
                <div class="value" id="modalUserName"></div>
            </div>
            
            <form id="roleChangeForm" method="POST" action="update_role.php">
                <input type="hidden" name="user_id" id="modalUserId">
                
                <!-- User Role -->
                <div class="role-option-modal" id="roleUser" onclick="selectRole('user')">
                    <input type="radio" name="new_role" value="user" id="radio_user">
                    <div class="role-header-modal">
                        <span class="role-title-modal">
                            <i class="fas fa-user"></i>
                            User
                        </span>
                        <i class="fas fa-check-circle check-icon-modal"></i>
                    </div>
                    <ul class="permissions-modal">
                        <li>• Can view and edit their own profile</li>
                        <li>• Can make reservations and manage their own bookings</li>
                        <li>• Can view menu and place orders</li>
                    </ul>
                </div>
                
                <!-- Admin Role -->
                <div class="role-option-modal" id="roleAdmin" onclick="selectRole('admin')">
                    <input type="radio" name="new_role" value="admin" id="radio_admin">
                    <div class="role-header-modal">
                        <span class="role-title-modal">
                            <i class="fas fa-user-shield"></i>
                            Admin (Staff)
                        </span>
                        <i class="fas fa-check-circle check-icon-modal"></i>
                    </div>
                    <ul class="permissions-modal">
                        <li>• Can view and edit all reservations</li>
                        <li>• Can manage services and uploads</li>
                        <li>• Can view reports and manage orders</li>
                        <li>• Cannot manage other admins or users</li>
                    </ul>
                </div>
                
                <!-- Super Admin Role -->
                <div class="role-option-modal" id="roleSuperAdmin" onclick="selectRole('super_admin')">
                    <input type="radio" name="new_role" value="super_admin" id="radio_super_admin">
                    <div class="role-header-modal">
                        <span class="role-title-modal">
                            <i class="fas fa-crown"></i>
                            Super Admin (Owner)
                        </span>
                        <i class="fas fa-check-circle check-icon-modal"></i>
                    </div>
                    <ul class="permissions-modal">
                        <li>• Can manage system settings, user roles, and permissions</li>
                        <li>• Can create, edit, block or delete Admin and User accounts</li>
                        <li>• Full access to all system features</li>
                        <li>• Can view audit logs and system analytics</li>
                    </ul>
                </div>
                
                <div class="button-group-modal">
                    <button type="submit" class="btn-modal btn-save-modal">Save Changes</button>
                    <button type="button" onclick="closeRoleModal()" class="btn-modal btn-cancel-modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add New Admin Modal -->
<div id="addAdminModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h2>Add New Admin</h2>
            <button class="close-modal" onclick="closeAddAdminModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addAdminForm" method="POST" action="create_admin_account.php">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #2d3748; margin-bottom: 6px;">Full Name</label>
                    <input type="text" name="fullname" required style="width: 80%; padding: 10px 12px; border: 2px solid #e0e4e8; border-radius: 6px; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #2d3748; margin-bottom: 6px;">Username</label>
                    <input type="text" name="username" required style="width: 80%; padding: 10px 12px; border: 2px solid #e0e4e8; border-radius: 6px; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #2d3748; margin-bottom: 6px;">Email</label>
                    <input type="email" name="email" required style="width: 80%; padding: 10px 12px; border: 2px solid #e0e4e8; border-radius: 6px; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #2d3748; margin-bottom: 6px;">Contact Number</label>
                    <input type="text" name="contact" pattern="[0-9]{10,15}" placeholder="10-15 digits" style="width: 80%; padding: 10px 12px; border: 2px solid #e0e4e8; border-radius: 6px; font-size: 14px;">
                </div>
                
                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #2d3748; margin-bottom: 6px;">Password</label>
                    <input type="password" name="password" required minlength="8" style="width: 80%; padding: 10px 12px; border: 2px solid #e0e4e8; border-radius: 6px; font-size: 14px;">
                    <small style="color: #64748b; font-size: 12px; display: block; margin-top: 4px;">At least 8 characters with 1 capital letter</small>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #2d3748; margin-bottom: 6px;">Role</label>
                    <select name="role" required style="width: 87%; padding: 10px 12px; border: 2px solid #e0e4e8; border-radius: 6px; font-size: 14px;">
                        <option value="admin">Admin (Staff)</option>
                        <option value="super_admin">Super Admin (Owner)</option>
                    </select>
                </div>
                
                <div class="button-group-modal">
                    <button type="submit" class="btn-modal btn-save-modal">Create Admin</button>
                    <button type="button" onclick="closeAddAdminModal()" class="btn-modal btn-cancel-modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRoleModal(userId, fullname, username, currentRole) {
    document.getElementById('modalUserId').value = userId;
    document.getElementById('modalUserName').textContent = fullname + ' (@' + username + ')';
    
    // Remove all selected classes
    document.querySelectorAll('.role-option-modal').forEach(opt => opt.classList.remove('selected'));
    
    // Uncheck all radios
    document.querySelectorAll('input[name="new_role"]').forEach(radio => radio.checked = false);
    
    // Set current role as selected
    const roleMap = {
        'user': 'roleUser',
        'admin': 'roleAdmin',
        'super_admin': 'roleSuperAdmin'
    };
    
    if (roleMap[currentRole]) {
        const roleElement = document.getElementById(roleMap[currentRole]);
        roleElement.classList.add('selected');
        const radioId = 'radio_' + currentRole;
        document.getElementById(radioId).checked = true;
    }
    
    document.getElementById('roleModal').classList.add('active');
}

function closeRoleModal() {
    document.getElementById('roleModal').classList.remove('active');
}

function selectRole(role) {
    // Remove all selected classes
    document.querySelectorAll('.role-option-modal').forEach(option => {
        option.classList.remove('selected');
    });
    
    // Uncheck all radios
    document.querySelectorAll('input[name="new_role"]').forEach(radio => radio.checked = false);
    
    // Select the clicked role
    const roleMap = {
        'user': 'roleUser',
        'admin': 'roleAdmin',
        'super_admin': 'roleSuperAdmin'
    };
    
    if (roleMap[role]) {
        document.getElementById(roleMap[role]).classList.add('selected');
        document.getElementById('radio_' + role).checked = true;
    }
}

// Close modal when clicking outside
document.getElementById('roleModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRoleModal();
    }
});

// Add New Admin Modal Functions
function openAddAdminModal() {
    document.getElementById('addAdminModal').classList.add('active');
    document.getElementById('addAdminForm').reset();
}

function closeAddAdminModal() {
    document.getElementById('addAdminModal').classList.remove('active');
}

// Close add admin modal when clicking outside
document.getElementById('addAdminModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddAdminModal();
    }
});
</script>
</body>
</html>
<?php $conn->close(); ?>
