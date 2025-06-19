<?php

// Ensure the database connection function is available
require_once __DIR__ . '/../config/database.php';

class Category
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = getDbConnection();
    }

    /**
     * Finds a category by its ID.
     *
     * @param int $categoryId The ID of the category.
     * @return array|false An associative array of category data if found, false otherwise.
     */
    public function findById($categoryId)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Categories WHERE category_id = :category_id");
            $stmt->execute(['category_id' => $categoryId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error finding category by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds a category by its name.
     *
     * @param string $categoryName The name of the category.
     * @return array|false An associative array of category data if found, false otherwise.
     */
    public function findByName($categoryName)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM Categories WHERE category_name = :category_name");
            $stmt->execute(['category_name' => $categoryName]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error finding category by name: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves all categories from the database.
     *
     * @return array|false An array of all category data if found, false otherwise.
     */
    public function getAllCategories()
    {
        try {
            // Changed sorting to category_id ASC
            $stmt = $this->pdo->query("SELECT * FROM Categories ORDER BY category_id ASC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting all categories: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Creates a new category.
     *
     * @param string $categoryName The name of the new category.
     * @return array An associative array with 'success' (boolean) and 'message' (string), and 'category_id'.
     */
    public function createCategory($categoryName)
    {
        if (empty($categoryName)) {
            return ['success' => false, 'message' => 'Category name cannot be empty.'];
        }

        try {
            // Check if category already exists
            if ($this->findByName($categoryName)) {
                return ['success' => false, 'message' => 'Category with this name already exists.'];
            }

            $stmt = $this->pdo->prepare("INSERT INTO Categories (category_name) VALUES (:category_name)");
            $success = $stmt->execute(['category_name' => $categoryName]);

            return [
                'success' => $success,
                'message' => $success ? 'Category created successfully.' : 'Failed to create category.',
                'category_id' => $success ? $this->pdo->lastInsertId() : null
            ];

        } catch (PDOException $e) {
            error_log("Error creating category: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    /**
     * Updates an existing category. (Admin functionality)
     *
     * @param int $categoryId The ID of the category to update.
     * @param string $newCategoryName The new name for the category.
     * @return array An associative array with 'success' (boolean) and 'message' (string).
     */
    public function updateCategory($categoryId, $newCategoryName)
    {
        if (empty($newCategoryName)) {
            return ['success' => false, 'message' => 'Category name cannot be empty.'];
        }

        try {
            // Check for duplicate name excluding current category
            $stmt = $this->pdo->prepare("SELECT category_id FROM Categories WHERE category_name = :category_name AND category_id != :category_id");
            $stmt->execute(['category_name' => $newCategoryName, 'category_id' => $categoryId]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Another category with this name already exists.'];
            }

            $stmt = $this->pdo->prepare("UPDATE Categories SET category_name = :category_name WHERE category_id = :category_id");
            $success = $stmt->execute([
                'category_name' => $newCategoryName,
                'category_id' => $categoryId
            ]);

            return ['success' => $success, 'message' => $success ? 'Category updated successfully.' : 'Failed to update category.'];

        } catch (PDOException $e) {
            error_log("Error updating category: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }

    /**
     * Deletes a category by its ID. (Admin functionality)
     * Note: This will set `category_id` to NULL for any ingredients linked to this category due to ON DELETE SET NULL.
     *
     * @param int $categoryId The ID of the category to delete.
     * @return array An associative array with 'success' (boolean) and 'message' (string).
     */
    public function deleteCategory($categoryId)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM Categories WHERE category_id = :category_id");
            $success = $stmt->execute(['category_id' => $categoryId]);

            return ['success' => $success, 'message' => $success ? 'Category deleted successfully.' : 'Failed to delete category.'];

        } catch (PDOException $e) {
            error_log("Error deleting category: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()];
        }
    }
}