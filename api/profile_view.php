<?php
session_start();
include 'C:/Users/User/Documents/GitHub/Website-Project-2/database/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: C:/Users/User/Documents/GitHub/Website-Project-2/public/auth/login.php");
    exit();
}

 $logged_in_id = $_SESSION['user_id'];
 $view_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($view_id <= 0) {
    echo "Invalid user ID.";
    exit();
}

// Fetch logged-in user's role for dashboard redirect
 $role_sql = "SELECT role FROM users WHERE id = ?";
 $stmt_role = $conn->prepare($role_sql);
 $stmt_role->bind_param("i", $logged_in_id);
 $stmt_role->execute();
 $role_result = $stmt_role->get_result();
 $user_role = $role_result->fetch_assoc()['role'] ?? 'user';

// Determine dashboard URL based on role
switch ($user_role) {
    case 'admin':
        $dashboard_url = '/dashboards/admin/admin_dashboard.php';
        break;
    case 'moderator':
        $dashboard_url = 'moderator_dashboard.php';
        break;
    default:
        $dashboard_url = '/dashboards/users/profile.php';
        break;
}

// Fetch user info
 $sql = "SELECT u.id, u.username, p.full_name, p.age, p.gender, p.bio, p.relationship_status, p.profile_image
        FROM users u
        LEFT JOIN users_profile p ON u.id = p.user_id
        WHERE u.id = ?";
 $stmt = $conn->prepare($sql);
 $stmt->bind_param("i", $view_id);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "User not found.";
    exit();
}

 $user = $result->fetch_assoc();

// Check if logged-in user is following this user
 $follow_sql = "SELECT * FROM followers WHERE follower_id = ? AND followed_id = ?";
 $stmt_follow = $conn->prepare($follow_sql);
 $stmt_follow->bind_param("ii", $logged_in_id, $view_id);
 $stmt_follow->execute();
 $is_following = $stmt_follow->get_result()->num_rows > 0;

 $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Profile - <?php echo htmlspecialchars($user['username']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { max-width: 600px; margin: 40px auto; padding: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); text-align: center; }
        .card img { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; }
        .user-information { text-align: left; margin-top: 20px; }
        .user-information p { margin: 8px 0; }
        .btn-follow { width: 150px; }
        .btn-follow .spinner-border {
            vertical-align: middle;
            margin-right: 5px;
            width: 1rem;
            height: 1rem;
        }
    </style>
</head>
<body>

<div class="card">
    <img src="<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/295/295128.png'; ?>" alt="profile_image">
    <h1><?php echo htmlspecialchars($user['username']); ?></h1>

    <?php if ($logged_in_id !== $view_id): ?>
         <!-- a simple button -->
        <div class="mb-3">
            <button type="button" id="followBtn" 
                    class="btn <?php echo $is_following ? 'btn-danger' : 'btn-primary'; ?> btn-follow" 
                    data-user-id="<?php echo $view_id; ?>">
                <?php echo $is_following ? 'Unfollow' : 'Follow'; ?>
            </button>
        </div>
    <?php endif; ?>

    <div class="user-information">
        <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user['full_name'] ?? 'Not set'); ?></p>
        <p><strong>Age:</strong> <?php echo htmlspecialchars($user['age'] ?? 'Not set'); ?></p>
        <p><strong>Gender:</strong> <?php echo htmlspecialchars($user['gender'] ?? 'Not set'); ?></p>
        <p><strong>Bio:</strong> <?php echo htmlspecialchars($user['bio'] ?? 'Not set'); ?></p>
        <p><strong>Relationship Status:</strong> <?php echo htmlspecialchars($user['relationship_status'] ?? 'Not set'); ?></p>
    </div>

    <a href="<?php echo $dashboard_url; ?>" class="btn btn-warning mt-3">
        <?php 
            switch ($user_role) {
                case 'admin':
                    echo 'Back to Admin Dashboard';
                    break;
                case 'moderator':
                    echo 'Back to Moderator Dashboard';
                    break;
                default:
                    echo 'Back to My Profile';
                    break;
            }
        ?>
    </a>
</div>

<script>
document.getElementById('followBtn').addEventListener('click', function() {
    const btn = this;
    const userId = btn.getAttribute('data-user-id');
    
    // Determine current state by checking the button's color class
    const isCurrentlyFollowing = btn.classList.contains('btn-danger');
    const action = isCurrentlyFollowing ? 'unfollow' : 'follow';

    // 1. Set Loading State (Disable button, show spinner, change to gray)
    btn.disabled = true;
    btn.classList.remove('btn-primary', 'btn-danger');
    btn.classList.add('btn-secondary');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';

    // 2. Send AJAX Request
    // Using URL from your original form action
    fetch('/api/handlers/follow_unfollow_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}&${action}=1`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // 3. Update UI based on the action taken
            if (data.action === 'followed') {
                btn.innerHTML = 'Unfollow';
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-danger');
            } else if (data.action === 'unfollowed') {
                btn.innerHTML = 'Follow';
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-primary');
            }
        } else {
            // If error, revert to original state
            alert(data.message || 'Something went wrong.');
            btn.innerHTML = isCurrentlyFollowing ? 'Unfollow' : 'Follow';
            btn.classList.remove('btn-secondary');
            btn.classList.add(isCurrentlyFollowing ? 'btn-danger' : 'btn-primary');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Revert on network error
        btn.innerHTML = isCurrentlyFollowing ? 'Unfollow' : 'Follow';
        btn.classList.remove('btn-secondary');
        btn.classList.add(isCurrentlyFollowing ? 'btn-danger' : 'btn-primary');
    })
    .finally(() => {
        // Re-enable button regardless of success/failure
        btn.disabled = false;
    });
});
</script>

</body>
</html>