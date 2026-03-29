<?php
/**
 * index.php - Unified Controller & View for Twin Cities Application
 * * This file serves as the main entry point (Router/Controller) for the application.
 * It manages:
 * 1. State: Session management and city selection routing.
 * 2. Data: Fetching Points of Interest (POIs) and Comments from the database.
 * 3. Integrations: Preparing data for Leaflet maps, RSS feeds, and Weather widgets.
 * 4. Accessibility: Handling voice synthesis and color-blind accessibility modes.
 */

session_start(); // Initialise session to persist user feedback or temporary upload states

// --- INITIALIZATION & DEPENDENCIES ---

// Load configuration containing DB credentials, API keys, and system constants
$config = require_once __DIR__ . '/config.php';

// Load business logic for handling user comments (submission and retrieval)
require_once __DIR__ . '/comments_logic.php';



/**
 * DATABASE VALIDATION
 * Before proceeding, ensure the database is reachable.
 * If the 'City' table doesn't exist or connection fails, redirect to the setup wizard.
 */
try {
    $pdo->query("SELECT 1 FROM City LIMIT 1");
} catch (Exception $e) {
    header("Location: setup.php");
    exit;
}

/**
 * STATIC CITY DATA
 * Definitions for the two primary cities. Used for:
 * - Rendering the initial selection landing page
 * - Setting map center coordinates
 * - Validating 'city' GET parameters for routing
 */
$cities = [
    "Liverpool" => ["lat" => 53.4106, "lon" => -2.9779, "image" => "app_images/liverpool.jpg", "label" => "Liverpool"],
    "Cologne"   => ["lat" => 50.9333, "lon" => 6.95,    "image" => "app_images/cologne.jpg",   "label" => "Cologne"]
];

// Determine the requested city from the URL (e.g., index.php?city=liverpool)
$requestedCity   = isset($_GET['city']) ? ucfirst($_GET['city']) : null;

// Validate if the requested city is supported by our $cities array
$hasSelectedCity = $requestedCity && array_key_exists($requestedCity, $cities);

// Set specific city name for database queries if valid
$currentCityName = $hasSelectedCity ? $requestedCity : null;

// Generate a version string based on CSS file modification time to prevent browser caching issues
$assetVersion = filemtime('styles.css');

// Default initialization to prevent PHP notices in the global scope
$currentCityId = 1;
$otherCityName = null;
$coords         = ["lat" => 0, "lon" => 0];

// Preparation for the Weather API widget
$weatherBase = rtrim(WEATHER_BASE_URL, '/');
$units       = $config['api']['weather_units'] ?? 'metric';

// --- LOGIC FOR SELECTED CITY VIEW ---

if ($hasSelectedCity) {
    // Map center coordinates retrieved from static array
    $coords = $cities[$currentCityName];

    /**
     * DATABASE LOOKUP: City ID
     * Retrieve the unique ID for the selected city to link POIs and Comments.
     */
    $stmt = $pdo->prepare("SELECT city_id FROM City WHERE name = ? LIMIT 1");
    $stmt->execute([$currentCityName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentCityId = ($row && isset($row['city_id'])) ? $row['city_id'] : 1;

    /**
     * DATABASE LOOKUP: Points of Interest
     * Fetches POI data including coordinates and descriptions.
     * Uses a JOIN with the City table to ensure relational integrity.
     */
    try {
        $stmtPois = $pdo->prepare("SELECT p.poi_id, p.name, p.type, p.latitude, p.longitude, p.description, p.year_opened, p.entry_fee, p.rating, c.name AS city_name FROM Place_of_Interest p JOIN City c ON c.city_id = p.city_id WHERE LOWER(c.name) = LOWER(?) ORDER BY p.poi_id ASC");
        $stmtPois->execute([$currentCityName]);
        $poiRows = $stmtPois->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $poiRows = [];
    }

    /**
     * DATA FORMATTING FOR JAVASCRIPT
     * Maps the database result set into a clean associative array.
     * This will be encoded to JSON later for use by the Leaflet Map engine.
     */
    $jsPois = array_map(function ($r) {
        return [
            'id'          => (int)($r['poi_id'] ?? 0),
            'town'        => $r['city_name'] ?? null,
            'name'        => $r['name'] ?? null,
            'lat'         => (float)($r['latitude'] ?? 0),
            'lng'         => (float)($r['longitude'] ?? 0),
            'description' => $r['description'] ?? null,
            'type'        => $r['type'] ?? null,
            'yearOpened'  => (int)($r['year_opened'] ?? 0),
            'entryFee'    => $r['entry_fee'] ?? null,
            'rating'      => (float)($r['rating'] ?? 0),
        ];
    }, $poiRows);

    // Load the weather widget logic if the file exists on the server
    if (file_exists(__DIR__ . '/weather_widget.php')) require_once __DIR__ . '/weather_widget.php';

    /**
     * UI HELPER: Switch City
     * Identifies the 'other' city in the array to provide a quick-switch toggle in the UI.
     */
    foreach (array_keys($cities) as $name) {
        if ($name !== $currentCityName) {
            $otherCityName = $name;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Twin Cities</title>
    <link rel="stylesheet" href="styles.css?v=<?= urlencode($assetVersion); ?>"> 
    <link rel="icon" type="image/png" href="app_images/cityfav.png">
    
    <?php if ($hasSelectedCity): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <?php endif; ?>
</head>
<body class="<?= $hasSelectedCity ? 'map-page' : 'index-body' ?>">

<header class="<?= $hasSelectedCity ? 'index-selected-hero' : '' ?>">
    <div class="<?= $hasSelectedCity ? 'index-selected-main' : '' ?>">
        <h1>Twin Cities: <span class="h1-accent"><?= $hasSelectedCity ? htmlspecialchars($currentCityName) : 'Liverpool & Cologne' ?></span></h1>
        
        <div class="toggle-container">
            <button id="voiceToggle" class="toggle-button btn--accent">Voice Feedback: OFF</button>

            <select id="colorModeSelect" class="toggle-button color-select-dropdown">
                <option value="none">Color-Blind Mode: OFF</option>
                <option value="protan">Protanopia</option>
                <option value="deutan">Deuteranopia</option>
                <option value="tritan">Tritanopia</option>
            </select>

            <?php if ($hasSelectedCity): ?>
                <button id="rssJump" class="toggle-button btn--accent">View RSS Feed</button>
                <button id="weatherJump" class="toggle-button btn--accent-alt">View Weather</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($hasSelectedCity && $otherCityName): ?>
        <a class="city city-mini-switch" href="index.php?city=<?= urlencode(strtolower($otherCityName)); ?>">
            <img src="<?= htmlspecialchars($cities[$otherCityName]['image']); ?>" alt="<?= $otherCityName ?>">
            <h2><?= htmlspecialchars($otherCityName); ?></h2>
            <span class="city-button">Switch</span>
        </a>
    <?php endif; ?>
</header>

<main>
<?php if (!$hasSelectedCity): ?>
    <div class="city-container">
        <?php foreach ($cities as $name => $data): ?>
            <a class="city" href="index.php?city=<?= strtolower($name) ?>">
                <img src="<?= $data['image'] ?>" alt="<?= $name ?>">
                <h2><?= $name ?></h2>
                <span class="city-button">Explore</span>
            </a>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <section class="map-rss-row" id="mapRssRow" aria-label="Map and RSS area">
        <?php
        /**
         * MAP INTEGRATION
         * Data is bridged from PHP to JS by echo-ing a JSON object.
         * The 'maps.php' file contains the Leaflet initialization logic.
         */
        echo "<script>const pois = " . json_encode($jsPois, JSON_UNESCAPED_UNICODE) . ";</script>";
        include 'maps.php';
        ?>

        <aside class="rss-side-panel" id="rssSidePanel">
            <h2>City RSS Feed</h2>
            <iframe id="rssFrame" title="City RSS feed" loading="lazy"></iframe>
        </aside>

        <aside class="weather-side-panel" id="weatherSidePanel">
            <div class="weather-dashboard">
                <?php if (function_exists('renderWeatherWidget')) {
                    renderWeatherWidget($currentCityName, $coords['lat'], $coords['lon'], $weatherBase, $units, $config['weather_codes']);
                } ?>
            </div>
        </aside>
    </section>

    <?php include 'photo_widget.php'; ?>

    <section id="comments-section">
        <div class="container">
            <h2 class="comments-title">Comments</h2>
            
            <form class="comment-form" method="POST">
                <input type="hidden" name="city_id" value="<?= (int)$currentCityId; ?>">
                <input type="text" name="user_name" placeholder="Your name" maxlength="100" required>
                <textarea name="comment_text" placeholder="Write a comment..." maxlength="2000" required></textarea>
                <button type="submit" name="submit_comment" class="toggle-button">Post Comment</button>
            </form>

            <?php 
            /**
             * RENDER COMMENTS
             * Fetches list of comments from database for the current city ID.
             */
            $comments = function_exists('getCommentsForCity') ? getCommentsForCity((int)$currentCityId, $pdo) : []; 
            ?>
            
            <?php if (empty($comments)): ?>
                <p class="empty-note">No comments yet. Be the first to post!</p>
            <?php else: foreach ($comments as $c): ?>
                <div class="comment-card" style="position: relative;">
                    <form method="POST" style="position: absolute; top: 10px; right: 10px; margin: 0;">
                        <input type="hidden" name="delete_id" value="<?= (int)($c['comments_id'] ?? $c['comment_id']); ?>">
                        <input type="hidden" name="city_id" value="<?= (int)$currentCityId; ?>">
                        <button type="submit" name="delete_comment" class="delete-btn">×</button>
                    </form>
                    
                    <div class="comment-header"><strong><?= htmlspecialchars($c['user_name'] ?? 'Anonymous'); ?></strong></div>
                    <p><?= nl2br(htmlspecialchars($c['comment_text'] ?? '')); ?></p>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </section>
<?php endif; ?>
</main>

<div id="aria-live" aria-live="polite" style="position:absolute; left:-9999px;"></div>

<script>
/**
 * --- JAVASCRIPT GLOBAL HANDLERS ---
 * Manages the client-side interactivity, local storage, and Web Speech API.
 */

// STATE MANAGEMENT: Persistent UI preferences via LocalStorage
let voiceEnabled = localStorage.getItem("voiceEnabled") === "true";
const voiceBtn = document.getElementById("voiceToggle");
const colorSelect = document.getElementById("colorModeSelect");

/**
 * SPEECH ENGINE
 * Orchestrates text-to-speech feedback and ARIA live region updates.
 * @param {string} text - The message to announce.
 */
function speak(text) {
    const liveRegion = document.getElementById('aria-live');
    if (liveRegion) liveRegion.textContent = text; // Visual accessibility fallback
    if (!voiceEnabled || !window.speechSynthesis) return;
    
    window.speechSynthesis.cancel(); // Interrupt previous speech for responsiveness
    window.speechSynthesis.speak(new SpeechSynthesisUtterance(text));
}

/**
 * PAGE LOAD ANNOUNCEMENTS
 * Handles system messages passed through the URL (e.g., upload success)
 * and announces city transitions.
 */
(function() {
    setTimeout(() => {
        const params = new URLSearchParams(window.location.search);
        // Check for specific 'announce' flag in query string
        if (params.has('announce')) {
            const msg = params.get('announce').replace(/_/g, ' ');
            speak(msg);
            
            // Cleanup URL to prevent announcement repeating on refresh
            params.delete('announce');
            const newUrl = window.location.pathname + '?' + params.toString() + window.location.hash;
            history.replaceState(null, '', newUrl);
        }
        <?php if ($hasSelectedCity): ?>
        else {
            speak("Switching to <?= htmlspecialchars($currentCityName) ?>");
        }
        <?php endif; ?>
    }, 200);
})();

/**
 * UI CONTROLS: COLOR MODES
 * Applies a global CSS class to the <html> tag to trigger color-blind filters.
 */
function applyColorMode(mode) {
    document.documentElement.className = (mode !== "none") ? mode : "";
    if (colorSelect) colorSelect.value = mode;
}

// Voice feedback toggle event
if (voiceBtn) {
    voiceBtn.onclick = () => {
        voiceEnabled = !voiceEnabled;
        localStorage.setItem("voiceEnabled", voiceEnabled);
        voiceBtn.textContent = `Voice Feedback: ${voiceEnabled ? "ON" : "OFF"}`;
        speak(`Voice feedback ${voiceEnabled ? 'enabled' : 'disabled'}`);
    };
}

// Color-blind mode selection event
if (colorSelect) {
    colorSelect.onchange = (e) => {
        const mode = e.target.value;
        applyColorMode(mode);
        localStorage.setItem("colorMode", mode);
        speak(`${mode} mode active`);
    };
}

// Initial UI application from saved user preferences
applyColorMode(localStorage.getItem("colorMode") || "none");
if (voiceBtn) voiceBtn.textContent = `Voice Feedback: ${voiceEnabled ? "ON" : "OFF"}`;

/**
 * SIDE PANEL LOGIC
 * Manages the 'Drawer' system for RSS and Weather.
 * Ensures only one panel is active at a time and handles iframe lazy-loading.
 */
(function() {
    const rssBtn = document.getElementById('rssJump');
    const weatherBtn = document.getElementById('weatherJump');
    const mapRssRow = document.getElementById('mapRssRow');
    const rssFrame = document.getElementById('rssFrame');
    const rssPanel = document.getElementById('rssSidePanel');
    const weatherPanel = document.getElementById('weatherSidePanel');

    /**
     * Toggles visibility of side panels and adjusts map container size.
     * @param {string} panelName - 'rss' or 'weather'
     */
    function toggleSidePanel(panelName) {
        if (!mapRssRow) return;
        
        const currentlyOpen = mapRssRow.classList.contains("rss-open");
        const activePanel = mapRssRow.dataset.activePanel || "";

        // If clicking the button of a panel that is already open, close it.
        if (currentlyOpen && activePanel === panelName) {
            mapRssRow.classList.remove("rss-open");
            mapRssRow.dataset.activePanel = "";
            rssPanel?.classList.remove("active");
            weatherPanel?.classList.remove("active");
            
            if (rssBtn) rssBtn.textContent = "View RSS Feed";
            if (weatherBtn) weatherBtn.textContent = "View Weather";
            speak("Hiding side panel");
            
            // Trigger map resize event so Leaflet updates its container bounds
            setTimeout(() => window.dispatchEvent(new Event("resize")), 200);
            return;
        }

        // Lazy load the RSS iframe only when first requested
        if (panelName === "rss" && !rssFrame.getAttribute("src")) {
            const cityParam = "<?= isset($currentCityName) ? urlencode(strtolower($currentCityName)) : ''; ?>";
            rssFrame.setAttribute('src', 'rss_view.php?city=' + cityParam);
        }

        // Update layout state
        mapRssRow.classList.add("rss-open");
        mapRssRow.dataset.activePanel = panelName;
        
        // Toggle specific panel visibility
        rssPanel?.classList.toggle("active", panelName === "rss");
        weatherPanel?.classList.toggle("active", panelName === "weather");
        
        // UI Text updates
        if (rssBtn) rssBtn.textContent = panelName === "rss" ? "Hide RSS Feed" : "View RSS Feed";
        if (weatherBtn) weatherBtn.textContent = panelName === "weather" ? "Hide Weather" : "View Weather";
        
        speak("View " + panelName);
        
        // Recalculate map dimensions to prevent grey tiles
        setTimeout(() => window.dispatchEvent(new Event("resize")), 200);
    }

    if (rssBtn) rssBtn.addEventListener("click", () => toggleSidePanel("rss"));
    if (weatherBtn) weatherBtn.addEventListener("click", () => toggleSidePanel("weather"));
})();
</script>
</body>
</html>