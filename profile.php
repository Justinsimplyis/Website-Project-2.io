<?php
session_start();

// Check if the user is logged in, if not then redirect them to the login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// db connections
include 'db_connection.php';

// Get user ID from session
 $user_id = $_SESSION['user_id'];
 $username = $_SESSION['username'];

// NEW: Fetch notifications for the navbar
 $notifications_query = "SELECT n.*, u.username AS sender_name
                       FROM notifications n
                       LEFT JOIN users u ON n.sender_id = u.id
                       WHERE n.recipient_id = ?
                       ORDER BY n.created_at DESC
                       LIMIT 5";
 $stmt_notifications = $conn->prepare($notifications_query);
 $stmt_notifications->bind_param("i", $user_id);
 $stmt_notifications->execute();
 $notifications_result = $stmt_notifications->get_result();
 $notifications = $notifications_result->fetch_all(MYSQLI_ASSOC);

// NEW: Count unread notifications
 $unread_query = "SELECT COUNT(*) as total FROM notifications 
                 WHERE recipient_id = ? AND is_read = 0";
 $stmt_unread = $conn->prepare($unread_query);
 $stmt_unread->bind_param("i", $user_id);
 $stmt_unread->execute();
 $unread_data = $stmt_unread->get_result()->fetch_assoc();
 $unread_count = $unread_data['total'];


// Fetch profile information
 $profile_info = [];
 $sql = "SELECT * FROM users_profile WHERE user_id = ?";
 $stmt = $conn->prepare($sql);
 $stmt->bind_param("i", $user_id);
 $stmt->execute();
 $result = $stmt->get_result();

if ($result->num_rows > 0) {
    $profile_info = $result->fetch_assoc();
} else {
    // Initialize empty profile if it doesn't exist
    $profile_info = [
        'full_name' => '',
        'age' => '',
        'gender' => '',
        'bio' => '',
        'relationship_status' => '',
        'profile_image' => 'https://cdn-icons-png.flaticon.com/512/295/295128.png'
    ];
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'] ?? '';
    $age = $_POST['age'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $relationship_status = $_POST['relationship_status'] ?? '';
    
    // Handle image upload
    $profile_image = $profile_info['profile_image']; // Keep current image by default
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profile_images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = $user_id . '_' . time() . '_' . basename($_FILES['profile_image']['name']);
        $upload_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
            $profile_image = $upload_path;
        }
    }
    
    // Check if profile exists
    $check_sql = "SELECT user_id FROM users_profile WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing profile
        $update_sql = "UPDATE users_profile SET full_name = ?, age = ?, gender = ?, bio = ?, relationship_status = ?, profile_image = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sissssi", $full_name, $age, $gender, $bio, $relationship_status, $profile_image, $user_id);
        $update_stmt->execute();
    } else {
        // Insert new profile
        $insert_sql = "INSERT INTO users_profile (user_id, full_name, age, gender, bio, relationship_status, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("isissss", $user_id, $full_name, $age, $gender, $bio, $relationship_status, $profile_image);
        $insert_stmt->execute();
    }
    
    // NEW: Create a notification for the profile update
    $notification_message = "Your profile was successfully updated.";
    $notif_sql = "INSERT INTO notifications (recipient_id, sender_id, type, message, is_read) VALUES (?, ?, 'profile_update', ?, 0)";
    $notif_stmt = $conn->prepare($notif_sql);
    $notif_stmt->bind_param("iis", $user_id, $user_id, $notification_message);
    $notif_stmt->execute();
    $notif_stmt->close();

    // Refresh profile info after update
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $profile_info = $result->fetch_assoc();
    }
    
    // Show success message
    $update_success = true;
}

// Get followers
 $followers_sql = "SELECT u.id, u.username, p.profile_image
                  FROM followers f
                  JOIN users u ON f.follower_id = u.id
                  LEFT JOIN users_profile p ON u.id = p.user_id
                  WHERE f.followed_id = ?";
 $stmt_followers = $conn->prepare($followers_sql);
 $stmt_followers->bind_param("i", $user_id);
 $stmt_followers->execute();
 $followers = $stmt_followers->get_result()->fetch_all(MYSQLI_ASSOC);

// Get following
 $following_sql = "SELECT u.id, u.username, p.profile_image
                  FROM followers f
                  JOIN users u ON f.followed_id = u.id
                  LEFT JOIN users_profile p ON u.id = p.user_id
                  WHERE f.follower_id = ?";
 $stmt_following = $conn->prepare($following_sql);
 $stmt_following->bind_param("i", $user_id);
 $stmt_following->execute();
 $following = $stmt_following->get_result()->fetch_all(MYSQLI_ASSOC);

 $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    <link rel="shortcut icon" href="https://cdn-icons-png.flaticon.com/512/295/295128.png">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
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
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }

        .navbar-brand, .navbar-text {
            color: white !important;
            text-shadow: 0 0 10px rgba(0, 255, 200, 0.5);
        }

        .btn-light {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: white !important;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .btn-light:hover {
            background: rgba(255, 255, 255, 0.2) !important;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 200, 0.3);
        }

        /* Glassmorphism Cards */
        .glass-box {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px;
            margin: 20px auto;
            max-width: 600px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .glass-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 255, 200, 0.3);
        }

        .profile-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(0, 255, 200, 0.5);
            box-shadow: 0 0 20px rgba(0, 255, 200, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .profile-img:hover {
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(0, 255, 200, 0.5);
        }

        .username {
            font-size: 2rem;
            font-weight: bold;
            margin: 20px 0;
            text-shadow: 0 0 10px rgba(0, 255, 200, 0.5);
        }

        .online-status {
            color: #00ff88;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .online-status::before {
            content: '';
            width: 10px;
            height: 10px;
            background: #00ff88;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 255, 136, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(0, 255, 136, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 255, 136, 0); }
        }

        .info-section {
            text-align: left;
            margin-top: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-section h3 {
            color: #00ffc8;
            margin-bottom: 20px;
            text-shadow: 0 0 10px rgba(0, 255, 200, 0.5);
        }

        .info-section p {
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-section p:last-child {
            border-bottom: none;
        }

        .info-section strong {
            color: #00ffc8;
            margin-right: 10px;
        }

        /* Glassmorphism Buttons */
        .btn-glass {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 25px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            margin: 5px;
        }

        .btn-glass:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 200, 0.3);
            color: white;
        }

        .btn-primary-custom {
            background: linear-gradient(45deg, #00ffc8, #00ff88);
            border: none;
            color: #0f2027;
            font-weight: bold;
        }

        .btn-primary-custom:hover {
            background: linear-gradient(45deg, #00ff88, #00ffc8);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 255, 200, 0.5);
        }

        /* Form Styles */
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 10px;
            padding: 12px;
            backdrop-filter: blur(10px);
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #00ffc8;
            box-shadow: 0 0 10px rgba(0, 255, 200, 0.3);
            color: white;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-label {
            color: #00ffc8;
            font-weight: 500;
            margin-bottom: 8px;
        }

        /* Modal Styles */
        .modal-content {
            background: rgba(15, 32, 39, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            color: white;
        }

        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-close {
            filter: invert(1);
        }

        .notification-item {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: background 0.3s ease;
        }

        .notification-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .notification-item.unread {
            background: rgba(0, 255, 200, 0.1);
            border-left: 3px solid #00ffc8;
        }

        /* Loading Overlay */
        #loadingOverlay {
            background: rgba(15, 32, 39, 0.95);
            backdrop-filter: blur(10px);
        }

        /* Alert Styles */
        .alert-success {
            background: rgba(0, 255, 136, 0.2);
            border: 1px solid rgba(0, 255, 136, 0.3);
            color: #00ff88;
            border-radius: 10px;
        }

        /* Follower/Following Cards */
        .user-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .user-card:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(0, 255, 200, 0.5);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .glass-box {
                margin: 10px;
                padding: 20px;
            }
            
            .username {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; justify-content: center; align-items: center;">
        <div class="text-center">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Please wait...</p>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-sm">
        <div class="container">
            <a class="navbar-brand" href="#" style="font-weight: bold;">
                <i class="fa fa-user-circle"></i> Profile
            </a>
            <span class="navbar-text">
                <i class="fa fa-user"></i> <?php echo htmlspecialchars($username); ?>
            </span>
            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#collapsibleNavId" aria-controls="collapsibleNavId" aria-expanded="false"
                aria-label="Toggle navigation">                
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="collapsibleNavId">
                <ul class="navbar-nav m-auto mt-2 mt-lg-0">
                </ul>
                <div class="d-flex align-items-center gap-2">                   
                    <!-- Notifications -->
                    <button class="btn btn-light position-relative" type="button" data-bs-toggle="modal" data-bs-target="#notificationsModal">
                        <i class="fa fa-bell"></i>
                        <?php if($unread_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <!-- Back to Dashboard -->
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="fa fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Profile Card -->
        <div class="glass-box text-center">
            <img src="<?php echo !empty($profile_info['profile_image']) ? htmlspecialchars($profile_info['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/295/295128.png'; ?>" 
                 alt="profile_image" 
                 class="profile-img" 
                 id="profileImagePreview">
            
            <h1 class="username"><?php echo htmlspecialchars($username); ?></h1>
            <p class="online-status">Online</p>
            <p class="text-muted">
                <i class="fa fa-clock"></i> 
                Last Updated: <?php echo !empty($profile_info['updated_at']) ? date('F j, Y, g:i a', strtotime($profile_info['updated_at'])) : 'Never'; ?>
            </p>
            
            <!-- Followers / Following buttons -->
            <div class="d-flex justify-content-center gap-3 my-4">
                <button class="btn btn-glass" data-bs-toggle="modal" data-bs-target="#followersModal">
                    <i class="fa fa-users"></i> Followers (<?php echo count($followers); ?>)
                </button>
                <button class="btn btn-glass" data-bs-toggle="modal" data-bs-target="#followingModal">
                    <i class="fa fa-user-plus"></i> Following (<?php echo count($following); ?>)
                </button>
            </div>
    
            <div class="info-section">
                <h3><i class="fa fa-info-circle"></i> Profile Information</h3>
                <p><strong>Full Name:</strong> <?php echo !empty($profile_info['full_name']) ? htmlspecialchars($profile_info['full_name']) : '<span class="text-muted">Not set</span>'; ?></p>
                <p><strong>Age:</strong> <?php echo !empty($profile_info['age']) ? htmlspecialchars($profile_info['age']) : '<span class="text-muted">Not set</span>'; ?></p>
                <p><strong>Gender:</strong> <?php echo !empty($profile_info['gender']) ? htmlspecialchars($profile_info['gender']) : '<span class="text-muted">Not set</span>'; ?></p>
                <p><strong>Bio:</strong> <?php echo !empty($profile_info['bio']) ? htmlspecialchars($profile_info['bio']) : '<span class="text-muted">Not set</span>'; ?></p>
                <p><strong>Relationship Status:</strong> <?php echo !empty($profile_info['relationship_status']) ? htmlspecialchars($profile_info['relationship_status']) : '<span class="text-muted">Not set</span>'; ?></p>
            </div>
            
            <p>
                <!-- Changed from collapse to modal -->
                <button class="btn btn-primary-custom" type="button" data-bs-toggle="modal" data-bs-target="#updateProfileModal">
                    <i class="fa fa-edit"></i> Update Profile
                </button>
            </p>
        </div>
    </div>

    <!-- Update Profile Modal -->
    <div class="modal fade" id="updateProfileModal" tabindex="-1" aria-labelledby="updateProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateProfileModalLabel">
                        <i class="fa fa-user-edit"></i> Update Profile
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (isset($update_success) && $update_success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fa fa-check-circle"></i> Profile updated successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" enctype="multipart/form-data" id="profileUpdateForm">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($profile_info['full_name']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="age" class="form-label">Age</label>
                            <input type="number" class="form-control" id="age" name="age" value="<?php echo htmlspecialchars($profile_info['age']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="">Not Set</option>
                                <option value="Male" <?php echo (isset($profile_info['gender']) && $profile_info['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($profile_info['gender']) && $profile_info['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (isset($profile_info['gender']) && $profile_info['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo htmlspecialchars($profile_info['bio']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="relationship_status" class="form-label">Relationship Status</label>
                            <select class="form-select" id="relationship_status" name="relationship_status">
                                <option value="">Not Set</option>
                                <option value="Single" <?php echo (isset($profile_info['relationship_status']) && $profile_info['relationship_status'] == 'Single') ? 'selected' : ''; ?>>Single</option>
                                <option value="In a relationship" <?php echo (isset($profile_info['relationship_status']) && $profile_info['relationship_status'] == 'In a relationship') ? 'selected' : ''; ?>>In a relationship</option>
                                <option value="Married" <?php echo (isset($profile_info['relationship_status']) && $profile_info['relationship_status'] == 'Married') ? 'selected' : ''; ?>>Married</option>
                                <option value="Complicated" <?php echo (isset($profile_info['relationship_status']) && $profile_info['relationship_status'] == 'Complicated') ? 'selected' : ''; ?>>It's complicated</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="profile_image" class="form-label">Profile Image</label>
                            <input type="file" class="form-control" id="profile_image" name="profile_image">
                            <small class="text-muted">Leave empty to keep current image</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="profileUpdateForm" name="update_profile" class="btn btn-primary-custom">
                        <i class="fa fa-save"></i> Update Profile
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications Modal -->
    <div class="modal fade" id="notificationsModal" tabindex="-1" aria-labelledby="notificationsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="notificationsModalLabel">
                        <i class="fa fa-bell"></i> Notifications
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if(count($notifications) > 0): ?>
                        <?php foreach($notifications as $row): ?>
                            <a href="mark_read.php?id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                <div class="notification-item <?php echo $row['is_read'] == 0 ? 'unread' : ''; ?>">
                                    <small class="text-muted d-block mb-1">
                                        <i class="fa fa-clock"></i> <?php echo date("M d, H:i", strtotime($row['created_at'])); ?>
                                    </small>
                                    <?php
                                    if($row['type'] == 'follow'){
                                        echo "<i class='fa fa-user-plus text-info'></i> <strong>{$row['sender_name']}</strong> followed you";
                                    } elseif($row['type'] == 'like'){
                                        echo "<i class='fa fa-heart text-danger'></i> <strong>{$row['sender_name']}</strong> liked your post";
                                    } elseif($row['type'] == 'profile_update') {
                                        echo "<i class='fa fa-check-circle text-success'></i> {$row['message']}";
                                    } else {
                                        echo "<i class='fa fa-info-circle text-primary'></i> {$row['message']}";
                                    }
                                    ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted p-4">
                            <i class="fa fa-bell-slash fa-3x mb-3"></i>
                            <p>No notifications</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Followers Modal -->
    <div class="modal fade" id="followersModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa fa-users"></i> Followers
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if(count($followers) > 0): ?>
                        <?php foreach($followers as $user): ?>
                            <div class="user-card">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/295/295128.png'; ?>"
                                         class="user-avatar">
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                </div>
                                <a href="profile_view.php?id=<?php echo $user['id']; ?>" class="btn btn-glass btn-sm">
                                    <i class="fa fa-eye"></i> View
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted p-4">
                            <i class="fa fa-users fa-3x mb-3"></i>
                            <p>No followers yet</p>
                        </div>
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
                    <h5 class="modal-title">
                        <i class="fa fa-user-plus"></i> Following
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if(count($following) > 0): ?>
                        <?php foreach($following as $user): ?>
                            <div class="user-card">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/295/295128.png'; ?>"
                                         class="user-avatar">
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="profile_view.php?id=<?php echo $user['id']; ?>" class="btn btn-glass btn-sm">
                                        <i class="fa fa-eye"></i> View
                                    </a>
                                    <a href="unfollow.php?id=<?php echo $user['id']; ?>" class="btn btn-glass btn-sm">
                                        <i class="fa fa-user-times"></i> Unfollow
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted p-4">
                            <i class="fa fa-user-plus fa-3x mb-3"></i>
                            <p>You are not following anyone</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Show loading overlay on form submit
        document.getElementById('profileUpdateForm').addEventListener('submit', function() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        });

        // Make profile image click trigger file input
        document.getElementById('profileImagePreview').addEventListener('click', function() {
            document.getElementById('profile_image').click();
        });

        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Add animation to elements on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all glass boxes
        document.querySelectorAll('.glass-box').forEach(box => {
            box.style.opacity = '0';
            box.style.transform = 'translateY(20px)';
            box.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(box);
        });
    </script>
</body>
</html>