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
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    <link rel="shortcut icon" href="https://cdn-icons-png.flaticon.com/512/295/295128.png">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <style>
        /* NEW: Sticky Navbar with shadow */
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .card {
            box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2);
            max-width: 600px;
            margin: auto;
            text-align: center;
            font-family: arial;
            padding: 20px;
            margin-top: 20px;
        }
        
        .card img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            cursor: pointer; /* NEW: Makes image look clickable */
            transition: transform 0.2s;
        }
        
        /* NEW: Hover effect for profile image */
        .card img:hover {
            transform: scale(1.05);
        }
        
        .user-information {
            text-align: left;
            margin-top: 20px;
        }
        
        .user-information p {
            margin: 10px 0;
        }
        .user-information h2{
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        .logged-in-status{
            color: green;
            font-weight: bold;
            margin-bottom: 5px; 
        }
        .update-profile {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .btn-update {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }
        .btn {
            min-width: 120px;
        }
        .btn-update:hover {
            opacity: 0.8;
        }
        
        .alert-success {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .modal-body div{
            padding: 5px 0;
        }
        /* NEW: Style for modal body with potential long lists */
        .modal-body {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>

<body>
    <!-- NEW: Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
        <div class="text-center text-white">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Please wait...</p>
        </div>
    </div>

    <nav class="navbar navbar-expand-sm navbar-light bg-success">
        <div class="container">
            <a class="navbar-brand" href="#" style="font-weight:bold; color:white;">Profile</a>
            <span style="color:white; font-weight:bold;">
                <?php echo htmlspecialchars($username); ?>
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

                   
                    <!-- Profile -->
                    <a href="admin_dashboard.php" class="btn btn-light my-2 my-sm-0"
                        style="font-weight:bolder;color:orange;">
                        <i class="fa fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div>
        <!-- Profile Content -->
        <h2 style="text-align:center">User Profile</h2>

        <div class="card">
            <!-- NEW: Make image clickable to trigger file input -->
            <img src="<?php echo !empty($profile_info['profile_image']) ? htmlspecialchars($profile_info['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/295/295128.png'; ?>" alt="profile_image" id="profileImagePreview">
            <h1><?php echo htmlspecialchars($username); ?></h1>
            <p class="logged-in-status">online</p>
            <p>Last Updated: <?php echo !empty($profile_info['updated_at']) ? date('F j, Y, g:i a', strtotime($profile_info['updated_at'])) : 'Never'; ?></p>
            
            <!-- Followers / Following buttons -->
            <div class="d-flex justify-content-center gap-3 my-3">
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#followersModal">
                    <i class="fa fa-users"></i> Followers (<?php echo count($followers); ?>)
                </button>
                <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#followingModal">
                    <i class="fa fa-user-plus"></i> Following (<?php echo count($following); ?>)
                </button>
            </div>
    
            <div class="user-information">
                <h2>User's Profile Information</h2>
                <p><strong>Full Name:</strong> <?php echo !empty($profile_info['full_name']) ? htmlspecialchars($profile_info['full_name']) : 'Not set'; ?></p>
                <p><strong>Age:</strong> <?php echo !empty($profile_info['age']) ? htmlspecialchars($profile_info['age']) : 'Not set'; ?></p>
                <p><strong>Gender:</strong> <?php echo !empty($profile_info['gender']) ? htmlspecialchars($profile_info['gender']) : 'Not set'; ?></p>
                <p><strong>Bio:</strong> <?php echo !empty($profile_info['bio']) ? htmlspecialchars($profile_info['bio']) : 'Not set'; ?></p>
                <p><strong>Relationship Status:</strong> <?php echo !empty($profile_info['relationship_status']) ? htmlspecialchars($profile_info['relationship_status']) : 'Not set'; ?></p>
            </div>
            
            <!-- NEW: Using Bootstrap Collapse for smoother transition -->
            <p>
                <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#updateForm" aria-expanded="false" aria-controls="updateForm">
                    <i class="fa fa-edit"></i> Update Profile
                </button>
            </p>
        </div>
        
        <!-- The 'collapse' class makes this div hidden by default -->
        <div class="update-profile collapse" id="updateForm">
            <?php if (isset($update_success) && $update_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Profile updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <h2>Update Profile</h2>
            <form method="post" enctype="multipart/form-data" id="profileUpdateForm">
                <div class="form-group">
                    <label for="full_name">Full Name:</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($profile_info['full_name']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="age">Age:</label>
                    <input type="number" class="form-control" id="age" name="age" value="<?php echo htmlspecialchars($profile_info['age']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="gender">Gender:</label>
                    <select class="form-control" id="gender" name="gender">
                        <option value="">Not Set</option>
                        <option value="Male" <?php echo (isset($profile_info['gender']) && $profile_info['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (isset($profile_info['gender']) && $profile_info['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo (isset($profile_info['gender']) && $profile_info['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="bio">Bio:</label>
                    <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo htmlspecialchars($profile_info['bio']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="relationship_status">Relationship Status:</label>
                    <select class="form-control" id="relationship_status" name="relationship_status">
                        <option value="">Not Set</option>
                        <option value="Single" <?php echo (isset($profile_info['relationship_status']) && $profile_info['relationship_status'] == 'Single') ? 'selected' : ''; ?>>Single</option>
                        <option value="In a relationship" <?php echo (isset($profile_info['relationship_status']) && $profile_info['relationship_status'] == 'In a relationship') ? 'selected' : ''; ?>>In a relationship</option>
                        <option value="Married" <?php echo (isset($profile_info['relationship_status']) && $profile_info['relationship_status'] == 'Married') ? 'selected' : ''; ?>>Married</option>
                        <option value="Complicated" <?php echo (isset($profile_info['relationship_status']) && $profile_info['relationship_status'] == 'Complicated') ? 'selected' : ''; ?>>It's complicated</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="profile_image">Profile Image:</label>
                    <input type="file" class="form-control" id="profile_image" name="profile_image">
                    <small class="form-text text-muted">Leave empty to keep current image</small>
                </div>
                
                <button type="submit" name="update_profile" class="btn btn-success w-100">Update Profile</button>
                
            </form>
        </div>
    </div>
    

    <!-- Followers Modal -->
    <div class="modal fade" id="followersModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Followers</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if(count($followers) > 0): ?>
                        <?php foreach($followers as $user): ?>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/295/295128.png'; ?>"
                                         width="40" height="40" style="border-radius:50%; object-fit:cover;">
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                </div>
                                <a href="profile_view.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">View</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No followers yet</p>
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
                    <h5 class="modal-title">Following</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if(count($following) > 0): ?>
                        <?php foreach($following as $user): ?>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/295/295128.png'; ?>"
                                         width="40" height="40" style="border-radius:50%; object-fit:cover;">
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                </div>
                                <div class="d-flex gap-1">
                                    <a href="profile_view.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">View</a>                                   
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>You are not following anyone</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- NEW: JavaScript for loading overlay and clickable profile image -->
    <script>
        // Show loading overlay on form submit
        document.getElementById('profileUpdateForm').addEventListener('submit', function() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        });

        // Make profile image click trigger file input
        document.getElementById('profileImagePreview').addEventListener('click', function() {
            document.getElementById('profile_image').click();
        });
    </script>
</body>
</html>