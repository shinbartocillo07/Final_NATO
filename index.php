<?php
// Load shared configuration, database connection, and helper functions.
require_once 'config.php';

// Initialize the error message that may be displayed to the user.
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize input values before using them in database queries.
    $username = sanitize($_POST['username']);
    // Password matches are performed against the stored hash.
    $password = $_POST['password'];

    // Query the database for the entered username.
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        // Compare the entered password to the hashed password from the database.
        if (verifyPassword($password, $user['password'])) {
            if ($user['role'] === 'driver') {
                $driverCheck = $conn->prepare("SELECT status FROM drivers WHERE user_id = ?");
                $driverCheck->bind_param("i", $user['id']);
                $driverCheck->execute();
                $driverResult = $driverCheck->get_result();

                if ($driverResult->num_rows > 0) {
                    $driverStatus = $driverResult->fetch_assoc()['status'];
                    if ($driverStatus !== 'approved') {
                        $error = 'Your driver account is still pending admin approval. Please wait for the approval email before logging in.';
                        $driverCheck->close();
                        return;
                    }
                }
                $driverCheck->close();
            }

            // Store user session values to keep the user logged in.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // Redirect user to the dashboard that matches their role.
            switch ($user['role']) {
                case 'admin':
                    redirect('admin/dashboard.php');
                    break;
                case 'parent':
                    redirect('parent/dashboard.php');
                    break;
                case 'driver':
                    redirect('driver/dashboard.php');
                    break;
            }
        } else {
            $error = 'Invalid password';
        }
    } else {
        $error = 'User not found';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - School Transport System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>School Service Transportation System</h1>
        <div class="login-form">
            <h2>Login</h2>
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Login</button>
            </form>
            <p><a href="forgot_password.php">Forgot password?</a></p>
            <p>Don't have an account? <a href="register.php">Register</a></p>
        </div>
    </div>
</body>
</html>