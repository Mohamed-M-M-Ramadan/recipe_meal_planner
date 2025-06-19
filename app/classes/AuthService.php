<?php

// Ensure the database connection function is available
require_once __DIR__ . '/../config/database.php';

class AuthService
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = getDbConnection();
    }

    /**
     * Registers a new user.
     *
     * @param string $username The desired username.
     * @param string $password The plain text password.
     * @return array An associative array with 'success' (boolean) and 'message' (string).
     */
    public function register($username, $password)
    {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password cannot be empty.'];
        }

        // Validate username (e.g., length, characters)
        if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return ['success' => false, 'message' => 'Username must be at least 3 characters long and can only contain letters, numbers, and underscores.'];
        }

        try {
            // Check if username already exists
            $stmt = $this->pdo->prepare("SELECT user_id FROM Users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username already taken.'];
            }

            // Hash the password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Default role is 'user'
            $defaultRole = 'user';

            // Insert new user into the database
            $stmt = $this->pdo->prepare("INSERT INTO Users (username, password, role) VALUES (:username, :password, :role)");
            $success = $stmt->execute([
                'username' => $username,
                'password' => $hashedPassword,
                'role' => $defaultRole
            ]);

            return [
                'success' => $success,
                'message' => $success ? 'Registration successful. You can now log in.' : 'Registration failed.'
            ];

        } catch (PDOException $e) {
            // Log the error for debugging (e.g., to a file)
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during registration. Please try again later.'];
        }
    }

    /**
     * Logs a user in.
     *
     * @param string $username The username.
     * @param string $password The plain text password.
     * @return array An associative array with 'success' (boolean) and 'message' (string).
     */
    public function login($username, $password)
    {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Please enter both username and password.'];
        }

        try {
            $stmt = $this->pdo->prepare("SELECT user_id, username, password, role FROM Users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Password is correct, set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];

                return ['success' => true, 'message' => 'Login successful!'];
            } else {
                return ['success' => false, 'message' => 'Invalid username or password.'];
            }

        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during login. Please try again later.'];
        }
    }

    /**
     * Logs the current user out.
     */
    public function logout()
    {
        // Unset all session variables
        $_SESSION = [];

        // Destroy the session
        session_destroy();

        // Redirect to login page or home page
        header("Location: /recipe_meal_planner/public/login.php");
        exit();
    }

    /**
     * Checks if a user is currently logged in.
     *
     * @return bool True if logged in, false otherwise.
     */
    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Checks if the logged-in user has an 'admin' role.
     *
     * @return bool True if the user is an admin, false otherwise.
     */
    public function isAdmin()
    {
        return $this->isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }

    /**
     * Redirects to the login page if the user is not logged in.
     * Optionally sets a message for the user.
     *
     * @param string $redirect_path The path to redirect to if not logged in.
     */
    public function redirectIfNotLoggedIn($redirect_path = '/recipe_meal_planner/public/login.php')
    {
        if (!$this->isLoggedIn()) {
            $_SESSION['message'] = 'You must be logged in to access this page.';
            $_SESSION['message_type'] = 'error';
            header("Location: " . $redirect_path);
            exit();
        }
    }

    /**
     * Redirects to the home page (or specified path) if the user is not an admin.
     * Sets a message for the user.
     *
     * @param string $redirect_path The path to redirect to if not an admin.
     */
    public function redirectIfNotAdmin($redirect_path = '/recipe_meal_planner/public/index.php')
    {
        if (!$this->isAdmin()) {
            $_SESSION['message'] = 'Access Denied: You must be an administrator to view this page.';
            $_SESSION['message_type'] = 'error';
            header("Location: " . $redirect_path);
            exit();
        }
    }
}