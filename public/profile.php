<?php
session_start();

require_once '../app/classes/AuthService.php';
require_once '../app/classes/User.php'; // Assuming User.php handles user data

$authService = new AuthService();
$user = new User();

// Redirect if not logged in
$authService->redirectIfNotLoggedIn();

$currentUserId = $_SESSION['user_id']; // CORRECT: Get current user ID from session
$username = $_SESSION['username'];    // CORRECT: Get username from session

$message = '';
$messageType = '';

// Fetch current user's details
$userDetails = $user->findById($currentUserId);

if (!$userDetails) {
    // This should ideally not happen if user is logged in, but good for robustness
    $message = "Error: User profile not found.";
    $messageType = 'error';
    // Optionally redirect or handle more gracefully
}

// Handle profile update (only username and password in this example)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = trim($_POST['username'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $currentPassword = $_POST['current_password'] ?? ''; // Required for password change

    // Ensure current password is provided for any changes
    if (empty($currentPassword)) {
        $message = "Please enter your current password to make changes.";
        $messageType = 'error';
    } elseif (!password_verify($currentPassword, $userDetails['password'])) {
        $message = "Current password incorrect.";
        $messageType = 'error';
    } else {
        $updateResult = $user->updateUser($currentUserId, $newUsername, $newPassword);
        $message = $updateResult['message'];
        $messageType = $updateResult['success'] ? 'success' : 'error';

        if ($updateResult['success']) {
            // Update session username if it changed
            if ($newUsername && $newUsername !== $username) {
                $_SESSION['username'] = $newUsername;
                $username = $newUsername; // Update local variable
            }
            // Re-fetch details to reflect changes
            $userDetails = $user->findById($currentUserId);
        }
    }
}

require_once '../app/includes/header.php'; // Adjust path
?>

<main class="container profile-page">
    <h2>My Profile</h2>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <?php if ($userDetails): ?>
        <form action="profile.php" method="POST" class="standard-form">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($userDetails['username']); ?>" required>
            </div>
            <div class="form-group">
                <label for="new_password">New Password (leave blank to keep current):</label>
                <input type="password" id="new_password" name="password">
                <small>Enter a new password if you wish to change it.</small>
            </div>
             <div class="form-group">
                <label for="current_password">Current Password (required to save changes):</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <button type="submit" class="btn btn-primary">Update Profile</button>
        </form>
    <?php else: ?>
        <p>Unable to load profile data.</p>
    <?php endif; ?>
</main>

<?php
require_once '../app/includes/footer.php'; // Adjust path
?>