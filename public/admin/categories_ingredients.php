<?php
// Start the session.
session_start();

// Include necessary classes and configurations.
require_once '../../app/classes/AuthService.php'; // Path from public/admin/
require_once '../../app/classes/Category.php';   // Path from public/admin/
require_once '../../app/classes/Ingredient.php'; // Path from public/admin/

// Instantiate the services/models.
$authService = new AuthService();
$category = new Category();
$ingredient = new Ingredient();

// Restrict access: Only administrators can view this page.
$authService->redirectIfNotAdmin();

$message = '';
$messageType = ''; // 'success' or 'error'

// -----------------------------------------------------------------------------
// Handle Form Submissions for Category Management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_category'])) {
        $categoryName = trim($_POST['category_name']);
        $result = $category->createCategory($categoryName);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif (isset($_POST['update_category'])) {
        $categoryId = (int)$_POST['category_id'];
        $newCategoryName = trim($_POST['category_name']);
        $result = $category->updateCategory($categoryId, $newCategoryName);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif (isset($_POST['delete_category'])) {
        $categoryId = (int)$_POST['category_id'];
        $result = $category->deleteCategory($categoryId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }

    // -----------------------------------------------------------------------------
    // Handle Form Submissions for Ingredient Management
    elseif (isset($_POST['create_ingredient'])) {
        $ingredientName = trim($_POST['ingredient_name']);
        $categoryId = isset($_POST['ingredient_category_id']) && $_POST['ingredient_category_id'] !== '' ? (int)$_POST['ingredient_category_id'] : null;
        $result = $ingredient->createIngredient($ingredientName, $categoryId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif (isset($_POST['update_ingredient'])) {
        $ingredientId = (int)$_POST['ingredient_id'];
        $newIngredientName = trim($_POST['ingredient_name']);
        $newCategoryId = isset($_POST['ingredient_category_id']) && $_POST['ingredient_category_id'] !== '' ? (int)$_POST['ingredient_category_id'] : null;
        $result = $ingredient->updateIngredient($ingredientId, $newIngredientName, $newCategoryId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif (isset($_POST['delete_ingredient'])) {
        $ingredientId = (int)$_POST['ingredient_id'];
        $result = $ingredient->deleteIngredient($ingredientId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// -----------------------------------------------------------------------------
// Fetch Data for Display
$allCategories = $category->getAllCategories();
$allIngredients = $ingredient->getAllIngredients();

// Re-map categories for easy lookup by ID for ingredients table display
$categoryNamesById = [];
foreach ($allCategories as $cat) {
    $categoryNamesById[$cat['category_id']] = $cat['category_name'];
}


// -----------------------------------------------------------------------------
// HTML Structure
require_once '../../app/includes/header.php'; // Adjust path
?>

<main class="container admin-page">
    <h2>Admin Dashboard - Manage Categories & Ingredients</h2>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <section class="admin-section">
        <h3>Manage Categories</h3>
        <form action="categories_ingredients.php" method="POST" class="add-new-form">
            <div class="form-group inline-form-group">
                <label for="category_name">New Category Name:</label>
                <input type="text" id="category_name" name="category_name" placeholder="e.g., Main Dishes" required>
            </div>
            <button type="submit" name="create_category" class="btn btn-primary">Add Category</button>
        </form>

        <?php if ($allCategories): ?>
            <table class="admin-table data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allCategories as $cat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cat['category_id']); ?></td>
                            <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                            <td class="actions-column">
                                <button type="button" class="btn btn-info btn-small edit-button"
                                        data-id="<?php echo htmlspecialchars($cat['category_id']); ?>"
                                        data-name="<?php echo htmlspecialchars($cat['category_name']); ?>"
                                        data-type="category">Edit</button>
                                <form action="categories_ingredients.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete category <?php echo htmlspecialchars($cat['category_name']); ?>? This will also affect any ingredients linked to it.');">
                                    <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($cat['category_id']); ?>">
                                    <button type="submit" name="delete_category" class="btn btn-danger btn-small">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No categories found.</p>
        <?php endif; ?>
    </section>

    <section class="admin-section">
        <h3>Manage Ingredients</h3>
        <form action="categories_ingredients.php" method="POST" class="add-new-form">
            <div class="form-group inline-form-group">
                <label for="ingredient_name">New Ingredient Name:</label>
                <input type="text" id="ingredient_name" name="ingredient_name" placeholder="e.g., Chicken Breast" required>
            </div>
            <div class="form-group inline-form-group">
                <label for="ingredient_category_id">Category:</label>
                <select id="ingredient_category_id" name="ingredient_category_id">
                    <option value="">No Category</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category_id']); ?>">
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="create_ingredient" class="btn btn-primary">Add Ingredient</button>
        </form>

        <?php if ($allIngredients): ?>
            <table class="admin-table data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ingredient Name</th>
                        <th>Category</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allIngredients as $ing): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ing['ingredient_id']); ?></td>
                            <td><?php echo htmlspecialchars($ing['ingredient_name']); ?></td>
                            <td><?php echo htmlspecialchars($categoryNamesById[$ing['category_id']] ?? 'N/A'); ?></td>
                            <td class="actions-column">
                                <button type="button" class="btn btn-info btn-small edit-button"
                                        data-id="<?php echo htmlspecialchars($ing['ingredient_id']); ?>"
                                        data-name="<?php echo htmlspecialchars($ing['ingredient_name']); ?>"
                                        data-category-id="<?php echo htmlspecialchars($ing['category_id'] ?? ''); ?>"
                                        data-type="ingredient">Edit</button>
                                <form action="categories_ingredients.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete ingredient <?php echo htmlspecialchars($ing['ingredient_name']); ?>? This will also affect any recipes using it.');">
                                    <input type="hidden" name="ingredient_id" value="<?php echo htmlspecialchars($ing['ingredient_id']); ?>">
                                    <button type="submit" name="delete_ingredient" class="btn btn-danger btn-small">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No ingredients found.</p>
        <?php endif; ?>
    </section>
</main>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h3 id="modalTitle">Edit Item</h3>
        <form id="editForm" action="categories_ingredients.php" method="POST">
            <input type="hidden" name="item_id" id="modalItemId">
            <input type="hidden" name="action_type" id="modalActionType"> <div class="form-group">
                <label for="modalItemName">Name:</label>
                <input type="text" id="modalItemName" name="item_name" required>
            </div>

            <div class="form-group" id="modalCategorySelectGroup" style="display: none;">
                <label for="modalItemCategory">Category:</label>
                <select id="modalItemCategory" name="item_category_id">
                    <option value="">No Category</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category_id']); ?>">
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<?php
require_once '../../app/includes/footer.php'; // Adjust path
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-button');
    const modal = document.getElementById('editModal');
    const closeButton = document.querySelector('.close-button');
    const modalTitle = document.getElementById('modalTitle');
    const modalItemId = document.getElementById('modalItemId');
    const modalItemName = document.getElementById('modalItemName');
    const modalActionType = document.getElementById('modalActionType');
    const modalCategorySelectGroup = document.getElementById('modalCategorySelectGroup');
    const modalItemCategory = document.getElementById('modalItemCategory');
    const editForm = document.getElementById('editForm');

    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const type = this.dataset.type; // 'category' or 'ingredient'
            const categoryId = this.dataset.categoryId; // Only for ingredient type

            modalItemId.value = id;
            modalItemName.value = name;

            if (type === 'category') {
                modalTitle.textContent = 'Edit Category';
                modalActionType.name = 'update_category'; // Set action for PHP
                modalCategorySelectGroup.style.display = 'none'; // Hide category select for categories
                modalItemCategory.removeAttribute('required');
            } else if (type === 'ingredient') {
                modalTitle.textContent = 'Edit Ingredient';
                modalActionType.name = 'update_ingredient'; // Set action for PHP
                modalCategorySelectGroup.style.display = 'block'; // Show category select for ingredients
                modalItemCategory.value = categoryId; // Pre-select current category
                modalItemCategory.setAttribute('required', 'required'); // Ingredients should ideally have a category, adjust as needed
            }

            modal.style.display = 'block';
        });
    });

    closeButton.addEventListener('click', function() {
        modal.style.display = 'none';
    });

    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });

    // Handle form submission from modal
    editForm.addEventListener('submit', function(event) {
        // The hidden input 'action_type' will correctly tell PHP which update to perform
        // Rename the generic 'item_id' and 'item_name' to specific names expected by PHP
        const actionType = modalActionType.name; // e.g., 'update_category' or 'update_ingredient'
        editForm.querySelector('#modalItemId').name = actionType === 'update_category' ? 'category_id' : 'ingredient_id';
        editForm.querySelector('#modalItemName').name = 'ingredient_name'; // Both use 'ingredient_name' for now, but rename if needed
                                                                         // For category, it will become category_name.
        if (actionType === 'update_category') {
             editForm.querySelector('#modalItemName').name = 'category_name'; // Correct name for category update
        } else if (actionType === 'update_ingredient') {
             editForm.querySelector('#modalItemCategory').name = 'ingredient_category_id'; // Correct name for ingredient category
        }
    });
});
</script>