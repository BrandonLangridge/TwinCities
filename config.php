<?php
/* config.php */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Define Settings Array
$config = [
    "db" => [
        "host" => "localhost",
        "name" => "city_twin_db",
        "user" => "root",
        "pass" => "",
        "charset" => "utf8mb4" 
    ],

    "api" => [
        "weather_base_url" => "https://api.open-meteo.com/v1/",
        "weather_units"    => "metric",
        "pixabay_key"      => "54664421-9a17e2d26b529b08d054890af"
    ],


    "queries" => [
        "get_twin_cities" => "
            SELECT 
                c1.name AS city_1, 
                c2.name AS city_2 
            FROM Cities c1 
            JOIN Cities c2 ON c1.country != c2.country 
            LIMIT 1
        " 
    ],

    "rss" => [
        "title"       => "Liverpool & Cologne - Places Feed",
        "description" => "Dynamic RSS generated from Cities and Place_of_Interest tables.",
        "base_url"    => "http://localhost/Twin_Cities_Project", 
        "max_items"   => 50
    ]
];

// Establish PDO Connection
// Establish PDO Connection
try {
    // 1. Connect to the HOST only. 
    // We removed 'dbname' so the connection works even if the DB doesn't exist yet.
    $dsn = "mysql:host=" . $config['db']['host'] . ";charset=" . $config['db']['charset'];
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Only try to select the specific database if we are NOT on the setup page.
    if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
        $pdo->exec("USE `" . $config['db']['name'] . "`");
    }

} catch (PDOException $e) {

    // If database does not exist, automatically run setup
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        header("Location: setup.php");
        exit;
    }

    // Any other database error
    die("Database Connection Failed: " . $e->getMessage());
}

return $config;