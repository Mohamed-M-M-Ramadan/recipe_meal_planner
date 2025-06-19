<?php
// Start the session. This must be the very first thing in your PHP document.
session_start();

// Include necessary classes and configurations.
require_once '../app/classes/AuthService.php'; // Path from public/
require_once '../app/classes/Recipe.php';     // Path from public/

// Instantiate the services/models.
$authService = new AuthService();
$recipe = new Recipe();

$message = '';
$messageType = '';

// Check for session messages (e.g., after login/logout, or redirects from other pages)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'] ?? 'info'; // Default to 'info' or adjust based on your needs
    unset($_SESSION['message']); // Clear the message after displaying
    unset($_SESSION['message_type']);
}

// -----------------------------------------------------------------------------
// Fetch Public Recipes for display on the homepage
// We'll fetch a limited number of the most recently approved public recipes.
$publicRecipes = $recipe->getRecipes(
    'public_approved', // Only fetch approved public recipes
    null,              // No specific user ID filter
    null,              // No search query
    'creation_date',   // Sort by creation date
    'DESC'             // Newest first
);

// If no public recipes are found, it might be false. Ensure it's an empty array.
if ($publicRecipes === false) {
    $publicRecipes = [];
    error_log("Failed to fetch public recipes for homepage.");
}

// -----------------------------------------------------------------------------
// HTML Structure
require_once '../app/includes/header.php'; // Adjust path
?>

<main class="container homepage-content">
    <section class="hero-section">
        <h2>Unleash Your Inner Chef!</h2>
        <p>Your ultimate companion for discovering delicious recipes and planning your meals effortlessly.</p>
        <?php if (!$authService->isLoggedIn()): ?>
            <div class="hero-actions">
                <a href="register.php" class="btn btn-primary large-button">Get Started - Register Now!</a>
                <p>Already a member? <a href="login.php">Login here</a>.</p>
            </div>
        <?php else: ?>
            <div class="hero-actions">
                <a href="recipe_form.php" class="btn btn-primary large-button">Create Your First Recipe!</a>
                <a href="my_recipes.php" class="btn btn-secondary large-button">View My Recipes</a>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!empty($message)): // Display any messages to the user ?>
        <p class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <section class="latest-public-recipes">
        <h3>Latest Public Recipes</h3>
        <?php if (!empty($publicRecipes)): ?>
            <div class="recipe-card-grid">
                <?php foreach ($publicRecipes as $pr): ?>
                    <div class="recipe-card">
                        <img src="<?php echo htmlspecialchars($pr['image_path'] ?? 'images/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($pr['title']); ?>">
                        <h4><a href="recipe_detail.php?id=<?php echo htmlspecialchars($pr['recipe_id']); ?>"><?php echo htmlspecialchars($pr['title']); ?></a></h4>
                        <p class="recipe-meta">By <?php echo htmlspecialchars($pr['username'] ?? 'Anonymous'); ?> | Prep: <?php echo htmlspecialchars($pr['prep_time']); ?> min | Cook: <?php echo htmlspecialchars($pr['cook_time']); ?> min</p>
                        <p class="recipe-description"><?php echo htmlspecialchars(substr($pr['description'], 0, 100)); ?>...</p>
                        <a href="recipe_detail.php?id=<?php echo htmlspecialchars($pr['recipe_id']); ?>" class="btn btn-info btn-small">View Recipe</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No public recipes approved yet. Check back later!</p>
        <?php endif; ?>
        <div class="all-recipes-link">
            <a href="recipes.php" class="btn btn-secondary">Browse All Public Recipes</a>
        </div>
    </section>

</main>

<?php
require_once '../app/includes/footer.php'; // Adjust path
?>