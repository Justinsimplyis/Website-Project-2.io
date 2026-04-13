<?php
include 'db_connection.php';

// If already logged in → redirect to correct dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {

    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
        exit();
    } else {
        header("Location: dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg,#0f2027,#203a43,#2c5364);
            color:white;
            height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
        }

        .glass-box {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
            padding:40px;
            border-radius:20px;
            text-align:center;
            width:400px;
            box-shadow: 0 0 25px rgba(0,255,200,0.3);
        }

        .logo {
            width:120px;
            margin-bottom:20px;
        }

        .btn-custom {
            width:100%;
            margin-top:10px;
        }

        .footer {
            margin-top:20px;
            font-size:12px;
            opacity:0.7;
        }
    </style>
</head>

<body>

<div class="glass-box">

    <!-- Replace with your actual logo file if stored locally -->
    <img src="uploads/default.png" class="logo" alt="Logo">

    <h2></h2>
    <p class="mb-4"></p>

    <!--<a href="auth/login_and_registration.php" class="btn btn-success btn-custom">Get Started</a>-->
    <h1>Get Started</h1>
    <a href="login.php" class="btn btn-success btn-custom">Login</a>
    <a href="register.php" class="btn btn-outline-light btn-custom">Register</a>

    <div class="footer">
        <p>System Status: 
        <?php 
        if ($conn) {
            echo "<span style='color:#00ffcc;'>Database Connected ✔</span>";
        } else {
            echo "<span style='color:red;'>Database Error ✖</span>";
        }
        ?>
        </p>
        <p>© <?php echo date("Y"); ?> </p>
    </div>

</div>

</body>
</html>
