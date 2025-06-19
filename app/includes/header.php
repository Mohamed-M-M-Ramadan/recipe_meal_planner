<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipe & Meal Planner</title>
    <link rel="stylesheet" href="/recipe_meal_planner/public/css/style.css">
    <link rel="stylesheet" href="/recipe_meal_planner/public/css/autoComplete.min.css"> </head>
<body>
    <header>
        <div class="container">
            <h1><a href="/recipe_meal_planner/public/index.php">Recipe & Meal Planner</a></h1>
            <nav>
                <ul>
                    <li><a href="/recipe_meal_planner/public/index.php">Home</a></li>
                    <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true): ?>
                        <li><a href="/recipe_meal_planner/public/my_recipes.php">My Recipes</a></li> <li><a href="/recipe_meal_planner/public/recipe_form.php">Create Recipe</a></li> <li><a href="/recipe_meal_planner/public/meal_plan_form.php">Create Meal Plan</a></li> <li><a href="/recipe_meal_planner/public/profile.php">Profile</a></li>
                        <?php if (isset($_SESSION['user_level']) && $_SESSION['user_level'] === 'Admin'): ?>
                            <li><a href="/recipe_meal_planner/public/admin/index.php">Admin</a></li>
                        <?php endif; ?>
                        <li><a href="/recipe_meal_planner/public/login.php?logout=true">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                    <?php else: ?>
                        <li><a href="/recipe_meal_planner/public/login.php">Login</a></li>
                        <li><a href="/recipe_meal_planner/public/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    ```
