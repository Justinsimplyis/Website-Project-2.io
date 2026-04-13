<?php
session_start();
include 'db_connection.php';

// Only admin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

 $username = $_SESSION['username'] ?? 'Admin';
 $admin_id = $_SESSION['user_id'] ?? 0;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    // Update role (requires admin password verification)
    if ($action === 'update_role') {
        $user_id = $_POST['user_id'];
        $role = $_POST['role'];
        $current_password = $_POST['current_password'];

        // Verify admin password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $admin_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result || !password_verify($current_password, $result['password'])) {
            echo "Incorrect password";
            exit();
        }

        // Update role
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $role, $user_id);
        $stmt->execute();
        echo $stmt->affected_rows > 0 ? "success" : "failed";
        exit();
    }

    // Block user
    if ($action === 'block_user') {
        $user_id = $_POST['user_id'];
        $stmt = $conn->prepare("UPDATE users SET blocked = 1 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        echo $stmt->affected_rows > 0 ? "success" : "failed";
        exit();
    }

    // Unblock user
    if ($action === 'unblock_user') {
        $user_id = $_POST['user_id'];
        $stmt = $conn->prepare("UPDATE users SET blocked = 0 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        echo $stmt->affected_rows > 0 ? "success" : "failed";
        exit();
    }

    // Delete user
    if ($action === 'delete_user') {
        $user_id = $_POST['user_id'];
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        echo $stmt->affected_rows > 0 ? "success" : "failed";
        exit();
    }

    // Notify user
    if ($action === 'notify_user') {
        $recipient_id = $_POST['recipient_id'];
        $message = $_POST['message'];
        $sender_id = $admin_id;
        $stmt = $conn->prepare("INSERT INTO notifications (recipient_id, sender_id, type, message) VALUES (?, ?, 'admin', ?)");
        $stmt->bind_param("iis", $recipient_id, $sender_id, $message);
        $stmt->execute();
        echo $stmt->affected_rows > 0 ? "success" : "failed";
        exit();
    }
    if ($action === 'add_user') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = $_POST['role'];

        if ($password !== $confirm_password) {
            echo "Passwords do not match";
            exit();
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo "Email already exists";
            exit();
        }

        // Insert user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $hashedPassword, $role);
        $stmt->execute();

        // Create default profile
        $user_id = $stmt->insert_id;
        $stmt2 = $conn->prepare("INSERT INTO users_profile (user_id) VALUES (?)");
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();

        echo $stmt->affected_rows > 0 ? "success" : "failed";
        exit();
    }
}

// Fetch users
 $users = $conn->query("SELECT id, username, email, role, is_logged_in, blocked FROM users ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg,#0f2027,#203a43,#2c5364);
            color:white;
            min-height:100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .glass-container {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
            border-radius:20px;
            box-shadow: 0 0 25px rgba(0,255,200,0.3);
            padding:30px;
            margin:30px auto;
            max-width:1200px;
        }

        .glass-nav {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding:15px 30px;
            border-radius:15px;
            margin:20px auto;
            max-width:1200px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            box-shadow: 0 0 20px rgba(0,255,200,0.2);
        }

        .glass-table {
            background: rgba(255,255,255,0.05);
            border-radius:15px;
            overflow:hidden;
            box-shadow: 0 0 15px rgba(0,255,200,0.1);
        }

        .glass-table thead {
            background: rgba(255,255,255,0.1);
        }

        .glass-table th, .glass-table td {
            border-color: rgba(255,255,255,0.1);
            padding:12px 15px;
        }

        .glass-table tbody tr {
            transition: all 0.3s ease;
        }

        .glass-table tbody tr:hover {
            background: rgba(255,255,255,0.05);
        }

        .glass-btn {
            background: rgba(255,255,255,0.1);
            border:1px solid rgba(255,255,255,0.2);
            color:white;
            padding:8px 15px;
            border-radius:8px;
            font-size:14px;
            transition: all 0.3s ease;
            margin:2px;
        }

        .glass-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,255,200,0.3);
            color:white;
        }

        .glass-btn-primary {
            background: rgba(0,200,255,0.3);
            border:1px solid rgba(0,200,255,0.5);
        }

        .glass-btn-primary:hover {
            background: rgba(0,200,255,0.5);
        }

        .glass-btn-success {
            background: rgba(0,255,100,0.3);
            border:1px solid rgba(0,255,100,0.5);
        }

        .glass-btn-success:hover {
            background: rgba(0,255,100,0.5);
        }

        .glass-btn-danger {
            background: rgba(255,50,50,0.3);
            border:1px solid rgba(255,50,50,0.5);
        }

        .glass-btn-danger:hover {
            background: rgba(255,50,50,0.5);
        }

        .glass-btn-warning {
            background: rgba(255,200,0,0.3);
            border:1px solid rgba(255,200,0,0.5);
        }

        .glass-btn-warning:hover {
            background: rgba(255,200,0,0.5);
        }

        .glass-btn-info {
            background: rgba(0,150,255,0.3);
            border:1px solid rgba(0,150,255,0.5);
        }

        .glass-btn-info:hover {
            background: rgba(0,150,255,0.5);
        }

        .glass-form-control {
            background: rgba(255,255,255,0.1);
            border:1px solid rgba(255,255,255,0.2);
            color:white;
            border-radius:8px;
            padding:10px 15px;
        }

        .glass-form-control:focus {
            background: rgba(255,255,255,0.15);
            border-color: rgba(0,255,200,0.5);
            box-shadow: 0 0 10px rgba(0,255,200,0.3);
            color:white;
        }

        .glass-form-control::placeholder {
            color: rgba(255,255,255,0.6);
        }

        .glass-modal-content {
            background: rgba(20,30,40,0.95);
            backdrop-filter: blur(10px);
            border:1px solid rgba(255,255,255,0.1);
            border-radius:15px;
            color:white;
        }

        .glass-modal-header {
            border-bottom:1px solid rgba(255,255,255,0.1);
        }

        .glass-modal-footer {
            border-top:1px solid rgba(255,255,255,0.1);
        }

        .glass-select {
            background: rgba(255,255,255,0.1);
            border:1px solid rgba(255,255,255,0.2);
            color:white;
            border-radius:8px;
            padding:8px 15px;
        }

        .glass-select option {
            background: #203a43;
            color:white;
        }

        .badge {
            font-size: 0.8em;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .glass-title {
            text-align: center;
            margin-bottom:30px;
            font-weight: 600;
            text-shadow: 0 0 10px rgba(0,255,200,0.5);
        }

        .glass-back-btn {
            background: rgba(255,100,0,0.3);
            border:1px solid rgba(255,100,0,0.5);
            color:white;
            padding:8px 15px;
            border-radius:8px;
            font-size:14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .glass-back-btn:hover {
            background: rgba(255,100,0,0.5);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,100,0,0.3);
            color:white;
            text-decoration: none;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(0,255,200,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .add-user-btn {
            margin-bottom: 20px;
        }

        .modal-backdrop {
            background: rgba(0,0,0,0.7);
        }
    </style>
</head>
<body>
<!-- Glass Navigation -->
<div class="glass-nav">
    <h2 class="mb-0">Manage Users</h2>
    <div class="admin-info">
        <div class="admin-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
        <div>
            <div style="font-weight: bold;"><?php echo htmlspecialchars($username); ?></div>
            <div style="font-size: 12px; opacity: 0.7;">Administrator</div>
        </div>
    </div>
    <a href="admin_dashboard.php" class="glass-back-btn">
        <i class="fa fa-arrow-left me-2"></i> Back to Dashboard
    </a>
</div>

<!-- Main Content -->
<div class="glass-container">
    <h2 class="glass-title">User Management</h2>
    
    <!-- Add New User Button -->
    <div class="add-user-btn">
        <button class="glass-btn glass-btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fa fa-plus me-2"></i> Add New User
        </button>
    </div>

    <!-- Users Table -->
    <div class="table-responsive">
        <table class="table glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="usersTable">
            <?php while($user = $users->fetch_assoc()): ?>
                <tr id="user-<?php echo $user['id']; ?>">
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <select class="glass-select roleSelect" data-user-id="<?php echo $user['id']; ?>">
                            <option value="user" <?php if($user['role']=='user') echo 'selected'; ?>>User</option>
                            <option value="moderator" <?php if($user['role']=='moderator') echo 'selected'; ?>>Moderator</option>
                            <option value="admin" <?php if($user['role']=='admin') echo 'selected'; ?>>Admin</option>
                        </select>
                    </td>
                    <td>
                        <?php 
                        if($user['blocked']) echo "<span class='badge bg-danger'>Blocked</span>";
                        elseif($user['is_logged_in']) echo "<span class='badge bg-success'>Logged In</span>";
                        else echo "<span class='badge bg-secondary'>Logged Out</span>";
                        ?>
                    </td>
                    <td>
                        <?php if($user['blocked']): ?>
                            <button class="glass-btn glass-btn-success unblockBtn" data-id="<?php echo $user['id']; ?>">
                                <i class="fa fa-unlock me-1"></i> Unblock
                            </button>
                        <?php else: ?>
                            <button class="glass-btn glass-btn-warning blockBtn" data-id="<?php echo $user['id']; ?>">
                                <i class="fa fa-ban me-1"></i> Block
                            </button>
                        <?php endif; ?>
                        <button class="glass-btn glass-btn-danger deleteBtn" data-id="<?php echo $user['id']; ?>">
                            <i class="fa fa-trash me-1"></i> Delete
                        </button>
                        <button class="glass-btn glass-btn-info notifyBtn" data-id="<?php echo $user['id']; ?>" data-username="<?php echo $user['username']; ?>">
                            <i class="fa fa-bell me-1"></i> Notify
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Notify Modal -->
<div class="modal fade" id="notifyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="notifyForm">
        <div class="modal-content glass-modal-content">
            <div class="modal-header glass-modal-header">
                <h5 class="modal-title">Notify User: <span id="notifyUsername"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <textarea name="message" class="form-control glass-form-control" rows="4" placeholder="Enter notification message" required></textarea>
                <input type="hidden" name="recipient_id" id="notifyRecipient">
                <input type="hidden" name="action" value="notify_user">
            </div>
            <div class="modal-footer glass-modal-footer">
                <button type="submit" class="glass-btn glass-btn-primary">Send Notification</button>
            </div>
        </div>
    </form>
  </div>
</div>

<!-- Re-enter Admin Password Modal -->
<div class="modal fade" id="reenterPasswordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="reenterPasswordForm">
      <div class="modal-content glass-modal-content">
        <div class="modal-header glass-modal-header">
          <h5 class="modal-title">Confirm Your Password</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="password" name="current_password" class="form-control glass-form-control" placeholder="Enter your password" required>
          <input type="hidden" name="user_id" id="roleUserId">
          <input type="hidden" name="role" id="roleValue">
          <input type="hidden" name="action" value="update_role">
        </div>
        <div class="modal-footer glass-modal-footer">
          <button type="submit" class="glass-btn glass-btn-primary">Confirm</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form id="addUserForm">
      <div class="modal-content glass-modal-content">
        <div class="modal-header glass-modal-header">
          <h5 class="modal-title">Create New User</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="text" name="username" class="form-control glass-form-control mb-3" placeholder="Username" required>
          <input type="email" name="email" class="form-control glass-form-control mb-3" placeholder="Email" required>
          <input type="password" name="password" class="form-control glass-form-control mb-3" placeholder="Password" required>
          <input type="password" name="confirm_password" class="form-control glass-form-control mb-3" placeholder="Confirm Password" required>
          <select name="role" class="form-select glass-select mb-3" required>
            <option value="user">User</option>
            <option value="moderator">Moderator</option>
            <option value="admin">Admin</option>
          </select>
          <input type="hidden" name="action" value="add_user">
        </div>
        <div class="modal-footer glass-modal-footer">
          <button type="submit" class="glass-btn glass-btn-success">Create User</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
 $(document).ready(function(){

    // Role change with password verification
    var reenterModal = new bootstrap.Modal(document.getElementById('reenterPasswordModal'));
    $('.roleSelect').change(function(){
        var user_id = $(this).data('user-id');
        var role = $(this).val();
        $('#roleUserId').val(user_id);
        $('#roleValue').val(role);
        reenterModal.show();
    });

    $('#reenterPasswordForm').submit(function(e){
        e.preventDefault();
        $.post('', $(this).serialize(), function(response){
            if(response === 'success'){
                showNotification('Role updated successfully!', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showNotification(response, 'danger');
            }
        });
    });

    // Block/Unblock/Delete
    $('.blockBtn').click(function(){
        if(!confirm('Are you sure you want to block this user?')) return;
        var user_id = $(this).data('id');
        $.post('', {action:'block_user', user_id:user_id}, function(response){
            if(response === 'success') {
                showNotification('User blocked successfully!', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            }
            else showNotification('Failed to block user', 'danger');
        });
    });

    $('.unblockBtn').click(function(){
        var user_id = $(this).data('id');
        $.post('', {action:'unblock_user', user_id:user_id}, function(response){
            if(response === 'success') {
                showNotification('User unblocked successfully!', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            }
            else showNotification('Failed to unblock user', 'danger');
        });
    });

    $('.deleteBtn').click(function(){
        if(!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;
        var user_id = $(this).data('id');
        $.post('', {action:'delete_user', user_id:user_id}, function(response){
            if(response === 'success') {
                showNotification('User deleted successfully!', 'success');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            }
            else showNotification('Failed to delete user', 'danger');
        });
    });

    // Notify user modal
    var notifyModal = new bootstrap.Modal(document.getElementById('notifyModal'));
    $('.notifyBtn').click(function(){
        var user_id = $(this).data('id');
        var username = $(this).data('username');
        $('#notifyRecipient').val(user_id);
        $('#notifyUsername').text(username);
        notifyModal.show();
    });

    $('#notifyForm').submit(function(e){
        e.preventDefault();
        $.post('', $(this).serialize(), function(response){
            if(response === 'success'){
                showNotification('Notification sent successfully!', 'success');
                notifyModal.hide();
            } else {
                showNotification('Failed to send notification', 'danger');
            }
        });
    });

    // Add user form
    $('#addUserForm').submit(function(e){
        e.preventDefault();
        $.post('', $(this).serialize(), function(response){
            if(response === 'success'){
                showNotification('User created successfully!', 'success');
                $('#addUserModal').modal('hide');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showNotification(response, 'danger');
            }
        });
    });

    // Custom notification function
    function showNotification(message, type) {
        var alertClass = 'alert-' + type;
        var notification = $('<div class="alert ' + alertClass + ' position-fixed top-0 start-50 translate-middle-x mt-3" style="z-index: 9999; min-width: 300px;">' + message + '</div>');
        $('body').append(notification);
        notification.fadeIn(300);
        
        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
});
</script>
</body>
</html>