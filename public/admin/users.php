<?php
session_start();

require_once '../../app/classes/AuthService.php';
require_once '../../app/classes/User.php'; // Assuming User.php handles user data

$authService = new AuthService();
$user = new User();

// Redirect if not logged in or not an admin
$authService->redirectIfNotLoggedIn();
$authService->redirectIfNotAdmin();

$message = '';
$messageType = '';

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $deleteUserId = $_POST['user_id'] ?? null;
    $currentUserId = $_SESSION['user_id']; // Current logged-in admin's ID

    if ($deleteUserId !== null && is_numeric($deleteUserId)) {
        // Prevent admin from deleting themselves
        if ($deleteUserId == $currentUserId) {
            $message = "You cannot delete your own admin account.";
            $messageType = 'error';
        } else {
            $deleteResult = $user->deleteUser((int)$deleteUserId);
            $message = $deleteResult['message'];
            $messageType = $deleteResult['success'] ? 'success' : 'error';
        }
    } else {
        $message = "Invalid user ID for deletion.";
        $messageType = 'error';
    }
}

// Fetch all users for display
$allUsers = $user->getAllUsers();
if ($allUsers === false) {
    $allUsers = []; // Ensure it's an empty array if fetching fails
    $message = "Failed to load users.";
    $messageType = 'error';
}

require_once '../../app/includes/admin_header.php'; // Assuming an admin header
?>

<main class="container admin-page">
    <h2>User Management</h2>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($allUsers)): ?>
                <?php foreach ($allUsers as $u): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($u['role'])); ?></td>
                        <td>
                            <form action="users.php" method="POST" style="display:inline-block;">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($u['user_id']); ?>">
                                <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Are you sure you want to delete user: <?php echo htmlspecialchars($u['username']); ?>?');"
                                    <?php echo ($u['user_id'] == $_SESSION['user_id']) ? 'disabled title="Cannot delete yourself"' : ''; ?>>
                                    Delete
                                </button>
                            </form>
                            </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4">No users found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</main>

<?php
require_once '../../app/includes/admin_footer.php'; // Assuming an admin footer
?>