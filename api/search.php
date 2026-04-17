<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: C:/Users/User/Documents/GitHub/Website-Project-2/public/auth/login.php");
    exit();
}

 $user_id = $_SESSION['user_id'];

//db connection
include 'C:/Users/User/Documents/GitHub/Website-Project-2/database/db_connection.php';

 $search_results = [];

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search = "%" . trim($_GET['search']) . "%";

    $search_sql = "SELECT u.id, u.username, p.profile_image
                   FROM users u
                   LEFT JOIN users_profile p ON u.id = p.user_id
                   WHERE u.username LIKE ?
                   AND u.id != ?";

    $stmt = $conn->prepare($search_sql);
    $stmt->bind_param("si", $search, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $search_results = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Display search results
if (count($search_results) > 0) {
    foreach($search_results as $user) {
        echo '<div class="d-flex align-items-center justify-content-between border-bottom py-2 p-2">';
        echo '<div class="d-flex align-items-center gap-3">';
        echo '<img src="' . (!empty($user['profile_image']) ? $user['profile_image'] : 'https://cdn-icons-png.flaticon.com/512/295/295128.png') . '" 
                     width="40" height="40" style="border-radius:50%; object-fit:cover;">';
        echo '<div>';
        echo '<strong>' . htmlspecialchars($user['username']) . '</strong><br>';
        echo '</div>';
        echo '</div>';
        echo '<div class="d-flex gap-1">';
        echo '<a href="/api/profile_view.php?id=' . $user['id'] . '" class="btn btn-sm btn-primary">View</a>';     
        echo '</div>';
        echo '</div>';
    }
} else {
    echo '<div class="p-2 text-center">No users found</div>';
}
?>