<?php
// No session_start() needed here, as it's an API endpoint for data.
// It relies on database.php which is required by Ingredient.php
require_once '../../app/classes/Ingredient.php'; // Adjust path from app/api/

header('Content-Type: application/json');

$ingredient = new Ingredient();

$query = $_GET['query'] ?? '';

if (strlen($query) < 2) { // Require at least 2 characters for search
    echo json_encode([]);
    exit;
}

$results = $ingredient->searchIngredients($query);

echo json_encode($results);
?>