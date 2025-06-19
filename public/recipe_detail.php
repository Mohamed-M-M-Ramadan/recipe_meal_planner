<?php
session_start();

// Include necessary classes and configurations.
require_once '../app/classes/AuthService.php'; // Path from public/
require_once '../app/classes/Recipe.php';     // Path from public/
require_once '../app/classes/User.php';       // Path from public/

// Instantiate services/models.
$authService = new AuthService();
$recipe = new Recipe();
$user = new User();

$recipeDetails = null;
$message = '';
$messageType = '';

// Check if a recipe ID is provided in the URL.
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $recipeId = (int)$_GET['id'];
    $currentUserId = $_SESSION['user_id'] ?? null; // CORRECT: Get current user ID from session
    $isAdmin = $authService->isAdmin();

    // Attempt to fetch the recipe.
    $possibleStatuses = ['public_approved'];
    if ($currentUserId) { // If any user is logged in
        $possibleStatuses[] = 'private'; // User can see their own private recipes
        $possibleStatuses[] = 'public_pending'; // User can see their own pending recipes
        $possibleStatuses[] = 'public_rejected'; // User can see their own rejected recipes
    }
    if ($isAdmin) {
        // Admin can see all recipes regardless of status or owner
        $possibleStatuses = ['public_approved', 'public_pending', 'public_rejected', 'private'];
    }

    $fetchedRecipes = $recipe->getRecipes(
        $possibleStatuses,
        null, // No username filter needed here, as we're fetching by ID
        null, // No search query
        null, null, // No specific ordering for a single recipe fetch
        $recipeId // Pass the recipe ID directly
    );

    // If getRecipes returned an array and it contains our recipe
    if (is_array($fetchedRecipes) && !empty($fetchedRecipes) && $fetchedRecipes[0]['recipe_id'] == $recipeId) {
        $recipeDetails = $fetchedRecipes[0];

        // Access control check:
        // - If recipe is public_approved, anyone can see it.
        // - If recipe is private or pending/rejected, only the owner or an admin can see it.
        $isOwner = ($currentUserId && $recipeDetails['user_id'] == $currentUserId);

        if ($recipeDetails['status'] === 'public_approved' || $isOwner || $isAdmin) {
            // Recipe is accessible. Fetch creator's username.
            $creator = $user->findById($recipeDetails['user_id']);
            $recipeDetails['creator_username'] = $creator ? $creator['username'] : 'Unknown User';

            // Fetch ingredients for this recipe.
            $recipeDetails['ingredients'] = $recipe->getIngredientsForRecipe($recipeId);

        } else {
            $message = "You do not have permission to view this recipe.";
            $messageType = 'error';
            $recipeDetails = null; // Clear details as they are not accessible
        }

    } else {
        $message = "Recipe not found.";
        $messageType = 'error';
    }
} else {
    $message = "Invalid recipe ID provided.";
    $messageType = 'error';
}

// -----------------------------------------------------------------------------
// HTML Structure
require_once '../app/includes/header.php'; // Adjust path
?>

<main class="container recipe-detail-page">
    <?php if (!empty($message)): ?>
        <p class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <?php if ($recipeDetails): ?>
        <article class="recipe-article">
            <header class="recipe-header">
                <h1><?php echo htmlspecialchars($recipeDetails['title']); ?></h1>
                <p class="recipe-meta-top">
                    By: <span class="creator-name"><?php echo htmlspecialchars($recipeDetails['creator_username']); ?></span> |
                    Status: <span class="recipe-status status-<?php echo htmlspecialchars($recipeDetails['status']); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $recipeDetails['status']))); ?></span>
                </p>
                <?php if ($authService->isLoggedIn() && $recipeDetails['user_id'] == $_SESSION['user_id']): ?>
                    <div class="recipe-actions">
                        <a href="recipe_form.php?edit=<?php echo htmlspecialchars($recipeDetails['recipe_id']); ?>" class="btn btn-secondary">Edit Recipe</a>
                    </div>
                <?php endif; ?>
            </header>

            <figure class="recipe-image-container">
                <img src="<?php echo htmlspecialchars($recipeDetails['image_path'] ?? '../public/images/placeholder.png'); ?>" alt="<?php echo htmlspecialchars($recipeDetails['title']); ?>">
                <figcaption><?php echo htmlspecialchars($recipeDetails['title']); ?></figcaption>
            </figure>

            <section class="recipe-description-section">
                <h3>Description</h3>
                <p><?php echo nl2br(htmlspecialchars($recipeDetails['description'])); ?></p>
            </section>

            <section class="recipe-info-grid">
                <div class="info-card">
                    <h4>Preparation Time</h4>
                    <p><?php echo htmlspecialchars($recipeDetails['prep_time']); ?> minutes</p>
                </div>
                <div class="info-card">
                    <h4>Cook Time</h4>
                    <p><?php echo htmlspecialchars($recipeDetails['cook_time']); ?> minutes</p>
                </div>
                <div class="info-card">
                    <h4>Servings</h4>
                    <p><?php echo htmlspecialchars($recipeDetails['servings']); ?></p>
                </div>
                <?php if ($recipeDetails['category_name']): // Display category only if available and fetched ?>
                    <div class="info-card">
                        <h4>Category</h4>
                        <p><?php echo htmlspecialchars($recipeDetails['category_name']); ?></p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="recipe-ingredients-section">
                <h3>Ingredients</h3>
                <ul>
                    <?php if (!empty($recipeDetails['ingredients'])): ?>
                        <?php foreach ($recipeDetails['ingredients'] as $ingredient): ?>
                            <li>
                                <?php
                                    echo htmlspecialchars($ingredient['quantity']);
                                    if (!empty($ingredient['unit'])) {
                                        echo ' ' . htmlspecialchars($ingredient['unit']);
                                    }
                                    echo ' ' . htmlspecialchars($ingredient['ingredient_name']);
                                ?>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No ingredients listed.</li>
                    <?php endif; ?>
                </ul>
            </section>

            <section class="recipe-instructions-section">
                <h3>Instructions</h3>
                <ol>
                    <?php
                    // Split instructions by new lines and display as an ordered list.
                    $instructions = array_filter(explode("\n", $recipeDetails['instructions']));
                    if (!empty($instructions)) {
                        foreach ($instructions as $instruction) {
                            echo '<li>' . nl2br(htmlspecialchars(trim($instruction))) . '</li>';
                        }
                    } else {
                        echo '<li>No instructions provided.</li>';
                    }
                    ?>
                </ol>
            </section>
        </article>
    <?php endif; ?>
</main>

<?php
require_once '../app/includes/footer.php'; // Adjust path
?>