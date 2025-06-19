<?php
// Start the session.
session_start();

// Include necessary classes and configurations.
require_once '../../app/classes/AuthService.php'; // Path from public/admin/
// You might include other classes here later if you want to display summary stats
// require_once '../../app/classes/User.php';
// require_once '../../app/classes/Recipe.php';

// Instantiate the AuthService.
$authService = new AuthService();

// Restrict access: Only administrators can view this page.
$authService->redirectIfNotAdmin();

// Get the admin's username from the session for a personalized welcome.
$adminUsername = $_SESSION['username'] ?? 'Admin'; // Fallback to 'Admin' if not set

// -----------------------------------------------------------------------------
// HTML Structure
require_once '../../app/includes/header.php'; // Adjust path
?>

<main class="container admin-page">
    <h2>Welcome to the Admin Dashboard, <?php echo htmlspecialchars($adminUsername); ?>!</h2>
    <p class="admin-intro">From here, you can manage all aspects of your Recipe & Meal Planning System.</p>

    <section class="admin-dashboard-cards">
        <div class="dashboard-card">
            <h3>User Management</h3>
            <p>View, edit, and delete user accounts. Manage user roles and permissions.</p>
            <a href="users.php" class="btn btn-primary">Go to Users</a>
        </div>

        <div class="dashboard-card">
            <h3>Recipe Management</h3>
            <p>Approve or reject public recipe submissions. Edit or remove any recipe.</p>
            <a href="recipes.php" class="btn btn-primary">Go to Recipes</a>
        </div>

        <div class="dashboard-card">
            <h3>Categories & Ingredients</h3>
            <p>Add, edit, or remove recipe categories and ingredients.</p>
            <a href="categories_ingredients.php" class="btn btn-primary">Go to Categories & Ingredients</a>
        </div>

        </section>

    <section class="admin-section" style="margin-top: 40px;">
        <h3>System Overview</h3>
        <div class="overview-stats">
            <div class="stat-box">
                <h4>Total Users</h4>
                <p>
                    <?php
                    // Example of how you would fetch and display a stat:
                    // $user = new User();
                    // echo $user->countAllUsers(); // Requires a countAllUsers() method in User.php
                    echo 'Loading...'; // Placeholder
                    ?>
                </p>
            </div>
            <div class="stat-box">
                <h4>Total Recipes</h4>
                <p>
                    <?php
                    // $recipe = new Recipe();
                    // echo $recipe->countAllRecipes(); // Requires a Recipe class and method
                    echo 'Loading...'; // Placeholder
                    ?>
                </p>
            </div>
            <div class="stat-box">
                <h4>Pending Recipes</h4>
                <p>
                    <?php
                    // $recipe = new Recipe();
                    // echo $recipe->countPendingRecipes(); // Requires a Recipe class and method
                    echo 'Loading...'; // Placeholder
                    ?>
                </p>
            </div>
        </div>
    </section>

</main>

<?php
require_once '../../app/includes/footer.php'; // Adjust path
?>