<?php

// Ensure the database connection function is available
require_once __DIR__ . '/../config/database.php';

class User
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = getDbConnection();
    }

    /**
     * Finds a user by their ID.
     *
     * @param int $userId The ID of the user.
     * @return array|false An associative array of user data if found, false otherwise.
     */
    public function findById($userId)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT u.user_id, u.username, u.email, u.dietary_preferences, u.registration_date, ul.level_name 
                 FROM Users u 
                 JOIN User_Levels ul ON u.user_level_id = ul.user_level_id 
                 WHERE u.user_id = :user_id"
            );
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error finding user by ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds a user by their username.
     *
     * @param string $username The username of the user.
     * @return array|false An associative array of user data if found, false otherwise.
     */
    public function findByUsername($username)
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT u.user_id, u.username, u.email, u.dietary_preferences, u.registration_date, ul.level_name 
                 FROM Users u 
                 JOIN User_Levels ul ON u.user_level_id = ul.user_level_id 
                 WHERE u.username = :username"
            );
            $stmt->execute(['username' => $username]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error finding user by username: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates an existing user's information (excluding password).
     *
     * @param int $userId The ID of the user to update.
     * @param string $username The new username.
     * @param string $email The new email address.
     * @param string|null $dietaryPreferences Optional: New dietary preferences.
     * @param int|null $userLevelId Optional: New user level ID (for admin use).
     * @return bool True on success, false on failure.
     */
    public function updateUser($userId, $username, $email, $dietaryPreferences = null, $userLevelId = null)
    {
        try {
            // Check for duplicate username/email excluding the current user
            $stmt = $this->pdo->prepare("SELECT user_id FROM Users WHERE (username = :username OR email = :email) AND user_id != :user_id");
            $stmt->execute(['username' => $username, 'email' => $email, 'user_id' => $userId]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username or email already in use by another user.'];
            }

            $sql = "UPDATE Users SET username = :username, email = :email, dietary_preferences = :dietary_preferences";
            $params = [
                'username' => $username,
                'email' => $email,
                'dietary_preferences' => $dietaryPreferences,
                'user_id' => $userId
            ];

            if ($userLevelId !== null) {
                $sql .= ", user_level_id = :user_level_id";
                $params['user_level_id'] = $userLevelId;
            }

            $sql .= " WHERE user_id = :user_id";

            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute($params);

            return ['success' => $success, 'message' => $success ? 'User updated successfully.' : 'Failed to update user.'];

        } catch (PDOException $e) {
            error_log("Error updating user: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating user: ' . $e->getMessage()];
        }
    }

    /**
     * Updates a user's password.
     *
     * @param int $userId The ID of the user whose password to update.
     * @param string $newPassword The new raw password.
     * @return bool True on success, false on failure.
     */
    public function updatePassword($userId, $newPassword)
    {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("UPDATE Users SET password = :password WHERE user_id = :user_id");
            return $stmt->execute(['password' => $hashedPassword, 'user_id' => $userId]);
        } catch (PDOException $e) {
            error_log("Error updating user password: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a user by their ID. (Admin functionality)
     *
     * @param int $userId The ID of the user to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteUser($userId)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM Users WHERE user_id = :user_id");
            return $stmt->execute(['user_id' => $userId]);
        } catch (PDOException $e) {
            error_log("Error deleting user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves all users from the database. (Admin functionality)
     *
     * @return array|false An array of all user data if found, false otherwise.
     */
    public function getAllUsers()
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT u.user_id, u.username, u.email, u.dietary_preferences, u.registration_date, ul.level_name 
                 FROM Users u 
                 JOIN User_Levels ul ON u.user_level_id = ul.user_level_id 
                 ORDER BY u.registration_date DESC"
            );
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting all users: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves all user levels. (Useful for admin when assigning levels)
     *
     * @return array|false An array of user levels if found, false otherwise.
     */
    public function getAllUserLevels()
    {
        try {
            $stmt = $this->pdo->query("SELECT user_level_id, level_name FROM User_Levels");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting all user levels: " . $e->getMessage());
            return false;
        }
    }
}