<?php
/* config.php */

// central configuration file for the TwinCities project

/* --- THE ERROR OVERSEER --- */

error_reporting(E_ALL);
ini_set('display_errors', 1);

set_exception_handler(function ($e) {
    // Log the technical tragedy to XAMPP
    error_log("TwinCities Exception: " . $e->getMessage());

    // Display the error with humour and style using Tailwind CSS
    // Notice: Only ONE opening quote after 'echo' and ONE closing quote at the very end.
    echo "
<!DOCTYPE html>
<html lang='en'>
<head>
    <script src='https://cdn.tailwindcss.com'></script>
</head>
<body class='bg-gray-50'>
    <div class='max-w-lg mx-auto mt-12 p-6 bg-white border border-gray-200 rounded-lg'>
        <div class='text-sm text-gray-500 mb-1'>Unexpected error</div>
        <h2 class='text-lg font-semibold text-gray-900 mb-2'>Something went wrong</h2>
        <p class='text-sm text-gray-600 mb-4'>We couldn’t complete your request. Please try again.</p>
        <div class='bg-gray-50 border border-gray-200 rounded-md p-3 text-sm font-mono text-red-600 mb-4 overflow-x-auto'>
            " . htmlspecialchars($e->getMessage()) . "
        </div>
        <div class='text-sm text-gray-500 mb-1'>If it keeps happening, it's probably not you...</div>
    </div>
</body>
</html>";
    exit;
});

// Determine protocol and host dynamically based on current request
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
//get host (domain or IP)
$host = $_SERVER['HTTP_HOST'];
//making sure that theres base pasth(folder or script)
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($basePath === '') $basePath = ''; // root folder fallback
// combining the protocol, host and the base path to full basr URL
$base_url = $protocol . '://' . $host . $basePath;

/* --- CONSTANTS --- */
define('DB_HOST', 'localhost');
define('DB_NAME', 'city_twin_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('WEATHER_BASE_URL', 'https://api.open-meteo.com/v1/');
define('PIXABAY_KEY', '54664421-9a17e2d26b529b08d054890af');

define('RSS_TITLE', 'Liverpool & Cologne - Places Feed');
define('RSS_DESCRIPTION', 'Dynamic RSS generated from City and Place_of_Interest tables.');
define('RSS_MAX_ITEMS', 50);

// Define Settings Array
$config = [
    //DB settings
    "db" => [
        "host" => DB_HOST,
        "name" => DB_NAME,
        "user" => DB_USER,
        "pass" => DB_PASS,
        "charset" => DB_CHARSET
    ],
    //API settings
    "api" => [
        "weather_base_url" => WEATHER_BASE_URL,
        "weather_units"    => "metric",
        "pixabay_key"      => PIXABAY_KEY
    ],

    // queries
    "queries" => [
        "get_twin_cities" => "
            SELECT 
                c1.name AS city_1, 
                c2.name AS city_2 
            FROM City c1 
            JOIN City c2 ON c1.country != c2.country 
            LIMIT 1
        "
    ],

    "weather_codes" => [
        0  => "Clear sky",
        1  => "Mainly clear",
        2  => "Partly cloudy",
        3  => "Overcast",
        45 => "Fog",
        48 => "Ice fog",
        51 => "Light drizzle",
        53 => "Drizzle",
        55 => "Heavy drizzle",
        56 => "Light freezing drizzle",
        57 => "Heavy freezing drizzle",
        61 => "Light rain",
        63 => "Moderate rain",
        65 => "Heavy rain",
        66 => "Light freezing rain",
        67 => "Heavy freezing rain",
        71 => "Light snow",
        73 => "Moderate snow",
        75 => "Heavy snow",
        77 => "Snow grains",
        80 => "Light showers",
        81 => "Showers",
        82 => "Heavy showers",
        85 => "Light snow showers",
        86 => "Heavy snow showers",
        95 => "Thunderstorm",
        96 => "Storm & hail",
        99 => "Heavy storm & hail",
    ],
    // RSS feed settings 
    "rss" => [
        "title"       => RSS_TITLE,
        "description" => RSS_DESCRIPTION,
        "base_url"    => $base_url,
        "max_items"   => RSS_MAX_ITEMS
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
    throw $e;
}
//Return config array so it may be included in other scripts
return $config;
