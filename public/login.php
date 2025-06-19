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
    $password = $_POST['password'] ?? ''; // Do not trim password as it might contain leading/trailing spaces

    $loginResult = $authService->login($username, $password);

    if ($loginResult['success']) {
        $_SESSION['message'] = $loginResult['message'];
        $_SESSION['message_type'] = 'success';
        header("Location: index.php"); // Redirect to homepage on successful login
        exit();
    } else {
        $message = $loginResult['message'];
        $messageType = 'error';
    }
}

require_once '../app/includes/header.php'; // Adjust path
?>

<main class="container form-page">
    <h2>Login</h2>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form action="login.php" method="POST" class="standard-form">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
    </form>
    <p class="form-footer">Don't have an account? <a href="register.php">Register here</a>.</p>
</main>

<?php
require_once '../app/includes/footer.php'; // Adjust path
?>