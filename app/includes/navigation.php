<nav>
    <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="recipes.php">Recipes</a></li>
        <?php if (isset($_SESSION['user_id'])): // If user is logged in ?>
            <li><a href="meal_plans.php">Meal Plans</a></li>
            <li><a href="profile.php">Profile</a></li>
            <?php if (isset($_SESSION['user_level']) && $_SESSION['user_level'] === 'Admin'): ?>
                <li><a href="admin/index.php">Admin Dashboard</a></li>
            <?php endif; ?>
            <li><a href="login.php?logout=true">Logout</a></li>
        <?php else: // If user is not logged in ?>
            <li><a href="login.php">Login</a></li>
            <li><a href="register.php">Register</a></li>
        <?php endif; ?>
    </ul>
</nav>