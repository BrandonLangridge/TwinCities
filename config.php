<?php
/* config.php */

// central configuration file for the TwinCities project

//Error Reporting 
// Enable display of all errors and warning during development 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Determine protocol and host dynamically based on current request
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
//get host (domain or IP)
$host = $_SERVER['HTTP_HOST'];
//making sure that theres base pasth(folder or script)
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); 
if ($basePath === '') $basePath = ''; // root folder fallback
// combining the protocol, host and the base path to full basr URL
$base_url = $protocol . '://' . $host . $basePath;

// Define Settings Array
$config = [
    //DB settings
    "db" => [
        "host" => "localhost",
        "name" => "city_twin_db",
        "user" => "root",
        "pass" => "",
        "charset" => "utf8mb4" 
    ],
    //API settings
    "api" => [
        "weather_base_url" => "https://api.open-meteo.com/v1/",
        "weather_units"    => "metric",
        "pixabay_key"      => "54664421-9a17e2d26b529b08d054890af"
    ],
    
    // queries
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
    // RSS feed settings 
    "rss" => [
        "title"       => "Liverpool & Cologne - Places Feed",
        "description" => "Dynamic RSS generated from Cities and Place_of_Interest tables.",
        "base_url"    => "http://localhost/TwinCities", 
        "max_items"   => 50
    ]
];

// Establish PDO Connection
try {
    // DSN for PDO connection
    // connects to the host only 
    $dsn = "mysql:host=" . $config['db']['host'] . ";charset=" . $config['db']['charset'];
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
    
    // sets PDO to throw exceptions on errors 
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Only try to select the specific database if we are NOT on the setup page.
    if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
        $pdo->exec("USE `" . $config['db']['name'] . "`");
    }

} catch (PDOException $e) {

    // If database does not exist, automatically run setup
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        //redirects to setup.php to allow automatic creation
        header("Location: setup.php");
        exit;
    }

    // Any other database error
    die("Database Connection Failed: " . $e->getMessage());
}
//Return config array so it may be included in other scripts
return $config;

