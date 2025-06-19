<?php

// Ensure the database connection function is available
require_once __DIR__ . '/../config/database.php';

class Recipe
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = getDbConnection();
    }

    /**
     * Finds a recipe by its ID.
     *
     * @param int $recipeId The ID of the recipe.
     * @return array|false An associative array of recipe data if found, false otherwise.
     */
    public function findById($recipeId)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Recipes WHERE recipe_id = :recipe_id");
            $stmt->execute(['recipe_id' => $recipeId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error finding recipe by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves recipes based on various filters.
     * This method is designed to be flexible for fetching recipes for different views (public, user's own, admin).
     *
     * @param string|array|null $statusFilter Optional. A single status string ('public_approved', 'private', etc.),
     * an array of status strings, or null for any status (admin only).
     * @param int|null $userId Optional. Filter by user_id. Null to not filter by user.
     * @param string|null $searchQuery Optional. A search term for recipe titles or descriptions.
     * @param string $orderBy Optional. Column to order by (e.g., 'creation_date', 'title').
     * @param string $orderDir Optional. Order direction ('ASC' or 'DESC').
     * @param int|null $recipeId Optional. Filter by a specific recipe_id.
     * @return array|false An array of recipe data, or false on error.
     */
    public function getRecipes(
        $statusFilter = null,
        $userId = null,
        $searchQuery = null,
        $orderBy = 'creation_date',
        $orderDir = 'DESC',
        $recipeId = null
    ) {
        $sql = "SELECT r.*, u.username, c.category_name
                FROM Recipes r
                JOIN Users u ON r.user_id = u.user_id
                LEFT JOIN Categories c ON r.category_id = c.category_id";
        $conditions = [];
        $params = [];

        if ($recipeId !== null) {
            $conditions[] = "r.recipe_id = :recipe_id";
            $params[':recipe_id'] = $recipeId;
        }

        if ($statusFilter !== null) {
            if (is_array($statusFilter)) {
                // Handle array of statuses using IN clause
                $placeholders = [];
                foreach ($statusFilter as $index => $status) {
                    $placeholder = ":status_" . $index;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $status;
                }
                $conditions[] = "r.status IN (" . implode(', ', $placeholders) . ")";
            } else {
                // Handle single status string
                $conditions[] = "r.status = :status_filter";
                $params[':status_filter'] = $statusFilter;
            }
        }

        if ($userId !== null) {
            $conditions[] = "r.user_id = :user_id";
            $params[':user_id'] = $userId;
        }

        if ($searchQuery !== null) {
            $conditions[] = "(r.title LIKE :search_query OR r.description LIKE :search_query)";
            $params[':search_query'] = '%' . $searchQuery . '%';
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        // Basic validation for orderBy and orderDir
        $allowedOrderBy = ['creation_date', 'title', 'prep_time', 'cook_time', 'servings'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'creation_date'; // Default to safe value
        }
        $orderDir = (strtoupper($orderDir) === 'ASC') ? 'ASC' : 'DESC';

        $sql .= " ORDER BY " . $orderBy . " " . $orderDir;

        // If fetching a single recipe by ID, ensure LIMIT 1
        if ($recipeId !== null) {
            $sql .= " LIMIT 1";
        }


        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching recipes: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Creates a new recipe and its associated ingredients.
     *
     * @param int $userId The ID of the user creating the recipe.
     * @param string $title The recipe title.
     * @param string $description The recipe description.
     * @param string $instructions The recipe instructions.
     * @param int $prepTime Preparation time in minutes.
     * @param int $cookTime Cook time in minutes.
     * @param int $servings Number of servings.
     * @param string $status The visibility status ('private', 'public_pending').
     * @param string|null $imagePath Path to the recipe image.
     * @param int|null $categoryId The ID of the category.
     * @param array $ingredients An array of ingredient data [{quantity, unit, name}, ...].
     * @return array An associative array with 'success' (boolean) and 'message' (string), and 'recipe_id'.
     */
    public function createRecipe(
        $userId,
        $title,
        $description,
        $instructions,
        $prepTime,
        $cookTime,
        $servings,
        $status,
        $imagePath,
        $categoryId,
        $ingredients // This is an array of ingredient details
    ) {
        if (empty($title) || empty($instructions) || empty($prepTime) || empty($cookTime) || empty($servings) || empty($status) || empty($ingredients)) {
            return ['success' => false, 'message' => 'Please fill in all required recipe fields and add at least one ingredient.'];
        }

        try {
            $this->pdo->beginTransaction();

            // 1. Insert into Recipes table
            $stmt = $this->pdo->prepare("INSERT INTO Recipes (user_id, category_id, title, description, instructions, prep_time, cook_time, servings, image_path, status, creation_date) VALUES (:user_id, :category_id, :title, :description, :instructions, :prep_time, :cook_time, :servings, :image_path, :status, NOW())");
            $recipeSuccess = $stmt->execute([
                'user_id' => $userId,
                'category_id' => $categoryId,
                'title' => $title,
                'description' => $description,
                'instructions' => $instructions,
                'prep_time' => $prepTime,
                'cook_time' => $cookTime,
                'servings' => $servings,
                'image_path' => $imagePath,
                'status' => $status
            ]);

            if (!$recipeSuccess) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Failed to create recipe.'];
            }

            $recipeId = $this->pdo->lastInsertId();

            // 2. Insert into RecipeIngredients table
            $ingredientStmt = $this->pdo->prepare("INSERT INTO RecipeIngredients (recipe_id, ingredient_id, quantity, unit, custom_ingredient_name) VALUES (:recipe_id, :ingredient_id, :quantity, :unit, :custom_ingredient_name)");

            foreach ($ingredients as $ing) {
                $ingredientId = $ing['ingredient_id'] ?? null;
                $ingredientName = $ing['ingredient_name'] ?? ''; // This is the user-typed name
                $quantity = $ing['quantity'] ?? '';
                $unit = $ing['unit'] ?? '';

                // If ingredient_id is not provided (meaning it's a new ingredient),
                // check if it exists in Ingredients table or create it.
                if (empty($ingredientId) && !empty($ingredientName)) {
                    $existingIngredient = $this->getIngredientIdByName($ingredientName);
                    if ($existingIngredient) {
                        $ingredientId = $existingIngredient['ingredient_id'];
                    } else {
                        // Create new ingredient if it doesn't exist
                        $newIngredientStmt = $this->pdo->prepare("INSERT INTO Ingredients (ingredient_name) VALUES (:ingredient_name)");
                        $newIngredientStmt->execute(['ingredient_name' => $ingredientName]);
                        $ingredientId = $this->pdo->lastInsertId();
                    }
                }

                if (empty($ingredientId) && empty($ingredientName)) {
                     $this->pdo->rollBack();
                     return ['success' => false, 'message' => 'An ingredient name is missing.'];
                }

                // If ingredientId is still null, but ingredientName is present, we use custom_ingredient_name
                // Otherwise, ingredientId implies we use the linked ingredient_name from Ingredients table
                $customIngredientName = null;
                if (empty($ingredientId)) {
                    $customIngredientName = $ingredientName; // Use user-typed name as custom
                }


                $ingSuccess = $ingredientStmt->execute([
                    'recipe_id' => $recipeId,
                    'ingredient_id' => $ingredientId, // Will be null if using custom_ingredient_name
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'custom_ingredient_name' => $customIngredientName // Store custom name here
                ]);

                if (!$ingSuccess) {
                    $this->pdo->rollBack();
                    return ['success' => false, 'message' => 'Failed to add ingredient: ' . $ingredientName];
                }
            }

            $this->pdo->commit();
            return [
                'success' => true,
                'message' => 'Recipe created successfully!',
                'recipe_id' => $recipeId
            ];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error creating recipe: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create recipe: ' . $e->getMessage()];
        }
    }


    /**
     * Updates an existing recipe and its associated ingredients.
     *
     * @param int $recipeId The ID of the recipe to update.
     * @param int $userId The ID of the user updating the recipe (for ownership check).
     * @param string $title The recipe title.
     * @param string $description The recipe description.
     * @param string $instructions The recipe instructions.
     * @param int $prepTime Preparation time in minutes.
     * @param int $cookTime Cook time in minutes.
     * @param int $servings Number of servings.
     * @param string $status The visibility status ('private', 'public_pending').
     * @param string|null $imagePath Path to the recipe image.
     * @param int|null $categoryId The ID of the category.
     * @param array $ingredients An array of ingredient data [{quantity, unit, name}, ...].
     * @return array An associative array with 'success' (boolean) and 'message' (string).
     */
    public function updateRecipe(
        $recipeId,
        $userId,
        $title,
        $description,
        $instructions,
        $prepTime,
        $cookTime,
        $servings,
        $status,
        $imagePath,
        $categoryId,
        $ingredients
    ) {
        if (empty($title) || empty($instructions) || empty($prepTime) || empty($cookTime) || empty($servings) || empty($status) || empty($ingredients)) {
            return ['success' => false, 'message' => 'Please fill in all required recipe fields and add at least one ingredient.'];
        }

        try {
            $this->pdo->beginTransaction();

            // 1. Update Recipes table
            // Only allow updating status to 'public_pending' if it's currently 'private'
            // Or if it's 'public_approved', 'public_pending', 'public_rejected' keep it as is unless admin changes it.
            // For now, allow direct update of status. Admin will handle final approval.
            $stmt = $this->pdo->prepare("UPDATE Recipes SET
                category_id = :category_id,
                title = :title,
                description = :description,
                instructions = :instructions,
                prep_time = :prep_time,
                cook_time = :cook_time,
                servings = :servings,
                image_path = :image_path,
                status = :status,
                last_updated = NOW()
                WHERE recipe_id = :recipe_id AND user_id = :user_id"); // Ensure user can only update their own recipes

            $recipeSuccess = $stmt->execute([
                'category_id' => $categoryId,
                'title' => $title,
                'description' => $description,
                'instructions' => $instructions,
                'prep_time' => $prepTime,
                'cook_time' => $cookTime,
                'servings' => $servings,
                'image_path' => $imagePath,
                'status' => $status,
                'recipe_id' => $recipeId,
                'user_id' => $userId
            ]);

            if ($stmt->rowCount() === 0) {
                 // This means either the recipe_id didn't exist or it didn't belong to the user_id
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Recipe not found or you do not have permission to edit it.'];
            }

            // 2. Update RecipeIngredients: Delete existing and re-insert new ones
            $deleteStmt = $this->pdo->prepare("DELETE FROM RecipeIngredients WHERE recipe_id = :recipe_id");
            $deleteStmt->execute(['recipe_id' => $recipeId]);

            $ingredientStmt = $this->pdo->prepare("INSERT INTO RecipeIngredients (recipe_id, ingredient_id, quantity, unit, custom_ingredient_name) VALUES (:recipe_id, :ingredient_id, :quantity, :unit, :custom_ingredient_name)");

            foreach ($ingredients as $ing) {
                $ingredientId = $ing['ingredient_id'] ?? null;
                $ingredientName = $ing['ingredient_name'] ?? '';
                $quantity = $ing['quantity'] ?? '';
                $unit = $ing['unit'] ?? '';

                 if (empty($ingredientId) && !empty($ingredientName)) {
                    $existingIngredient = $this->getIngredientIdByName($ingredientName);
                    if ($existingIngredient) {
                        $ingredientId = $existingIngredient['ingredient_id'];
                    } else {
                        // Create new ingredient if it doesn't exist
                        $newIngredientStmt = $this->pdo->prepare("INSERT INTO Ingredients (ingredient_name) VALUES (:ingredient_name)");
                        $newIngredientStmt->execute(['ingredient_name' => $ingredientName]);
                        $ingredientId = $this->pdo->lastInsertId();
                    }
                }

                if (empty($ingredientId) && empty($ingredientName)) {
                     $this->pdo->rollBack();
                     return ['success' => false, 'message' => 'An ingredient name is missing.'];
                }

                $customIngredientName = null;
                if (empty($ingredientId)) {
                    $customIngredientName = $ingredientName;
                }

                $ingSuccess = $ingredientStmt->execute([
                    'recipe_id' => $recipeId,
                    'ingredient_id' => $ingredientId,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'custom_ingredient_name' => $customIngredientName
                ]);

                if (!$ingSuccess) {
                    $this->pdo->rollBack();
                    return ['success' => false, 'message' => 'Failed to update ingredient: ' . $ingredientName];
                }
            }

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Recipe updated successfully!'];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error updating recipe: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update recipe: ' . $e->getMessage()];
        }
    }

    /**
     * Deletes a recipe by its ID. (Admin or owner functionality)
     *
     * @param int $recipeId The ID of the recipe to delete.
     * @param int|null $userId Optional. The ID of the user attempting to delete (for ownership check).
     * @param bool $isAdmin Optional. True if the user is an admin.
     * @return array An associative array with 'success' (boolean) and 'message' (string).
     */
    public function deleteRecipe($recipeId, $userId = null, $isAdmin = false)
    {
        try {
            $this->pdo->beginTransaction();

            // Get recipe details to verify ownership if not admin
            $stmt = $this->pdo->prepare("SELECT user_id FROM Recipes WHERE recipe_id = :recipe_id");
            $stmt->execute(['recipe_id' => $recipeId]);
            $recipe = $stmt->fetch();

            if (!$recipe) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Recipe not found.'];
            }

            // Check if user is owner or admin
            if (!$isAdmin && $recipe['user_id'] != $userId) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'You do not have permission to delete this recipe.'];
            }

            // 1. Delete associated ingredients first (due to foreign key constraint)
            $deleteIngredientsStmt = $this->pdo->prepare("DELETE FROM RecipeIngredients WHERE recipe_id = :recipe_id");
            $deleteIngredientsStmt->execute(['recipe_id' => $recipeId]);

            // 2. Delete the recipe
            $deleteRecipeStmt = $this->pdo->prepare("DELETE FROM Recipes WHERE recipe_id = :recipe_id");
            $success = $deleteRecipeStmt->execute(['recipe_id' => $recipeId]);

            if ($success) {
                $this->pdo->commit();
                return ['success' => true, 'message' => 'Recipe deleted successfully.'];
            } else {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Failed to delete recipe.'];
            }

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error deleting recipe: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }


    /**
     * Approves a pending recipe (Admin functionality).
     *
     * @param int $recipeId The ID of the recipe to approve.
     * @return array An associative array with 'success' (boolean) and 'message' (string).
     */
    public function approveRecipe($recipeId)
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE Recipes SET status = 'public_approved', last_updated = NOW() WHERE recipe_id = :recipe_id AND status = 'public_pending'");
            $success = $stmt->execute(['recipe_id' => $recipeId]);

            return ['success' => $success, 'message' => $success ? 'Recipe approved successfully.' : 'Failed to approve recipe (might not be pending or not found).'];
        } catch (PDOException $e) {
            error_log("Error approving recipe: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    /**
     * Rejects a pending recipe (Admin functionality).
     *
     * @param int $recipeId The ID of the recipe to reject.
     * @return array An associative array with 'success' (boolean) and 'message' (string).
     */
    public function rejectRecipe($recipeId)
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE Recipes SET status = 'public_rejected', last_updated = NOW() WHERE recipe_id = :recipe_id AND status = 'public_pending'");
            $success = $stmt->execute(['recipe_id' => $recipeId]);

            return ['success' => $success, 'message' => $success ? 'Recipe rejected successfully.' : 'Failed to reject recipe (might not be pending or not found).'];
        } catch (PDOException $e) {
            error_log("Error rejecting recipe: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    /**
     * Changes a recipe's status to private (Admin functionality).
     *
     * @param int $recipeId The ID of the recipe to set private.
     * @return array An associative array with 'success' (boolean) and 'message' (string).
     */
    public function makeRecipePrivate($recipeId)
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE Recipes SET status = 'private', last_updated = NOW() WHERE recipe_id = :recipe_id");
            $success = $stmt->execute(['recipe_id' => $recipeId]);

            return ['success' => $success, 'message' => $success ? 'Recipe set to private successfully.' : 'Failed to set recipe to private.'];
        } catch (PDOException $e) {
            error_log("Error making recipe private: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }


    /**
     * Retrieves ingredients associated with a specific recipe.
     *
     * @param int $recipeId The ID of the recipe.
     * @return array An array of ingredient data.
     */
    public function getIngredientsForRecipe($recipeId)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ri.quantity, ri.unit, COALESCE(i.ingredient_name, ri.custom_ingredient_name) AS ingredient_name
                FROM RecipeIngredients ri
                LEFT JOIN Ingredients i ON ri.ingredient_id = i.ingredient_id
                WHERE ri.recipe_id = :recipe_id
            ");
            $stmt->execute(['recipe_id' => $recipeId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting ingredients for recipe: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Helper method to get ingredient ID by name.
     *
     * @param string $ingredientName
     * @return array|false
     */
    private function getIngredientIdByName($ingredientName)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT ingredient_id FROM Ingredients WHERE ingredient_name = :ingredient_name");
            $stmt->execute(['ingredient_name' => $ingredientName]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting ingredient by name: " . $e->getMessage());
            return false;
        }
    }

/**
 * Update the status of a recipe by its ID.
 * @param int $recipeId
 * @param string $newStatus
 * @return bool
 */
public function updateRecipeStatus($recipeId, $newStatus)
{
    // Allowed statuses
    $allowedStatuses = ['private', 'public_pending', 'public_approved'];
    if (!in_array($newStatus, $allowedStatuses, true)) {
        return false;
    }

    $stmt = $this->pdo->prepare("UPDATE Recipes SET status = :status WHERE recipe_id = :id");
    return $stmt->execute([
        ':status' => $newStatus,
        ':id' => $recipeId
    ]);
}

}