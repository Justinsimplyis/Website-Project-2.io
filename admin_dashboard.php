<?php
session_start();

// Check if the user is logged in, if
// not then redirect them to the login page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
// Get username from session
$username = $_SESSION['username'];
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href=
"https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href=
"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    <link rel="shortcut icon" href="https://cdn-icons-png.flaticon.com/512/295/295128.png">
    <script src=
"https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport"
  content="width=device-width, initial-scale=1.0">
    <title> Admin Dashboard</title>
</head>

<body>
    <nav class="navbar navbar-expand-sm navbar-light bg-success">
        <div class="container">
            <a class="navbar-brand" href="#" style="font-weight:bold; color:white;"></a>
            <span style="color:white; font-weight:bold;">
        Welcome Admin, <?php echo htmlspecialchars($username); ?>
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

                    <!--Search-->
                    <a href="" class="btn btn-light my-2 my-sm-0"
                        style="font-weight:bolder;color:grey;">
                        <i class="fa fa-user-circle"></i>
                    </a>
                    <!-- Notifications-->
                    <a href="" class="btn btn-light my-2 my-sm-0"
                        style="font-weight:bolder;color:bell;">
                        <i class="fa fa-bell"></i>
                    </a>


                    <!-- Profile -->
                    <a href="a_profile.php" class="btn btn-light my-2 my-sm-0"
                        style="font-weight:bolder;color:orange;">
                        <i class="fa fa-user-circle"></i>
                    </a>

                    <!-- Logout -->
                    <a href="logout.php" class="btn btn-light my-2 my-sm-0"
                        onclick="return confirm('Are you sure to logout?')"
                        style="font-weight:bolder;color:green;">
                        Logout
                    </a>

                </div>
            </div>
        </div>
    </nav>

    <div>
       

    </div>
</body>

</html>