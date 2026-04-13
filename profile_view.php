<?php
session_start();
include 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$logged_in_id = $_SESSION['user_id'];
$view_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($view_id <= 0) {
    echo "Invalid user ID.";
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
    </style>
</head>
<body>

<div class="card">
    <img src="<?php echo !empty($user['profile_image']) ? htmlspecialchars($user['profile_image']) : 'https://cdn-icons-png.flaticon.com/512/295/295128.png'; ?>" alt="profile_image">
    <h1><?php echo htmlspecialchars($user['username']); ?></h1>

    <?php if ($logged_in_id !== $view_id): ?>
        <form method="post" action="follow_unfollow_handler.php" class="mb-3">
            <input type="hidden" name="user_id" value="<?php echo $view_id; ?>">
            <?php if ($is_following): ?>
                <button type="submit" name="unfollow" class="btn btn-danger btn-follow">Unfollow</button>
            <?php else: ?>
                <button type="submit" name="follow" class="btn btn-primary btn-follow">Follow</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <div class="user-information">
        <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user['full_name'] ?? 'Not set'); ?></p>
        <p><strong>Age:</strong> <?php echo htmlspecialchars($user['age'] ?? 'Not set'); ?></p>
        <p><strong>Gender:</strong> <?php echo htmlspecialchars($user['gender'] ?? 'Not set'); ?></p>
        <p><strong>Bio:</strong> <?php echo htmlspecialchars($user['bio'] ?? 'Not set'); ?></p>
        <p><strong>Relationship Status:</strong> <?php echo htmlspecialchars($user['relationship_status'] ?? 'Not set'); ?></p>
    </div>

    <a href="profile.php" class="btn btn-warning mt-3">Back to My Profile</a>
</div>

</body>
</html>
