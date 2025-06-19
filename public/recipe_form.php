<?php
// Start the session.
session_start();

// Include necessary classes.
require_once '../app/classes/AuthService.php';
require_once '../app/classes/Recipe.php'; // Required if fetching recipe to edit
require_once '../app/classes/RecipeService.php'; // For saving/updating recipes with ingredients
require_once '../app/classes/Ingredient.php'; // For ingredient lookup (though not directly used here, its methods might be called by RecipeService)

// Instantiate services/models.
$authService = new AuthService();
$recipe = new Recipe(); // Used for findById
//$recipeService = new RecipeService(); // Used for saving/updating with ingredients
// $ingredient = new Ingredient(); // Not directly instantiated here, as RecipeService handles ingredient interaction

// Ensure the user is logged in.
$authService->redirectIfNotLoggedIn('You must be logged in to create or edit recipes.');

$currentUserId = $_SESSION['user_id'];
$message = '';
$messageType = '';

$isEditing = false;
$recipeId = null;
$currentRecipe = [];
$currentIngredients = [];

// Handle GET request for editing an existing recipe
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $recipeId = (int)$_GET['id'];
    $currentRecipe = $recipe->findById($recipeId);

    // Ensure the recipe exists and belongs to the current user OR the current user is an admin
    if ($currentRecipe && ($currentRecipe['user_id'] === $currentUserId || $authService->isAdmin())) { // CORRECTED: $userId to $currentUserId, and $authService->isAdmin()
        $isEditing = true;
        $currentIngredients = $recipeService->getRecipeIngredients($recipeId);
        if (!$currentIngredients) {
            $currentIngredients = []; // Ensure it's an empty array if no ingredients found
        }
    } else {
        $_SESSION['message'] = 'Recipe not found or you do not have permission to edit it.';
        $_SESSION['message_type'] = 'error';
        header('Location: my_recipes.php'); // Redirect to user's recipes or home
        exit();
    }
}

// Handle POST request for saving/updating a recipe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['create_recipe']) || isset($_POST['update_recipe']))) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $instructions = trim($_POST['instructions']);
    $prepTime = (int)$_POST['prep_time'];
    $cookTime = (int)$_POST['cook_time'];
    $servings = (int)$_POST['servings'];
    $recipeStatus = $_POST['status']; // 'private' or 'public_pending'

    // Process uploaded image
    $imagePath = null;
    if ($isEditing && !empty($currentRecipe['image_path'])) {
        $imagePath = $currentRecipe['image_path']; // Keep existing image if not new upload
    }

    if (isset($_FILES['recipe_image']) && $_FILES['recipe_image']['error'] === UPLOAD_ERR_OK) { // CORRECTED: name from 'image' to 'recipe_image'
        $uploadDir = '../public/images/recipes/'; // Adjust as needed
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $imageFileType = strtolower(pathinfo($_FILES['recipe_image']['name'], PATHINFO_EXTENSION));
        $fileName = uniqid('recipe_', true) . '.' . $imageFileType;
        $targetFilePath = $uploadDir . $fileName;

        $check = getimagesize($_FILES['recipe_image']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['recipe_image']['tmp_name'], $targetFilePath)) {
                $imagePath = 'images/recipes/' . $fileName; // Path relative to public/
            } else {
                $message = "Failed to upload image.";
                $messageType = 'error';
            }
        } else {
            $message = "File is not an image.";
            $messageType = 'error';
        }
    }

    // Collect ingredient data from the form
    $ingredientsData = [];
    if (isset($_POST['ingredient_name']) && is_array($_POST['ingredient_name'])) {
        foreach ($_POST['ingredient_name'] as $index => $name) {
            $name = trim($name);
            $quantity = trim($_POST['ingredient_quantity'][$index]);
            $unit = trim($_POST['ingredient_unit'][$index]);
            $ingredientId = !empty($_POST['ingredient_id'][$index]) ? (int)$_POST['ingredient_id'][$index] : null;

            // Only add if ingredient name is not empty
            if (!empty($name)) {
                $ingredientsData[] = [
                    'id' => $ingredientId, // Will be null if new ingredient
                    'name' => $name,
                    'quantity' => $quantity,
                    'unit' => $unit
                ];
            }
        }
    }

    // Basic validation for required fields
    if (empty($title) || empty($instructions) || empty($ingredientsData)) {
        $message = "Please fill in recipe title, instructions, and add at least one ingredient with quantity and unit.";
        $messageType = 'error';
    } elseif (!empty($messageType) && $messageType === 'error') {
        // If there was an image upload error, stop processing
    } else {
        $recipeDetails = [
            'title' => $title,
            'description' => $description,
            'instructions' => $instructions,
            'prep_time' => $prepTime,
            'cook_time' => $cookTime,
            'servings' => $servings,
            'image_path' => $imagePath // Use the determined image path
        ];

        if (isset($_POST['update_recipe']) && $isEditing) {
            // Update existing recipe
            $result = $recipeService->updateRecipeWithIngredients($recipeId, $recipeDetails, $ingredientsData, $recipeStatus);
        } else {
            // Create new recipe
            $result = $recipeService->saveRecipeWithIngredients($currentUserId, $recipeDetails, $ingredientsData, $recipeStatus); // CORRECTED: $userId to $currentUserId
        }

        if ($result['success']) {
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = 'success';
            header('Location: my_recipes.php'); // Redirect to user's recipes list
            exit();
        } else {
            $message = $result['message'];
            $messageType = 'error';
            // If it was an update, re-fetch current recipe data to reflect any unsaved changes or for user to re-edit
            if ($isEditing && $recipeId) {
                $currentRecipe = $recipe->findById($recipeId);
                $currentIngredients = $recipeService->getRecipeIngredients($recipeId);
            }
        }
    }
}

// Populate form fields for editing
$formTitle = $isEditing ? htmlspecialchars($currentRecipe['title']) : '';
$formDescription = $isEditing ? htmlspecialchars($currentRecipe['description']) : '';
$formInstructions = $isEditing ? htmlspecialchars($currentRecipe['instructions']) : '';
$formPrepTime = $isEditing ? htmlspecialchars($currentRecipe['prep_time']) : '';
$formCookTime = $isEditing ? htmlspecialchars($currentRecipe['cook_time']) : '';
$formServings = $isEditing ? htmlspecialchars($currentRecipe['servings']) : '';
$formStatus = $isEditing ? htmlspecialchars($currentRecipe['status']) : 'private';

require_once '../app/includes/header.php';
?>

<main class="container recipe-form-page">
    <h2><?php echo $isEditing ? 'Edit Your Recipe' : 'Create a New Recipe'; ?></h2>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form action="recipe_form.php<?php echo $isEditing ? '?id=' . htmlspecialchars($recipeId) : ''; ?>" method="POST" class="recipe-form" enctype="multipart/form-data">
        <div class="form-section">
            <h3>Recipe Details</h3>
            <div class="form-group">
                <label for="title">Recipe Title:</label>
                <input type="text" id="title" name="title" value="<?php echo $formTitle; ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Short Description:</label>
                <textarea id="description" name="description" rows="3" required><?php echo $formDescription; ?></textarea>
            </div>
            <div class="form-group">
                <label for="instructions">Instructions:</label>
                <textarea id="instructions" name="instructions" rows="8" required><?php echo $formInstructions; ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group half-width">
                    <label for="prep_time">Preparation Time (minutes):</label>
                    <input type="number" id="prep_time" name="prep_time" value="<?php echo $formPrepTime; ?>" required min="0">
                </div>
                <div class="form-group half-width">
                    <label for="cook_time">Cook Time (minutes):</label>
                    <input type="number" id="cook_time" name="cook_time" value="<?php echo $formCookTime; ?>" required min="0">
                </div>
            </div>
            <div class="form-group">
                <label for="servings">Servings:</label>
                <input type="number" id="servings" name="servings" value="<?php echo $formServings; ?>" required min="1">
            </div>
            <div class="form-group">
                <label for="recipe_image">Recipe Image (Optional):</label> <input type="file" id="recipe_image" name="recipe_image" accept="image/*"> <?php if ($isEditing && !empty($currentRecipe['image_path'])): ?>
                    <p class="current-image-preview">Current Image: <img src="../public/<?php echo htmlspecialchars($currentRecipe['image_path']); ?>" alt="Current Recipe Image" style="max-width: 150px; height: auto; display: block; margin-top: 10px;"></p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="status">Recipe Visibility:</label>
                <select id="status" name="status" required>
                    <option value="private" <?php echo $formStatus === 'private' ? 'selected' : ''; ?>>Private (Only visible to you)</option>
                    <option value="public_pending" <?php echo ($formStatus === 'public_pending' || $formStatus === 'public_approved' || $formStatus === 'public_rejected') ? 'selected' : ''; ?>>Public (Requires Admin Approval)</option>
                </select>
            </div>
        </div>

        <div class="form-section">
            <h3>Ingredients</h3>
            <div id="ingredients-container">
                <?php if ($isEditing && !empty($currentIngredients)): ?>
                    <?php foreach ($currentIngredients as $index => $ing): ?>
                        <div class="ingredient-item">
                            <input type="hidden" name="ingredient_id[]" value="<?php echo htmlspecialchars($ing['ingredient_id'] ?? ''); ?>">
                            <input type="text" name="ingredient_quantity[]" value="<?php echo htmlspecialchars($ing['quantity'] ?? ''); ?>" placeholder="Quantity (e.g., 2)" required>
                            <input type="text" name="ingredient_unit[]" value="<?php echo htmlspecialchars($ing['unit'] ?? ''); ?>" placeholder="Unit (e.g., cups)" required>
                            <input type="text" name="ingredient_name[]" value="<?php echo htmlspecialchars($ing['ingredient_name'] ?? ''); ?>" placeholder="Ingredient Name (e.g., Flour)" class="ingredient-name-input" required>
                            <button type="button" class="btn btn-danger btn-small remove-ingredient">Remove</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="ingredient-item">
                        <input type="hidden" name="ingredient_id[]" value="">
                        <input type="text" name="ingredient_quantity[]" placeholder="Quantity (e.g., 2)" required>
                        <input type="text" name="ingredient_unit[]" placeholder="Unit (e.g., cups)" required>
                        <input type="text" name="ingredient_name[]" placeholder="Ingredient Name (e.g., Flour)" class="ingredient-name-input" required>
                        <button type="button" class="btn btn-danger btn-small remove-ingredient">Remove</button>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" id="add-ingredient" class="btn btn-secondary">Add Another Ingredient</button>
        </div>

        <button type="submit" name="<?php echo $isEditing ? 'update_recipe' : 'create_recipe'; ?>" class="btn btn-primary large-button">
            <?php echo $isEditing ? 'Update Recipe' : 'Create Recipe'; ?>
        </button>
    </form>
</main>

<?php
require_once '../app/includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ingredientsContainer = document.getElementById('ingredients-container');
    const addIngredientButton = document.getElementById('add-ingredient');

    // Function to add a new ingredient row
    function addIngredientRow(ingredientId = '', quantity = '', unit = '', name = '') {
        const div = document.createElement('div');
        div.classList.add('ingredient-item');
        div.innerHTML = `
            <input type="hidden" name="ingredient_id[]" value="${ingredientId}">
            <input type="text" name="ingredient_quantity[]" value="${quantity}" placeholder="Quantity (e.g., 2)" required>
            <input type="text" name="ingredient_unit[]" value="${unit}" placeholder="Unit (e.g., cups)" required>
            <input type="text" name="ingredient_name[]" value="${name}" placeholder="Ingredient Name (e.g., Flour)" class="ingredient-name-input" required>
            <button type="button" class="btn btn-danger btn-small remove-ingredient">Remove</button>
        `;
        ingredientsContainer.appendChild(div);
        setupRemoveButton(div.querySelector('.remove-ingredient'));
        setupAutocomplete(div.querySelector('.ingredient-name-input'));
    }

    // Function to set up remove button listener
    function setupRemoveButton(button) {
        button.addEventListener('click', function() {
            // Ensure at least one ingredient row remains
            if (ingredientsContainer.children.length > 1) {
                this.closest('.ingredient-item').remove();
            } else {
                alert('A recipe must have at least one ingredient.');
            }
        });
    }

    // Function to set up autocomplete for ingredient name inputs
    function setupAutocomplete(inputElement) {
        new autoComplete({
            selector: inputElement,
            data: {
                src: async (query) => {
                    try {
                        // Fetch data from a PHP API endpoint
                        const source = await fetch(`../app/api/ingredient_autocomplete.php?query=${query}`);
                        const data = await source.json();
                        return data.map(item => ({
                            id: item.ingredient_id,
                            name: item.ingredient_name
                        }));
                    } catch (error) {
                        console.error('Error fetching ingredient autocomplete data:', error);
                        return [];
                    }
                },
                keys: ["name"]
            },
            resultsList: {
                element: (list, position) => {
                    // Adjust list position if needed
                },
                noResults: true,
                maxResults: 10,
                tabSelect: true
            },
            resultItem: {
                highlight: true
            },
            events: {
                input: {
                    selection: (event) => {
                        const feedback = event.detail.selection.value;
                        event.detail.input.value = feedback.name;
                        // Set the hidden ingredient ID field
                        // Navigating to previousSibling multiple times to find the hidden input
                        let hiddenInput = event.detail.input;
                        for(let i = 0; i < 3; i++) { // Adjusted from 3 to 2, assuming hidden input is 3rd sibling from end
                           if (hiddenInput && hiddenInput.previousElementSibling) {
                               hiddenInput = hiddenInput.previousElementSibling;
                           } else {
                               hiddenInput = null;
                               break;
                           }
                        }
                        // Correct logic to find the hidden input if it's the first child of the parent div
                        if (event.detail.input.closest('.ingredient-item')) {
                            const parentItem = event.detail.input.closest('.ingredient-item');
                            const hiddenIdInput = parentItem.querySelector('input[name="ingredient_id[]"]');
                            if (hiddenIdInput) {
                                hiddenIdInput.value = feedback.id;
                            }
                        }
                    }
                }
            }
        });
        // Add event listener to clear hidden ID if the user types something new
        inputElement.addEventListener('input', function() {
            const parentItem = this.closest('.ingredient-item');
            const hiddenIdInput = parentItem.querySelector('input[name="ingredient_id[]"]');
            if (hiddenIdInput && this.value !== '' && hiddenIdInput.value !== '') { // Only clear if there's a value and it's not the initial loaded value
                // A simple check to see if the input text matches the known selected item
                // This might need more robust logic if user types partial match and then selects
                hiddenIdInput.value = '';
            }
        });
    }

    // Add ingredient button listener
    addIngredientButton.addEventListener('click', () => addIngredientRow());

    // Setup existing remove buttons and autocomplete inputs on page load
    ingredientsContainer.querySelectorAll('.remove-ingredient').forEach(setupRemoveButton);
    ingredientsContainer.querySelectorAll('.ingredient-name-input').forEach(setupAutocomplete);

    // Initial check for at least one ingredient row
    if (ingredientsContainer.children.length === 0) {
        addIngredientRow(); // Add an empty row if none exist (e.g., for new recipe)
    }
});
</script>