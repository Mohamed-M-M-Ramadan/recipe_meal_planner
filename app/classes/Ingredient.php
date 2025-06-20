<?php

// Ensure the database connection function is available
require_once __DIR__ . '/../config/database.php';

class Ingredient
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = getDbConnection();
    }

    /**
     * Finds an ingredient by its ID.
     *
     * @param int $ingredientId The ID of the ingredient.
     * @return array|false An associative array of ingredient data if found, false otherwise.
     */
    public function findById($ingredientId)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT i.*, c.category_name 
                 FROM Ingredients i 
                 LEFT JOIN Categories c ON i.category_id = c.category_id 
                 WHERE i.ingredient_id = :ingredient_id"
            );
            $stmt->execute(['ingredient_id' => $ingredientId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error finding ingredient by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds an ingredient by its name.
     *
     * @param string $ingredientName The name of the ingredient.
     * @return array|false An associative array of ingredient data if found, false otherwise.
     */
    public function findByName($ingredientName)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Ingredients WHERE ingredient_name = :ingredient_name");
            $stmt->execute(['ingredient_name' => $ingredientName]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error finding ingredient by name: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves all ingredients from the database, optionally filtered by category.
     *
     * @param int|null $categoryId Optional: Filter by category ID.
     * @return array|false An array of all ingredient data if found, false otherwise.
     */
    public function getAllIngredients($categoryId = null)
    {
        try {
            $sql = "SELECT i.*, c.category_name 
                    FROM Ingredients i 
                    LEFT JOIN Categories c ON i.category_id = c.category_id";
            $params = [];

            if ($categoryId !== null) {
                $sql .= " WHERE i.category_id = :category_id";
                $params['category_id'] = $categoryId;
            }

            $sql .= " ORDER BY i.ingredient_name ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting all ingredients: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Creates a new ingredient. (Admin/user suggestion functionality)
     *
     * @param string $ingredientName The name of the new ingredient.
     * @param int|null $categoryId Optional: The category ID this ingredient belongs to.
     * @return array An associative array with 'success' (boolean) and 'message' (string), and 'ingredient_id'.
     */
    public function createIngredient($ingredientName, $categoryId = null)
    {
        if (empty($ingredientName)) {
            return ['success' => false, 'message' => 'Ingredient name cannot be empty.'];
        }

        try {
            // Check if ingredient already exists
            if ($this->findByName($ingredientName)) {
                return ['success' => false, 'message' => 'Ingredient with this name already exists.'];
            }

            $stmt = $this->pdo->prepare("INSERT INTO Ingredients (ingredient_name, category_id) VALUES (:ingredient_name, :category_id)");
            $success = $stmt->execute([
                'ingredient_name' => $ingredientName,
                'category_id' => $categoryId
            ]);

            return [
                'success' => $success,
                'message' => $success ? 'Ingredient created successfully.' : 'Failed to create ingredient.',
                'ingredient_id' => $success ? $this->pdo->lastInsertId() : null
            ];

        } catch (PDOException $e) {
            error_log("Error creating ingredient: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    /**
     * Updates an existing ingredient. (Admin functionality)
     *
     * @param int $ingredientId The ID of the ingredient to update.
     * @param string $newIngredientName The new name for the ingredient.
     * @param int|null $newCategoryId The new category ID for the ingredient.
     * @return array An associative array with 'success' (boolean) and 'message' (string).
     */
    public function updateIngredient($ingredientId, $newIngredientName, $newCategoryId = null)
    {
        if (empty($newIngredientName)) {
            return ['success' => false, 'message' => 'Ingredient name cannot be empty.'];
        }

        try {
            // Check for duplicate name excluding current ingredient
            $stmt = $this->pdo->prepare("SELECT ingredient_id FROM Ingredients WHERE ingredient_name = :ingredient_name AND ingredient_id != :ingredient_id");
            $stmt->execute(['ingredient_name' => $newIngredientName, 'ingredient_id' => $ingredientId]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Another ingredient with this name already exists.'];
            }

            $stmt = $this->pdo->prepare("UPDATE Ingredients SET ingredient_name = :ingredient_name, category_id = :category_id WHERE ingredient_id = :ingredient_id");
            $success = $stmt->execute([
                'ingredient_name' => $newIngredientName,
                'category_id' => $newCategoryId,
                'ingredient_id' => $ingredientId
            ]);

            return ['success' => $success, 'message' => $success ? 'Ingredient updated successfully.' : 'Failed to update ingredient.'];

        } catch (PDOException $e) {
            error_log("Error updating ingredient: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    /**
     * Deletes an ingredient by its ID. (Admin functionality)
     * Note: Deleting an ingredient will also delete its entries in Recipe_Ingredients due to ON DELETE CASCADE.
     *
     * @param int $ingredientId The ID of the ingredient to delete.
     * @return array An associative array with 'success' (boolean) and 'message' (string).
     */
    public function deleteIngredient($ingredientId)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM Ingredients WHERE ingredient_id = :ingredient_id");
            $success = $stmt->execute(['ingredient_id' => $ingredientId]);

            return ['success' => $success, 'message' => $success ? 'Ingredient deleted successfully.' : 'Failed to delete ingredient.'];

        } catch (PDOException $e) {
            error_log("Error deleting ingredient: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    /**
     * Autocomplete search for ingredients.
     *
     * @param string $query The search query.
     * @return array An array of matching ingredient names.
     */
    public function searchIngredients($query)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT ingredient_id, ingredient_name FROM Ingredients WHERE ingredient_name LIKE :query ORDER BY ingredient_name ASC LIMIT 10");
            $stmt->execute(['query' => '%' . $query . '%']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error searching ingredients: " . $e->getMessage());
            return [];
        }
    }

        /**
     * Finds an ingredient by its name.
     *
     * @param string $name The name of the ingredient to search for.
     * @return array|null Returns associative array with ingredient data if found, or null if not found.
     */
    public function findIngredientByName($name) {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM ingredients WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
        return $ingredient ? $ingredient : null;
    }
}