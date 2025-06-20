<?php
// Ensure this path is correct relative to Recipe.php
require_once __DIR__ . '/../config/database.php';

class Recipe {
    private $db; // Stores the database connection instance
    private $table = 'recipes'; // Main table for recipes
    private $recipeIngredientsTable = 'recipe_ingredients'; // Junction table for recipe-ingredient links
    private $ingredientsTable = 'ingredients'; // Table for all unique ingredients
    private $usersTable = 'users'; // Table for all users

    public function __construct() {
        // Get the database connection when the Recipe object is created
        $this->db = Database::getInstance();
    }

    /**
     * Finds a recipe by its primary key ID.
     * Used to retrieve all details of a single recipe.
     *
     * @param int $id The recipe_id to search for.
     * @return array|false The recipe's data as an associative array, or false if not found.
     */
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM " . $this->table . " WHERE recipe_id = ?");
        // Check if prepare was successful
        if ($stmt === false) {
            error_log("Recipe::findById Prepare failed: " . $this->db->error);
            return false;
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $recipe = $result->fetch_assoc(); // Fetch the single row as an associative array
        $stmt->close();
        return $recipe;
    }


     /**
     * Retrieves a list of recipes based on various criteria.
     * Corrected to use positional placeholders for mysqli.
     *
     * @param string|null $status The status of recipes to retrieve (e.g., 'public_approved', 'private'). Null for any status.
     * @param int|null $userId Optional: Filter by user ID.
     * @param string|null $searchQuery Optional: Search by title or description.
     * @param string $orderBy Column to order by (e.g., 'creation_date', 'title').
     * @param string $orderDir Order direction ('ASC' or 'DESC').
     * @param int $limit Max number of results.
     * @param int $offset Starting offset for results.
     * @return array An array of recipe data.
     */
    public function getRecipes($status = null, $userId = null, $searchQuery = null, $orderBy = 'creation_date', $orderDir = 'DESC', $limit = 20, $offset = 0) {
        // Base query
        $query = "SELECT r.*, u.username FROM " . $this->table . " r JOIN " . $this->usersTable . " u ON r.user_id = u.user_id WHERE 1=1";
        $params = []; // To store parameters for binding
        $types = ""; // To store types for binding (e.g., 's' for string, 'i' for integer)

        // Add status filter if provided
        if ($status !== null) {
            $query .= " AND r.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        // Add user ID filter if provided
        if ($userId !== null) {
            $query .= " AND r.user_id = ?";
            $params[] = $userId;
            $types .= "i";
        }

        // Add search query filter if provided
        if ($searchQuery !== null) {
            $query .= " AND (r.title LIKE ? OR r.description LIKE ?)";
            $params[] = '%' . $searchQuery . '%';
            $params[] = '%' . $searchQuery . '%';
            $types .= "ss"; // Two string parameters for the two LIKE clauses
        }

        // Add ordering
        // Sanitize orderBy and orderDir to prevent SQL injection
        $allowedOrderBy = ['creation_date', 'title', 'prep_time', 'cook_time', 'servings'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'creation_date'; // Default if invalid
        }
        $orderDir = (strtoupper($orderDir) === 'ASC') ? 'ASC' : 'DESC';

        $query .= " ORDER BY r." . $orderBy . " " . $orderDir;

        // Add LIMIT and OFFSET for pagination
        $query .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->db->prepare($query);

        if ($stmt === false) {
            error_log("Recipe::getRecipes Prepare failed: " . $this->db->error);
            return []; // Return empty array on prepare failure
        }

        // Dynamically bind parameters
        if (!empty($params)) {
            // Use call_user_func_array to bind parameters as bind_param requires
            // parameters to be passed by reference, and $params contains values
            // Create an array of references for bind_param
            $bindArgs = [$types];
            foreach ($params as $key => $value) {
                $bindArgs[] = &$params[$key]; // Pass by reference
            }
            call_user_func_array([$stmt, 'bind_param'], $bindArgs);
        }

        if (!$stmt->execute()) {
            error_log("Recipe::getRecipes Execute failed: " . $stmt->error);
            $stmt->close();
            return []; // Return empty array on execute failure
        }

        $result = $stmt->get_result();
        $recipes = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $recipes;
    }



    /**
     * Creates a new recipe entry in the 'recipes' table.
     * This is the method that `RecipeService::saveRecipeWithIngredients` calls.
     *
     * @param int $userId The ID of the user who owns this recipe.
     * @param string $title The title of the recipe.
     * @param string $description A short description of the recipe.
     * @param string $instructions Detailed cooking instructions.
     * @param int $prepTime Preparation time in minutes.
     * @param int $cookTime Cooking time in minutes.
     * @param int $servings Number of servings the recipe yields.
     * @param string $status The visibility status (e.g., 'private', 'public_pending').
     * @param string|null $imagePath Optional: file path to the recipe's image.
     * @param int|null $categoryId Optional: ID of the recipe's category.
     * @return array Result array with 'success' (boolean) and 'message' (string),
     * and 'recipe_id' if successful.
     */
    public function createRecipe($userId, $title, $description, $instructions, $prepTime, $cookTime, $servings, $status, $imagePath = null, $categoryId = null) {
        $query = "INSERT INTO " . $this->table . " (user_id, title, description, instructions, prep_time, cook_time, servings, status, image_path, category_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);

        if ($stmt === false) {
            return ['success' => false, 'message' => 'Prepare failed: ' . $this->db->error];
        }

        // 'isssiiisss' defines the types of the parameters:
        // i = integer, s = string
        // user_id (i), title (s), description (s), instructions (s),
        // prep_time (i), cook_time (i), servings (i), status (s),
        // image_path (s), category_id (s - bind as string, DB will convert or null)
        $stmt->bind_param("isssiiisss", $userId, $title, $description, $instructions, $prepTime, $cookTime, $servings, $status, $imagePath, $categoryId);

        if ($stmt->execute()) {
            $recipeId = $this->db->insert_id; // Get the ID of the newly inserted recipe
            $stmt->close();
            return ['success' => true, 'recipe_id' => $recipeId, 'message' => 'Recipe created successfully.'];
        } else {
            $error = $stmt->error;
            $stmt->close();
            error_log("Recipe::createRecipe Execution failed: " . $error);
            return ['success' => false, 'message' => 'Execution failed: ' . $error];
        }
    }

    /**
     * Updates an existing recipe entry in the 'recipes' table.
     * This is the method that `RecipeService::updateRecipeWithIngredients` calls.
     *
     * @param int $recipeId The ID of the recipe to update.
     * @param string $title Recipe title.
     * @param string $description Recipe description.
     * @param string $instructions Cooking instructions.
     * @param int $prepTime Preparation time in minutes.
     * @param int $cookTime Cook time in minutes.
     * @param int $servings Number of servings.
     * @param string $status Recipe status ('private', 'public_pending', etc.).
     * @param string|null $imagePath Path to the recipe image.
     * @param int|null $categoryId Optional category ID.
     * @return array Result array with 'success'.
     */
    public function updateRecipe($recipeId, $title, $description, $instructions, $prepTime, $cookTime, $servings, $status, $imagePath = null, $categoryId = null) {
        $query = "UPDATE " . $this->table . " SET title = ?, description = ?, instructions = ?, prep_time = ?, cook_time = ?, servings = ?, status = ?, image_path = ?, category_id = ? WHERE recipe_id = ?";
        $stmt = $this->db->prepare($query);

        if ($stmt === false) {
            return ['success' => false, 'message' => 'Prepare failed: ' . $this->db->error];
        }

        // 'sssiissssi' defines the types of the parameters for update:
        // title (s), description (s), instructions (s), prep_time (i),
        // cook_time (i), servings (i), status (s), image_path (s),
        // category_id (s), recipe_id (i)
        $stmt->bind_param("sssiissssi", $title, $description, $instructions, $prepTime, $cookTime, $servings, $status, $imagePath, $categoryId, $recipeId);

        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'message' => 'Recipe updated successfully.'];
        } else {
            $error = $stmt->error;
            $stmt->close();
            error_log("Recipe::updateRecipe Execution failed: " . $error);
            return ['success' => false, 'message' => 'Execution failed: ' . $error];
        }
    }

    /**
     * Deletes a recipe and all its associated ingredient links.
     * Uses a transaction to ensure both operations succeed or fail together.
     *
     * @param int $id The recipe_id to delete.
     * @return array Result array with 'success' (boolean) and 'message' (string).
     */
    public function deleteRecipe($id) {
        // Start transaction for atomicity
        $this->db->begin_transaction();
        try {
            // First, remove associated ingredients from recipe_ingredients table
            $stmt = $this->db->prepare("DELETE FROM " . $this->recipeIngredientsTable . " WHERE recipe_id = ?");
            if ($stmt === false) {
                throw new Exception('Prepare failed for deleting recipe ingredients: ' . $this->db->error);
            }
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                throw new Exception('Execution failed for deleting recipe ingredients: ' . $stmt->error);
            }
            $stmt->close();

            // Then, delete the recipe itself from the 'recipes' table
            $stmt = $this->db->prepare("DELETE FROM " . $this->table . " WHERE recipe_id = ?");
            if ($stmt === false) {
                throw new Exception('Prepare failed for deleting recipe: ' . $this->db->error);
            }
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $this->db->commit(); // Commit if both deletions are successful
                $stmt->close();
                return ['success' => true, 'message' => 'Recipe and its ingredients deleted successfully.'];
            } else {
                throw new Exception('Execution failed for deleting recipe: ' . $stmt->error);
            }
        } catch (Exception $e) {
            $this->db->rollback(); // Rollback on any error
            error_log("Error deleting recipe: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete recipe: ' . $e->getMessage()];
        }
    }

    /**
     * Adds an ingredient link to a specific recipe in the 'recipe_ingredients' table.
     * This is called by `RecipeService` when saving/updating recipes.
     *
     * @param int $recipeId The ID of the recipe.
     * @param int $ingredientId The ID of the ingredient.
     * @param string $quantity The quantity (e.g., "1 cup", "200g").
     * @param string $unit The unit of measurement (e.g., "cup", "g", "tsp").
     * @return array Result array with 'success' (boolean) and 'message' (string).
     */
    public function addIngredientToRecipe($recipeId, $ingredientId, $quantity, $unit) {
        $query = "INSERT INTO " . $this->recipeIngredientsTable . " (recipe_id, ingredient_id, quantity, unit) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);

        if ($stmt === false) {
            return ['success' => false, 'message' => 'Prepare failed: ' . $this->db->error];
        }

        $stmt->bind_param("iiss", $recipeId, $ingredientId, $quantity, $unit);

        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'message' => 'Ingredient added to recipe.'];
        } else {
            $error = $stmt->error;
            $stmt->close();
            error_log("Recipe::addIngredientToRecipe Execution failed: " . $error);
            return ['success' => false, 'message' => 'Execution failed: ' . $error];
        }
    }

    /**
     * Removes all ingredient links for a given recipe from the 'recipe_ingredients' table.
     * This is used by `RecipeService` before re-adding updated ingredients during an edit.
     *
     * @param int $recipeId The ID of the recipe.
     * @return array Result array with 'success' (boolean) and 'message' (string).
     */
    public function removeAllIngredientsFromRecipe($recipeId) {
        $query = "DELETE FROM " . $this->recipeIngredientsTable . " WHERE recipe_id = ?";
        $stmt = $this->db->prepare($query);

        if ($stmt === false) {
            return ['success' => false, 'message' => 'Prepare failed: ' . $this->db->error];
        }

        $stmt->bind_param("i", $recipeId);

        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'message' => 'All ingredients removed from recipe.'];
        } else {
            $error = $stmt->error;
            $stmt->close();
            error_log("Recipe::removeAllIngredientsFromRecipe Execution failed: " . $error);
            return ['success' => false, 'message' => 'Execution failed: ' . $error];
        }
    }

    /**
     * Retrieves all ingredients and their details for a specific recipe.
     * This is called by `RecipeService::getRecipeIngredients`.
     *
     * @param int $recipeId The ID of the recipe.
     * @return array An array of ingredient data (ingredient_id, name, quantity, unit).
     */
    public function getIngredientsForRecipe($recipeId) {
        $query = "SELECT ri.quantity, ri.unit, i.ingredient_id, i.ingredient_name
                  FROM " . $this->recipeIngredientsTable . " ri
                  JOIN " . $this->ingredientsTable . " i ON ri.ingredient_id = i.ingredient_id
                  WHERE ri.recipe_id = ?";
        $stmt = $this->db->prepare($query);

        if ($stmt === false) {
            error_log('Prepare failed in getIngredientsForRecipe: ' . $this->db->error);
            return []; // Return empty array on error
        }

        $stmt->bind_param("i", $recipeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ingredients = $result->fetch_all(MYSQLI_ASSOC); // Fetch all rows as associative arrays
        $stmt->close();
        return $ingredients;
    }

    /**
     * Update the status of a recipe by its ID.
     *
     * @param int $recipeId
     * @param string $newStatus
     * @return bool
     */
    public function updateRecipeStatus($recipeId, $newStatus)
    {
        // Define allowed statuses
        $allowedStatuses = ['private', 'public_pending', 'public_approved'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            return false;
        }

        // Assuming you have a PDO connection as $this->db
        $stmt = $this->db->prepare("UPDATE recipes SET status = :status WHERE recipe_id = :id");
        return $stmt->execute([
            ':status' => $newStatus,
            ':id' => $recipeId
        ]);
    }
}