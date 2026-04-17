<?php
include 'C:/Users/User/Documents/GitHub/Website-Project-2/database/db_connection.php';

$message = "";
$toastClass = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // ✅ Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format";
        $toastClass = "bg-warning";

    // ✅ Password strength validation
    } elseif (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[\W]/', $password)
    ) {
        $message = "Password must be at least 8 characters and include uppercase, lowercase, number, and special character.";
        $toastClass = "bg-danger";

    // ✅ Password match check
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match";
        $toastClass = "bg-warning";

    } else {

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $message = "Password updated successfully!";
                $toastClass = "bg-success";
            } else {
                $message = "No account found with that email";
                $toastClass = "bg-warning";
            }
        } else {
            $message = "Error updating password";
            $toastClass = "bg-danger";
        }

        $stmt->close();
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<title>Reset Password</title>
</head>

<body class="bg-light">

<div class="container p-5 d-flex flex-column align-items-center">

<?php if ($message): ?>
<div class="toast align-items-center text-white border-0"
     style="background-color: <?php echo $toastClass === 'bg-success' ? '#28a745' :
     ($toastClass === 'bg-danger' ? '#dc3545' :
     ($toastClass === 'bg-warning' ? '#ffc107' : '')); ?>;">
    <div class="d-flex">
        <div class="toast-body"><?php echo $message; ?></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast"></button>
    </div>
</div>
<?php endif; ?>

<form method="post" class="form-control mt-5 p-4"
      style="width:380px; box-shadow: rgba(0,0,0,0.2) 0px 2px 8px;">

    <div class="text-center">
        <i class="fa fa-user-circle-o fa-3x mb-2 text-success"></i>
        <h5 class="fw-bold">Change Your Password</h5>
    </div>

    <!-- Email -->
    <div class="mb-3 position-relative">
        <label><i class="fa fa-envelope"></i> Email</label>
        <input type="email" name="email" id="email" class="form-control" required>
        <span id="email-check" class="position-absolute"
              style="right:10px; top:50%; transform:translateY(-50%);"></span>
    </div>

    <!-- Password -->
    <div class="mb-3">
        <label><i class="fa fa-lock"></i> Password</label>

        <div class="input-group">
            <input type="password" name="password" id="password" class="form-control" required>
            <span class="input-group-text" id="togglePassword" style="cursor:pointer;">
                <i class="fa fa-eye-slash"></i>
            </span>
        </div>

        <!-- Strength Bar -->
        <div class="progress mt-2" style="height:5px;">
            <div id="strengthBar" class="progress-bar"></div>
        </div>
        <small id="strengthText"></small>
    </div>

    <!-- Confirm Password -->
    <div class="mb-3">
        <label><i class="fa fa-lock"></i> Confirm Password</label>
        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
        <small id="matchText"></small>
    </div>

    <button type="submit" class="btn btn-dark w-100 fw-bold">
        Reset Password
    </button>

    <div class="mt-3 text-center">
        <p class="fw-bold text-primary">
            <a href="./register.php">Create Account</a> OR
            <a href="./login.php">Login</a>
        </p>
    </div>

</form>
</div>

<script>
const password = document.getElementById('password');
const confirmPassword = document.getElementById('confirm_password');
const strengthBar = document.getElementById('strengthBar');
const strengthText = document.getElementById('strengthText');
const matchText = document.getElementById('matchText');

// Toggle password visibility
document.getElementById('togglePassword').addEventListener('click', function () {
    const type = password.type === 'password' ? 'text' : 'password';
    password.type = type;

    this.querySelector('i').classList.toggle('fa-eye');
    this.querySelector('i').classList.toggle('fa-eye-slash');
});

// Strength checker
password.addEventListener('input', function () {
    let val = password.value;
    let strength = 0;

    if (val.length >= 8) strength++;
    if (/[A-Z]/.test(val)) strength++;
    if (/[a-z]/.test(val)) strength++;
    if (/[0-9]/.test(val)) strength++;
    if (/[\W]/.test(val)) strength++;

    const levels = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    const colors = ['bg-danger','bg-warning','bg-info','bg-primary','bg-success'];

    strengthBar.style.width = (strength * 20) + '%';
    strengthBar.className = 'progress-bar ' + colors[strength - 1] || 'bg-danger';
    strengthText.innerText = levels[strength - 1] || 'Too Weak';
});

// Confirm password match
confirmPassword.addEventListener('input', function () {
    if (confirmPassword.value === password.value) {
        matchText.innerText = "Passwords match";
        matchText.style.color = "green";
    } else {
        matchText.innerText = "Passwords do not match";
        matchText.style.color = "red";
    }
});

// Email check AJAX
$(document).ready(function () {
    $('#email').on('blur', function () {
        let email = $(this).val();

        if (email) {
            $.post('public/check_email.php', { email: email }, function (response) {
                if (response === 'exists') {
                    $('#email-check').html('<i class="fa fa-check text-success"></i>');
                } else {
                    $('#email-check').html('<i class="fa fa-times text-danger"></i>');
                }
            });
        }
    });

    // Toast
    let toastElList = [].slice.call(document.querySelectorAll('.toast'));
    toastElList.map(el => new bootstrap.Toast(el, {delay: 3000}).show());
});
</script>

</body>
</html>
