<?php
session_start();

require_once '../app/classes/AuthService.php';

$authService = new AuthService();

// If user is already logged in, redirect them to the homepage
if ($authService->isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = 'error';
    } else {
        $registerResult = $authService->register($username, $password);

        if ($registerResult['success']) {
            $_SESSION['message'] = $registerResult['message'];
            $_SESSION['message_type'] = 'success';
            header("Location: login.php"); // Redirect to login page after successful registration
            exit();
        } else {
            $message = $registerResult['message'];
            $messageType = 'error';
        }
    }
}

require_once '../app/includes/header.php'; // Adjust path
?>

<main class="container form-page">
    <h2>Register</h2>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form action="register.php" method="POST" class="standard-form">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <button type="submit" class="btn btn-primary">Register</button>
    </form>
    <p class="form-footer">Already have an account? <a href="login.php">Login here</a>.</p>
</main>

<?php
require_once '../app/includes/footer.php'; // Adjust path
?>