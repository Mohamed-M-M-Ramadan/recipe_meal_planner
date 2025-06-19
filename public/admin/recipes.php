<?php
// Start the session.
session_start();

// Include necessary classes and configurations.
require_once '../../app/classes/AuthService.php'; // Path from public/admin/
require_once '../../app/classes/Recipe.php';     // NEW: Path to the Recipe class

// Instantiate the services/models.
$authService = new AuthService();
$recipe = new Recipe(); // Instantiate the Recipe class

// Restrict access: Only administrators can view this page.
$authService->redirectIfNotAdmin();

$message = '';
$messageType = ''; // 'success' or 'error'

// -----------------------------------------------------------------------------
// Filters and Sorting
$statusFilter = $_GET['status'] ?? 'all'; // Default to showing all recipes
$searchQuery = $_GET['search'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'creation_date';
$sortOrder = $_GET['sort_order'] ?? 'DESC';

// -----------------------------------------------------------------------------
// Handle Form Submissions for Recipe Management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $recipeId = (int)$_POST['recipe_id'];
        $newStatus = $_POST['new_status'];

        if ($recipe->updateRecipeStatus($recipeId, $newStatus)) {
            $message = 'Recipe status updated successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to update recipe status. Invalid status or database error.';
            $messageType = 'error';
        }
    } elseif (isset($_POST['delete_recipe'])) {
        $recipeId = (int)$_POST['recipe_id'];

        if ($recipe->deleteRecipe($recipeId)) {
            $message = 'Recipe deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Failed to delete recipe. There might be associated data or database error.';
            $messageType = 'error';
        }
    }
    // Note: Full recipe editing (title, instructions etc.) might be done on a separate page
    // or via an AJAX call to app/api/recipe_api.php, which would use Recipe::updateRecipe.
}

// -----------------------------------------------------------------------------
// Fetch Recipes for Display based on filters
// For admin, we show all recipes regardless of user, so userId is null.
$allRecipes = $recipe->getRecipes($statusFilter, null, $searchQuery, $sortBy, $sortOrder);


// -----------------------------------------------------------------------------
// HTML Structure
require_once '../../app/includes/header.php'; // Adjust path
?>

<main class="container admin-page">
    <h2>Admin Dashboard - Manage Recipes</h2>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <section class="admin-section recipe-filter-sort-section">
        <h3>Filter & Sort Recipes</h3>
        <form action="recipes.php" method="GET" class="filter-sort-form">
            <div class="form-group">
                <label for="status">Filter by Status:</label>
                <select id="status" name="status">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Recipes</option>
                    <option value="private" <?php echo $statusFilter === 'private' ? 'selected' : ''; ?>>Private</option>
                    <option value="public_pending" <?php echo $statusFilter === 'public_pending' ? 'selected' : ''; ?>>Pending Approval</option>
                    <option value="public_approved" <?php echo $statusFilter === 'public_approved' ? 'selected' : ''; ?>>Approved Public</option>
                </select>
            </div>

            <div class="form-group">
                <label for="search">Search Title/Description:</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search by keyword...">
            </div>

            <div class="form-group">
                <label for="sort_by">Sort By:</label>
                <select id="sort_by" name="sort_by">
                    <option value="creation_date" <?php echo $sortBy === 'creation_date' ? 'selected' : ''; ?>>Creation Date</option>
                    <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>Title</option>
                    <option value="prep_time" <?php echo $sortBy === 'prep_time' ? 'selected' : ''; ?>>Preparation Time</option>
                    <option value="cook_time" <?php echo $sortBy === 'cook_time' ? 'selected' : ''; ?>>Cook Time</option>
                </select>
            </div>

            <div class="form-group">
                <label for="sort_order">Order:</label>
                <select id="sort_order" name="sort_order">
                    <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                    <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Apply Filters</button>
        </form>
    </section>

    <section class="admin-section">
        <h3>All Recipes Overview</h3>
        <?php if ($allRecipes): ?>
            <table class="admin-table recipe-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Prep/Cook Time</th>
                        <th>Servings</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allRecipes as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['recipe_id']); ?></td>
                            <td><?php echo htmlspecialchars($r['title']); ?></td>
                            <td><?php echo htmlspecialchars($r['username'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($r['prep_time'] . ' min / ' . $r['cook_time'] . ' min'); ?></td>
                            <td><?php echo htmlspecialchars($r['servings']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars(str_replace('public_', '', $r['status'])); ?>">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $r['status']))); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($r['creation_date'])); ?></td>
                            <td class="recipe-actions">
                                <a href="../recipe_detail.php?id=<?php echo htmlspecialchars($r['recipe_id']); ?>" class="btn btn-info btn-small" target="_blank">View</a>
                                <!-- Approve/Reject/Make Private buttons -->
                                <?php if ($r['status'] !== 'public_approved'): ?>
                                    <form action="recipes.php?<?php echo http_build_query($_GET); ?>" method="POST" style="display: inline;">
                                        <input type="hidden" name="recipe_id" value="<?php echo htmlspecialchars($r['recipe_id']); ?>">
                                        <input type="hidden" name="new_status" value="public_approved">
                                        <button type="submit" name="update_status" class="btn btn-success btn-small">Approve</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($r['status'] === 'public_pending' || $r['status'] === 'public_approved'): ?>
                                    <form action="recipes.php?<?php echo http_build_query($_GET); ?>" method="POST" style="display: inline;">
                                        <input type="hidden" name="recipe_id" value="<?php echo htmlspecialchars($r['recipe_id']); ?>">
                                        <input type="hidden" name="new_status" value="private">
                                        <button type="submit" name="update_status" class="btn btn-warning btn-small">Make Private</button>
                                    </form>
                                <?php endif; ?>
                                <!-- Delete button -->
                                <form action="recipes.php?<?php echo http_build_query($_GET); ?>" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete recipe "<?php echo htmlspecialchars($r['title']); ?>"? This action is irreversible and will delete all associated ingredients.');">
                                    <input type="hidden" name="recipe_id" value="<?php echo htmlspecialchars($r['recipe_id']); ?>">
                                    <button type="submit" name="delete_recipe" class="btn btn-danger btn-small">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No recipes found matching your criteria.</p>
        <?php endif; ?>
    </section>
</main>

<?php
require_once '../../app/includes/footer.php'; // Adjust path
?>