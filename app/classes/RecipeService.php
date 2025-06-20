<?php
// Ensure these paths are correct relative to RecipeService.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Recipe.php';
require_once __DIR__ . '/Ingredient.php';

class RecipeService {
    private $db;
    private $recipe;
    private $ingredient;
    private $pdo;

    public function __construct() {
        // Establishes the database connection
        $this->pdo = getDbConnection();
        // Instantiates the Recipe and Ingredient models for interaction
        $this->recipe = new Recipe();
        $this->ingredient = new Ingredient();
    }

    /**
     * Saves a new recipe along with its ingredients in a single transaction.
     * This method orchestrates calls to the Recipe and Ingredient classes.
     *
     * @param int $userId The ID of the user creating the recipe.
     * @param array $recipeDetails Associative array containing recipe data
     * (e.g., 'title', 'description', 'instructions', 'prep_time', 'cook_time', 'servings', 'image_path', 'category_id').
     * @param array $ingredientsData Array of associative arrays, each representing an ingredient:
     * (e.g., ['id' => null/int, 'name' => string, 'quantity' => string, 'unit' => string]).
     * @param string $status The visibility status of the recipe ('private', 'public_pending').
     * @return array Result array with 'success' (boolean) and 'message' (string),
     * and optionally 'recipe_id' if successful.
     */
    public function saveRecipeWithIngredients($userId, $recipeDetails, $ingredientsData, $status) {
        // Start a database transaction to ensure atomicity
        // If any step fails, everything is rolled back.
        $this->db->begin_transaction();
        try {
            // 1. Create the main recipe entry in the 'recipes' table
            // This calls a method in the Recipe class: `createRecipe`
            $recipeResult = $this->recipe->createRecipe(
                $userId,
                $recipeDetails['title'],
                $recipeDetails['description'],
                $recipeDetails['instructions'],
                $recipeDetails['prep_time'],
                $recipeDetails['cook_time'],
                $recipeDetails['servings'],
                $status,
                $recipeDetails['image_path'] ?? null, // Use null if image_path is not set
                $recipeDetails['category_id'] ?? null // Use null if category_id is not set
            );

            if (!$recipeResult['success']) {
                $this->db->rollback(); // Rollback if recipe creation failed
                return ['success' => false, 'message' => 'Failed to create recipe: ' . ($recipeResult['message'] ?? 'Unknown error')];
            }
            $recipeId = $recipeResult['recipe_id'];

            // 2. Process and link ingredients to the newly created recipe
            foreach ($ingredientsData as $ingData) {
                $ingredientId = $ingData['id']; // May be null for new ingredients
                $ingredientName = trim($ingData['name']);
                $quantity = trim($ingData['quantity']);
                $unit = trim($ingData['unit']);

                // Skip empty ingredient names
                if (empty($ingredientName)) {
                    continue;
                }

                // If ingredientId is not provided (i.e., it's a new or unselected ingredient),
                // try to find it by name or create a new one.
                if (is_null($ingredientId) || $ingredientId === 0) { // Check for 0 as well, if frontend sends it
                    // This calls a method in the Ingredient class: `findIngredientByName`
                    $existingIngredient = $this->ingredient->findIngredientByName($ingredientName);
                    if ($existingIngredient) {
                        $ingredientId = $existingIngredient['ingredient_id'];
                    } else {
                        // This calls a method in the Ingredient class: `createIngredient`
                        $newIngredientResult = $this->ingredient->createIngredient($ingredientName);
                        if (!$newIngredientResult['success']) {
                            $this->db->rollback(); // Rollback if new ingredient creation failed
                            return ['success' => false, 'message' => 'Failed to create ingredient "' . htmlspecialchars($ingredientName) . '": ' . ($newIngredientResult['message'] ?? 'Unknown error')];
                        }
                        $ingredientId = $newIngredientResult['ingredient_id'];
                    }
                }
                
                // Link the ingredient to the recipe in the 'recipe_ingredients' junction table
                // This calls a method in the Recipe class: `addIngredientToRecipe`
                $addLinkResult = $this->recipe->addIngredientToRecipe($recipeId, $ingredientId, $quantity, $unit);
                if (!$addLinkResult['success']) {
                    $this->db->rollback(); // Rollback if linking failed
                    return ['success' => false, 'message' => 'Failed to link ingredient "' . htmlspecialchars($ingredientName) . '" to recipe: ' . ($addLinkResult['message'] ?? 'Unknown error')];
                }
            }

            $this->db->commit(); // Commit the transaction if all operations were successful
            return ['success' => true, 'recipe_id' => $recipeId, 'message' => 'Recipe created successfully!'];

        } catch (Exception $e) {
            $this->db->rollback(); // Ensure rollback on any unexpected exception
            error_log("RecipeService saveRecipeWithIngredients Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected error occurred during recipe creation. Please try again.'];
        }
    }

    /**
     * Updates an existing recipe along with its ingredients in a single transaction.
     * This method orchestrates calls to the Recipe and Ingredient classes.
     *
     * @param int $recipeId The ID of the recipe to update.
     * @param array $recipeDetails Associative array containing updated recipe data.
     * @param array $ingredientsData Array of associative arrays, each representing an ingredient.
     * @param string $status The new visibility status of the recipe.
     * @return array Result array with 'success' (boolean) and 'message' (string).
     */
    public function updateRecipeWithIngredients($recipeId, $recipeDetails, $ingredientsData, $status) {
        // Start a database transaction
        $this->db->begin_transaction();
        try {
            // 1. Update the main recipe entry in the 'recipes' table
            // This calls a method in the Recipe class: `updateRecipe`
            $updateRecipeResult = $this->recipe->updateRecipe(
                $recipeId,
                $recipeDetails['title'],
                $recipeDetails['description'],
                $recipeDetails['instructions'],
                $recipeDetails['prep_time'],
                $recipeDetails['cook_time'],
                $recipeDetails['servings'],
                $status,
                $recipeDetails['image_path'] ?? null,
                $recipeDetails['category_id'] ?? null
            );

            if (!$updateRecipeResult['success']) {
                $this->db->rollback(); // Rollback if recipe update failed
                return ['success' => false, 'message' => 'Failed to update recipe: ' . ($updateRecipeResult['message'] ?? 'Unknown error')];
            }

            // 2. Remove all existing ingredient links for this recipe from 'recipe_ingredients' table.
            // This simplifies updates: remove all old, then re-add all current.
            // This calls a method in the Recipe class: `removeAllIngredientsFromRecipe`
            $removeOldIngredientsResult = $this->recipe->removeAllIngredientsFromRecipe($recipeId);
            if (!$removeOldIngredientsResult['success']) {
                $this->db->rollback(); // Rollback if clearing old ingredients failed
                return ['success' => false, 'message' => 'Failed to clear old ingredients: ' . ($removeOldIngredientsResult['message'] ?? 'Unknown error')];
            }

            // 3. Add the new/updated ingredient links to the 'recipe_ingredients' table
            foreach ($ingredientsData as $ingData) {
                $ingredientId = $ingData['id'];
                $ingredientName = trim($ingData['name']);
                $quantity = trim($ingData['quantity']);
                $unit = trim($ingData['unit']);

                // Skip empty ingredient names
                if (empty($ingredientName)) {
                    continue;
                }

                if (is_null($ingredientId) || $ingredientId === 0) {
                    // This calls a method in the Ingredient class: `findIngredientByName`
                    $existingIngredient = $this->ingredient->findIngredientByName($ingredientName);
                    if ($existingIngredient) {
                        $ingredientId = $existingIngredient['ingredient_id'];
                    } else {
                        // This calls a method in the Ingredient class: `createIngredient`
                        $newIngredientResult = $this->ingredient->createIngredient($ingredientName);
                        if (!$newIngredientResult['success']) {
                            $this->db->rollback(); // Rollback if new ingredient creation failed
                            return ['success' => false, 'message' => 'Failed to create new ingredient "' . htmlspecialchars($ingredientName) . '": ' . ($newIngredientResult['message'] ?? 'Unknown error')];
                        }
                        $ingredientId = $newIngredientResult['ingredient_id'];
                    }
                }
                
                // Link the ingredient to the recipe
                // This calls a method in the Recipe class: `addIngredientToRecipe`
                $addLinkResult = $this->recipe->addIngredientToRecipe($recipeId, $ingredientId, $quantity, $unit);
                if (!$addLinkResult['success']) {
                    $this->db->rollback(); // Rollback if linking failed
                    return ['success' => false, 'message' => 'Failed to link ingredient "' . htmlspecialchars($ingredientName) . '" to recipe: ' . ($addLinkResult['message'] ?? 'Unknown error')];
                }
            }

            $this->db->commit(); // Commit the transaction
            return ['success' => true, 'message' => 'Recipe updated successfully!'];

        } catch (Exception $e) {
            $this->db->rollback(); // Ensure rollback on any unexpected exception
            error_log("RecipeService updateRecipeWithIngredients Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected error occurred during recipe update. Please try again.'];
        }
    }

    /**
     * Retrieves all ingredients associated with a specific recipe.
     *
     * @param int $recipeId The ID of the recipe.
     * @return array An array of ingredient data, or an empty array if none found or on error.
     * Each element in the array is an associative array (e.g., 'ingredient_id', 'ingredient_name', 'quantity', 'unit').
     */
    public function getRecipeIngredients($recipeId) {
        // This calls a method in the Recipe class: `getIngredientsForRecipe`
        return $this->recipe->getIngredientsForRecipe($recipeId);
    }
}