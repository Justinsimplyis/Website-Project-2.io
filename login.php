<?php
session_start();
include 'db_connection.php';

$message = "";
$toastClass = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $loginInput = trim($_POST['email']); // can be email OR username
    $password = $_POST['password'];

    // Prepare and execute (check BOTH email OR username)
    $stmt = $conn->prepare("SELECT id, username, email, password, role FROM users WHERE email = ? OR username = ?");
    $stmt->bind_param("ss", $loginInput, $loginInput);

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
           // Mark user as logged in
            $update_login_sql = "UPDATE users SET is_logged_in = 1 WHERE id = ?";
            $update_stmt = $conn->prepare($update_login_sql);
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            $update_stmt->close();

            $message = "Login successful";
            $toastClass = "bg-success";

            // ROLE-BASED REDIRECTION
            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();

        } else {
            $message = "Incorrect password";
            $toastClass = "bg-danger";
        }

    } else {
        $message = "User not found";
        $toastClass = "bg-warning";
    }

    $stmt->close();
    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" 
          content="width=device-width, initial-scale=1.0">
    <link href=
"https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href=
"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
    <link rel="shortcut icon" href=
"https://cdn-icons-png.flaticon.com/512/295/295128.png">
    <script src=
"https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../css/login.css">
    <title>Login Page</title>
</head>

<body class="bg-light">
    <div class="container p-5 d-flex flex-column align-items-center">
        <?php if ($message): ?>
            <div class="toast align-items-center text-white 
            <?php echo $toastClass; ?> border-0" role="alert"
                aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <?php echo $message; ?>
                    </div>
                    <button type="button" class="btn-close
                    btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                        aria-label="Close"></button>
                </div>
            </div>
        <?php endif; ?>
        <form id="loginForm" action="" method="post" class="form-control mt-5 p-4"
            style="height:auto; width:380px; box-shadow: rgba(60, 64, 67, 0.3) 
            0px 1px 2px 0px, rgba(60, 64, 67, 0.15) 0px 2px 6px 2px;">
            <div class="row">
                <i class="fa fa-user-circle-o fa-3x mt-1 mb-2"
          style="text-align: center; color: green;"></i>
                <h5 class="text-center p-4" 
          style="font-weight: 700;">Login Into Your Account</h5>
            </div>
            <div class="col-mb-3">
                <label for="email"><i 
                  class="fa fa-user"></i> Email or Username</label>
                <input type="text" name="email" id="email"
                  class="form-control" required>
            </div>
            <div class="col mb-3 mt-3">
                <label for="password">
                    <i class="fa fa-lock"></i> Password
                    </label>

                    <div class="input-group">
                        <input type="password" name="password" id="password" 
                        class="form-control" required>

                        <span class="input-group-text" id="togglePassword" style="cursor:pointer;">
                            <i class="fa fa-eye-slash"></i>
                            </span>
                    </div>
            </div>

            <div class="col mb-3 mt-3">
               <button type="submit" id="loginBtn"
               class="btn btn-success bg-success d-flex align-items-center justify-content-center gap-2"
               style="font-weight: 600;">
               <span id="btnText">Login</span>
               <span id="spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
            </button>

            </div>
            <p class="text-center"> <a href="reset_password.php"
                        style="text-decoration: none;font-weight: 600;  ">Forgot Password</a></p>
            <div class="col mb-2 mt-4">
                <p class="text-center" 
                  style="font-weight: 600; color: navy;"
                  >Don't Have An Account? <a href="./register.php"
                        style="text-decoration: none;">Create Account</a></p>
            </div>
        </form>
    </div>
    <script>
        var toastElList = [].slice.call(document.querySelectorAll('.toast'))
        var toastList = toastElList.map(function (toastEl) {
            return new bootstrap.Toast(toastEl, { delay: 3000 });
        });
        toastList.forEach(toast => toast.show());

        // ✅ Password visibility toggle
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);

        // Toggle icon
        this.querySelector('i').classList.toggle('fa-eye-slash');
        this.querySelector('i').classList.toggle('fa-eye');
    });
        // ✅ Login spinner logic
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        const spinner = document.getElementById('spinner');
        const btnText = document.getElementById('btnText');

        loginForm.addEventListener('submit', function () {
        // Disable button
        loginBtn.disabled = true;

        // Show spinner
        spinner.classList.remove('d-none');

        // Change text
        btnText.textContent = 'Please wait...';
    });
    </script>
</body>

</html>