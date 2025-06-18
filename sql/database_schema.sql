-- Disable foreign key checks temporarily (useful if you're dropping and recreating tables)
SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables if they exist (for easy re-running the script during development)
-- ORDER MATTERS FOR DROPPING TOO! Drop tables that REFERENCE others first.
DROP TABLE IF EXISTS Meal_Plan_Items;
DROP TABLE IF EXISTS Meal_Plans;
DROP TABLE IF EXISTS Recipe_Ingredients;
DROP TABLE IF EXISTS Recipes;
DROP TABLE IF EXISTS Ingredients;
DROP TABLE IF EXISTS Users;
DROP TABLE IF EXISTS Dietary_Tags; -- If you are using this
DROP TABLE IF EXISTS Categories;
DROP TABLE IF EXISTS User_Levels;


-- 1. User_Levels Table (No foreign keys, can be created early)
CREATE TABLE User_Levels (
    user_level_id INT AUTO_INCREMENT PRIMARY KEY,
    level_name VARCHAR(50) NOT NULL UNIQUE -- e.g., 'Admin', 'Registered User'
);

-- 2. Categories Table (No foreign keys, can be created early)
CREATE TABLE Categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
);

-- 3. Dietary_Tags (Optional, if you decide to use it. No foreign keys.)
-- If you don't plan to use this, you can remove it.
CREATE TABLE Dietary_Tags (
    tag_id INT AUTO_INCREMENT PRIMARY KEY,
    tag_name VARCHAR(100) NOT NULL UNIQUE
);

-- 4. Users Table (References User_Levels)
CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Hashed password
    email VARCHAR(100) NOT NULL UNIQUE,
    user_level_id INT NOT NULL,
    dietary_preferences TEXT,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_level_id) REFERENCES User_Levels(user_level_id),
    INDEX (username)
);

-- 5. Ingredients Table (References Categories)
CREATE TABLE Ingredients (
    ingredient_id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_name VARCHAR(100) NOT NULL UNIQUE,
    unit_of_measure VARCHAR(50), -- e.g., 'grams', 'ml', 'cups', 'units'
    category_id INT, -- e.g., 'produce', 'dairy', 'meat' (optional, can be NULL)
    FOREIGN KEY (category_id) REFERENCES Categories(category_id) ON DELETE SET NULL
);

-- 6. Recipes Table (References Users)
CREATE TABLE Recipes (
    recipe_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT, -- NULL for public recipes if no specific user, or link to admin user
    title VARCHAR(255) NOT NULL,
    description TEXT,
    instructions TEXT,
    prep_time INT, -- in minutes
    cook_time INT, -- in minutes
    servings INT,
    image_path VARCHAR(255),
    creation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('private', 'public_pending', 'public_approved') DEFAULT 'private',
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE SET NULL,
    INDEX (title),
    INDEX (status)
);

-- 7. Recipe_Ingredients (Junction Table for many-to-many: Recipes <-> Ingredients)
CREATE TABLE Recipe_Ingredients (
    recipe_ingredient_id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    notes VARCHAR(255), -- e.g., 'chopped', 'sliced', 'to taste'
    FOREIGN KEY (recipe_id) REFERENCES Recipes(recipe_id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES Ingredients(ingredient_id) ON DELETE CASCADE,
    UNIQUE (recipe_id, ingredient_id) -- Prevent duplicate entries for same recipe/ingredient
);

-- 8. Meal_Plans Table (References Users)
CREATE TABLE Meal_Plans (
    plan_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    creation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
);

-- 9. Meal_Plan_Items (Junction Table for meal plan recipes: Meal_Plans <-> Recipes)
CREATE TABLE Meal_Plan_Items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    recipe_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    meal_type ENUM('Breakfast', 'Lunch', 'Dinner', 'Snack', 'Dessert') NOT NULL,
    FOREIGN KEY (plan_id) REFERENCES Meal_Plans(plan_id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id) REFERENCES Recipes(recipe_id) ON DELETE CASCADE,
    UNIQUE (plan_id, recipe_id, day_of_week, meal_type) -- Prevent duplicates for same meal type on same day
);


-- Initial Data Inserts (must come AFTER the tables they insert into)
INSERT INTO User_Levels (level_name) VALUES ('Admin'), ('Registered User');
INSERT INTO Categories (category_name) VALUES ('Breakfast'), ('Lunch'), ('Dinner'), ('Dessert'), ('Appetizer'), ('Drink');

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;