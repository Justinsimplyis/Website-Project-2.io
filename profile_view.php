<?php
session_start();
include 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get session data safely
 $logged_in_id = $_SESSION['user_id'];
 $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';

// Get profile ID from URL
 $view_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($view_id <= 0) {
    echo "Invalid user ID.";
    exit();
}

// Redirect if user is viewing their own profile
if ($logged_in_id === $view_id) {
    if ($role === 'admin') {
        header("Location: a_profile.php");
    } else {
        header("Location: profile.php");
    }
    exit();
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

// --- NEW: Fetch Follower Count & List ---
 $follower_count = 0;
 $followers_list = [];
 $fc_sql = "SELECT COUNT(*) as total FROM followers WHERE followed_id = ?";
 $stmt_fc = $conn->prepare($fc_sql);
 $stmt_fc->bind_param("i", $view_id);
 $stmt_fc->execute();
 $follower_count = $stmt_fc->get_result()->fetch_assoc()['total'];

 $fl_sql = "SELECT u.id, u.username, p.profile_image 
           FROM followers f 
           JOIN users u ON f.follower_id = u.id 
           LEFT JOIN users_profile p ON u.id = p.user_id 
           WHERE f.followed_id = ?";
 $stmt_fl = $conn->prepare($fl_sql);
 $stmt_fl->bind_param("i", $view_id);
 $stmt_fl->execute();
 $followers_list = $stmt_fl->get_result()->fetch_all(MYSQLI_ASSOC);

// --- NEW: Fetch Following Count & List ---
 $following_count = 0;
 $following_list = [];
 $fgc_sql = "SELECT COUNT(*) as total FROM followers WHERE follower_id = ?";
 $stmt_fgc = $conn->prepare($fgc_sql);
 $stmt_fgc->bind_param("i", $view_id);
 $stmt_fgc->execute();
 $following_count = $stmt_fgc->get_result()->fetch_assoc()['total'];

 $fgl_sql = "SELECT u.id, u.username, p.profile_image 
            FROM followers f 
            JOIN users u ON f.followed_id = u.id 
            LEFT JOIN users_profile p ON u.id = p.user_id 
            WHERE f.follower_id = ?";
 $stmt_fgl = $conn->prepare($fgl_sql);
 $stmt_fgl->bind_param("i", $view_id);
 $stmt_fgl->execute();
 $following_list = $stmt_fgl->get_result()->fetch_all(MYSQLI_ASSOC);

 $conn->close();

// Determine correct profile page for "Back" button
 $dashboard_page = ($role === 'admin') ? 'admin_dashboard.php' : 'dashboard.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Profile - <?php echo htmlspecialchars($user['username']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    <link rel="shortcut icon" href="https://cdn-icons-png.flaticon.com/512/295/295128.png">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            color: white;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        /* Glassmorphism Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.08) !important;
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .navbar-brand, .btn-light { color: white !important; text-shadow: 0 0 10px rgba(0, 255, 200, 0.5); }
        .btn-light {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            transition: all 0.3s ease;
        }
        .btn-light:hover { background: rgba(255, 255, 255, 0.2) !important; }

        /* Glassmorphism Cards */
        .glass-box {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px;
            margin: 40px auto;
            max-width: 600px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .glass-box:hover { transform: translateY(-5px); box-shadow: 0 12px 40px rgba(0, 255, 200, 0.3); }

        .profile-img {
            width: 150px; height: 150px; border-radius: 50%; object-fit: cover;
            border: 3px solid rgba(0, 255, 200, 0.5); box-shadow: 0 0 20px rgba(0, 255, 200, 0.3);
        }
        .username { font-size: 2rem; font-weight: bold; margin: 20px 0; text-shadow: 0 0 10px rgba(0, 255, 200, 0.5); }
        
        .info-section {
            text-align: left; margin-top: 30px; padding: 20px;
            background: rgba(255, 255, 255, 0.05); border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .info-section p { margin: 10px 0; padding: 8px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .info-section p:last-child { border-bottom: none; }
        .info-section strong { color: #00ffc8; margin-right: 10px; }

        .btn-glass {
            background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2);
            color: white; padding: 10px 25px; border-radius: 10px; transition: all 0.3s ease;
        }
        .btn-glass:hover { background: rgba(255, 255, 255, 0.2); transform: translateY(-2px); color: white; }

        .btn-primary-custom {
            background: linear-gradient(45deg, #00ffc8, #00ff88); border: none;
            color: #0f2027; font-weight: bold; width: 150px;
        }
        .btn-primary-custom:hover { background: linear-gradient(45deg, #00ff88, #00ffc8); transform: translateY(-2px); color: #0f2027;}
        .btn-danger-custom {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24); border: none;
            color: white; font-weight: bold; width: 150px;
        }
        .btn-danger-custom:hover { transform: translateY(-2px); color: white;}

        /* Modal Styles */
        .modal-content { background: rgba(15, 32, 39, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; color: white; }
        .modal-header { border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .btn-close { filter: invert(1); }
        
        .user-card { display: flex; align-items: center; justify-content: space-between; padding: 10px; background: rgba(255, 255, 255, 0.05); border-radius: 10px; margin-bottom: 10px; transition: all 0.3s ease; }
        .user-card:hover { background: rgba(255, 255, 255, 0.1); transform: translateX(5px); }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(0, 255, 200, 0.5); }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-sm">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $dashboard_page; ?>">
                <i class="fa fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </nav>

    <div class="container">
        <!-- Profile Card -->
        <div class="glass-box text-center">
            <img src="<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/295/295128.png'; ?>" 
                 alt="profile_image" class="profile-img">
            
            <h1 class="username"><?php echo htmlspecialchars($user['username']); ?></h1>

            <!-- Follow / Unfollow Button -->
            <?php if ($logged_in_id !== $view_id): ?>
                <form method="post" action="follow_unfollow_handler.php" class="mb-3">
                    <input type="hidden" name="user_id" value="<?php echo $view_id; ?>">
                    <?php if ($is_following): ?>
                        <button type="submit" name="unfollow" class="btn btn-danger-custom">
                            <i class="fa fa-user-times"></i> Unfollow
                        </button>
                    <?php else: ?>
                        <button type="submit" name="follow" class="btn btn-primary-custom">
                            <i class="fa fa-user-plus"></i> Follow
                        </button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <!-- NEW: Followers / Following Counts -->
            <div class="d-flex justify-content-center gap-3 my-4">
                <button class="btn btn-glass" data-bs-toggle="modal" data-bs-target="#followersModal">
                    <i class="fa fa-users"></i> Followers (<?php echo $follower_count; ?>)
                </button>
                <button class="btn btn-glass" data-bs-toggle="modal" data-bs-target="#followingModal">
                    <i class="fa fa-user-plus"></i> Following (<?php echo $following_count; ?>)
                </button>
            </div>
    
            <!-- User Info -->
            <div class="info-section">
                <h3><i class="fa fa-info-circle" style="color:#00ffc8;"></i> Profile Information</h3>
                <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user['full_name'] ?? 'Not set'); ?></p>
                <p><strong>Age:</strong> <?php echo htmlspecialchars($user['age'] ?? 'Not set'); ?></p>
                <p><strong>Gender:</strong> <?php echo htmlspecialchars($user['gender'] ?? 'Not set'); ?></p>
                <p><strong>Bio:</strong> <?php echo htmlspecialchars($user['bio'] ?? 'Not set'); ?></p>
                <p><strong>Relationship Status:</strong> <?php echo htmlspecialchars($user['relationship_status'] ?? 'Not set'); ?></p>
            </div>
        </div>
    </div>

    <!-- Followers Modal -->
    <div class="modal fade" id="followersModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-users"></i> Followers</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if(count($followers_list) > 0): ?>
                        <?php foreach($followers_list as $u): ?>
                            <div class="user-card">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?php echo !empty($u['profile_image']) ? htmlspecialchars($u['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/295/295128.png'; ?>" class="user-avatar">
                                    <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                </div>
                                <a href="profile_view.php?id=<?php echo $u['id']; ?>" class="btn btn-glass btn-sm"><i class="fa fa-eye"></i> View</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted p-4"><i class="fa fa-users fa-3x mb-3"></i><p>No followers yet</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Following Modal -->
    <div class="modal fade" id="followingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fa fa-user-plus"></i> Following</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if(count($following_list) > 0): ?>
                        <?php foreach($following_list as $u): ?>
                            <div class="user-card">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?php echo !empty($u['profile_image']) ? htmlspecialchars($u['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/295/295128.png'; ?>" class="user-avatar">
                                    <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                </div>
                                <a href="profile_view.php?id=<?php echo $u['id']; ?>" class="btn btn-glass btn-sm"><i class="fa fa-eye"></i> View</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted p-4"><i class="fa fa-user-plus fa-3x mb-3"></i><p>Not following anyone</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</body>
</html>