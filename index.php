<?php
session_start(); // Start session for temporary storage (uploads, messages)

/**
 * index.php - Unified Controller Version
 * Main controller handling:
 * - City selection and routing
 * - File uploads
 * - Database queries for cities, POIs, photos, and comments
 * - Rendering HTML + JS for the frontend
 */

// --- INITIALIZATION ---

// Load configuration (DB, API keys, etc.)
$config = require_once __DIR__ . '/config.php';

// Load reusable comment functions
require_once __DIR__ . '/comments_logic.php';

// Verify database connection (check Cities table)
try {
    $pdo->query("SELECT 1 FROM Cities LIMIT 1");
} catch (Exception $e) {
    // Redirect to setup page if DB not ready
    header("Location: setup.php");
    exit;
}

// Static city data for UI and routing (avoids extra DB queries)
$cities = [
    "Liverpool" => ["lat" => 53.4106, "lon" => -2.9779, "image" => "app_images/liverpool.jpg", "label" => "Liverpool"],
    "Cologne"   => ["lat" => 50.9333, "lon" => 6.95,    "image" => "app_images/cologne.jpg",   "label" => "Cologne"]
];

// Get selected city from URL
$requestedCity   = isset($_GET['city']) ? ucfirst($_GET['city']) : null;

// Check if selected city exists in static list
$hasSelectedCity = $requestedCity && array_key_exists($requestedCity, $cities);

// Store current city name if valid
$currentCityName = $hasSelectedCity ? $requestedCity : null;

// Cache busting for CSS
$assetVersion = filemtime('styles.css');

// Initialize variables to prevent warnings
$currentCityId = 1;
$otherCityName = null;
$coords        = ["lat" => 0, "lon" => 0];

// Weather API setup
$weatherBase = rtrim(WEATHER_BASE_URL, '/');
$units       = $config['api']['weather_units'] ?? 'metric';

// --- LOGIC FOR SELECTED CITY ---

if ($hasSelectedCity) {
    // Set coordinates for the selected city
    $coords = $cities[$currentCityName];

    // --- HANDLE IMAGE UPLOAD ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['new_photo'])) {

        $city_id = (int)$_POST['city_id'];
        $file    = $_FILES['new_photo'];

        // Upload constraints
        $max_size = 2 * 1024 * 1024; // 2MB
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png'];

        // Validate upload
        if ($file['size'] > 0 && $file['size'] <= $max_size && $file['error'] === 0 && in_array($ext, $allowed)) {

            // Ensure upload directory exists
            if (!file_exists("user_pics/")) mkdir("user_pics/", 0755, true);

            // Unique filename to prevent overwriting
            $target = "user_pics/city{$city_id}_" . uniqid() . ".{$ext}";

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $target)) {

                // Store reference in database
                $stmt = $pdo->prepare("INSERT INTO Photos (city_id, page_num, image_url, caption, cached_at) VALUES (?, ?, ?, 'USER_UPLOAD', NOW())");
                $stmt->execute([(int)$city_id, 1, $target]);

                // Strip pagination params from URL
                $params = $_GET;
                unset($params['p']);
                foreach ($params as $key => $value) {
                    if (substr($key, -2) === '_p') unset($params[$key]);
                }

                // Reset pagination and add announcement
                $params['p'] = 1;
                $params['announce'] = 'picture_added_to_page_1';

                // Redirect to refresh page state
                header("Location: index.php?" . http_build_query($params) . "#photo-widget");
                exit;
            }

        } else {
            // Store upload error in session
            $_SESSION['upload_msg'] = ($file['size'] > 2 * 1024 * 1024) ? 'too_big' : 'wrong_type';
            header("Location: index.php?city=" . urlencode($_GET['city']) . "#photo-widget");
            exit;
        }
    }

    // --- GET CITY ID FROM DATABASE ---
    $stmt = $pdo->prepare("SELECT city_id FROM Cities WHERE name = ? LIMIT 1");
    $stmt->execute([$currentCityName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentCityId = ($row && isset($row['city_id'])) ? $row['city_id'] : 1;

    // --- FETCH POINTS OF INTEREST ---
    try {
        $stmtPois = $pdo->prepare("SELECT p.poi_id, p.name, p.type, p.latitude, p.longitude, p.description, p.year_opened, p.entry_fee, p.rating, c.name AS city_name FROM Place_of_Interest p JOIN Cities c ON c.city_id = p.city_id WHERE LOWER(c.name) = LOWER(?) ORDER BY p.poi_id ASC");
        $stmtPois->execute([$currentCityName]);
        $poiRows = $stmtPois->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $poiRows = [];
    }

    // Convert POIs to JS-friendly format
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

    // Load weather widget if available
    if (file_exists(__DIR__ . '/weather_widget.php')) require_once __DIR__ . '/weather_widget.php';

    // Determine the "other city" for switch button
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
    <link rel="stylesheet" href="styles.css?v=<?= urlencode($assetVersion); ?>"> <!-- Cache-busting -->
    <link rel="icon" type="image/png" href="app_images/cityfav.png">
    <?php if ($hasSelectedCity): ?>
        <!-- Leaflet map CSS -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <?php endif; ?>
</head>
<body class="<?= $hasSelectedCity ? 'map-page' : 'index-body' ?>">

<header class="<?= $hasSelectedCity ? 'index-selected-hero' : '' ?>">
    <div class="<?= $hasSelectedCity ? 'index-selected-main' : '' ?>">
        <h1>Twin Cities: <span class="h1-accent"><?= $hasSelectedCity ? htmlspecialchars($currentCityName) : 'Liverpool & Cologne' ?></span></h1>
        <div class="toggle-container">
            <!-- Voice feedback toggle -->
            <button id="voiceToggle" class="toggle-button btn--accent">Voice Feedback: OFF</button>

            <!-- Color-blind mode dropdown -->
            <select id="colorModeSelect" class="toggle-button color-select-dropdown">
                <option value="none">Color-Blind Mode: OFF</option>
                <option value="protan">Protanopia</option>
                <option value="deutan">Deuteranopia</option>
                <option value="tritan">Tritanopia</option>
            </select>

            <?php if ($hasSelectedCity): ?>
                <!-- Side panel buttons -->
                <button id="rssJump" class="toggle-button btn--accent">View RSS Feed</button>
                <button id="weatherJump" class="toggle-button btn--accent-alt">View Weather</button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($hasSelectedCity && $otherCityName): ?>
        <!-- Button to switch to the other city -->
        <a class="city city-mini-switch" href="index.php?city=<?= urlencode(strtolower($otherCityName)); ?>">
            <img src="<?= htmlspecialchars($cities[$otherCityName]['image']); ?>" alt="<?= $otherCityName ?>">
            <h2><?= htmlspecialchars($otherCityName); ?></h2>
            <span class="city-button">Switch</span>
        </a>
    <?php endif; ?>
</header>

<main>
<?php if (!$hasSelectedCity): ?>
    <!-- City selection view -->
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
    <!-- Selected city view with map, RSS, weather -->
    <section class="map-rss-row" id="mapRssRow" aria-label="Map and RSS area">
        <?php
        // Pass POIs to JS
        echo "<script>const pois = " . json_encode($jsPois, JSON_UNESCAPED_UNICODE) . ";</script>";
        include 'maps.php';
        ?>

        <!-- RSS side panel -->
        <aside class="rss-side-panel" id="rssSidePanel">
            <h2>City RSS Feed</h2>
            <iframe id="rssFrame" title="City RSS feed" loading="lazy"></iframe>
        </aside>

        <!-- Weather side panel -->
        <aside class="weather-side-panel" id="weatherSidePanel">
            <div class="weather-dashboard">
                <?php if (function_exists('renderWeatherWidget')) {
                    renderWeatherWidget($currentCityName, $coords['lat'], $coords['lon'], $weatherBase, $units, $config['weather_codes']);
                } ?>
            </div>
        </aside>
    </section>

    <?php include 'photo_widget.php'; ?> <!-- Photo upload/display widget -->

    <!-- Comments Section -->
    <section id="comments-section">
        <div class="container">
            <h2 class="comments-title">Comments</h2>
            <form class="comment-form" method="POST">
                <input type="hidden" name="city_id" value="<?= (int)$currentCityId; ?>">
                <input type="text" name="user_name" placeholder="Your name" maxlength="100" required>
                <textarea name="comment_text" placeholder="Write a comment..." maxlength="2000" required></textarea>
                <button type="submit" name="submit_comment" class="toggle-button">Post Comment</button>
            </form>

            <?php $comments = function_exists('getCommentsForCity') ? getCommentsForCity((int)$currentCityId, $pdo) : []; ?>
            <?php if (empty($comments)): ?>
                <p class="empty-note">No comments yet. Be the first to post!</p>
            <?php else: foreach ($comments as $c): ?>
                <div class="comment-card" style="position: relative;">
                    <!-- Delete comment button -->
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

<!-- ARIA live region for voice feedback -->
<div id="aria-live" aria-live="polite" style="position:absolute; left:-9999px;"></div>

<script>
// VOICE FEEDBACK ENGINE
let voiceEnabled = localStorage.getItem("voiceEnabled") === "true";
const voiceBtn = document.getElementById("voiceToggle");
const colorSelect = document.getElementById("colorModeSelect");

// Function to speak messages
function speak(text) {
    const liveRegion = document.getElementById('aria-live');
    if (liveRegion) liveRegion.textContent = text;
    if (!voiceEnabled || !window.speechSynthesis) return;
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(new SpeechSynthesisUtterance(text));
}

// ANNOUNCEMENT HANDLER
(function() {
    setTimeout(() => {
        const params = new URLSearchParams(window.location.search);
        if (params.has('announce')) {
            const msg = params.get('announce').replace(/_/g, ' ');
            speak(msg);
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

// UI CONTROLS
function applyColorMode(mode) {
    document.documentElement.className = (mode !== "none") ? mode : "";
    if (colorSelect) colorSelect.value = mode;
}

// Voice toggle button
if (voiceBtn) {
    voiceBtn.onclick = () => {
        voiceEnabled = !voiceEnabled;
        localStorage.setItem("voiceEnabled", voiceEnabled);
        voiceBtn.textContent = `Voice Feedback: ${voiceEnabled ? "ON" : "OFF"}`;
        speak(`Voice feedback ${voiceEnabled ? 'enabled' : 'disabled'}`);
    };
}

// Color-blind mode dropdown
if (colorSelect) {
    colorSelect.onchange = (e) => {
        const mode = e.target.value;
        applyColorMode(mode);
        localStorage.setItem("colorMode", mode);
        speak(`${mode} mode active`);
    };
}

// Apply saved color mode
applyColorMode(localStorage.getItem("colorMode") || "none");
if (voiceBtn) voiceBtn.textContent = `Voice Feedback: ${voiceEnabled ? "ON" : "OFF"}`;

// SIDE PANEL TOGGLE LOGIC
(function() {
    const rssBtn = document.getElementById('rssJump');
    const weatherBtn = document.getElementById('weatherJump');
    const mapRssRow = document.getElementById('mapRssRow');
    const rssFrame = document.getElementById('rssFrame');
    const rssPanel = document.getElementById('rssSidePanel');
    const weatherPanel = document.getElementById('weatherSidePanel');

    function toggleSidePanel(panelName) {
        if (!mapRssRow) return;
        const currentlyOpen = mapRssRow.classList.contains("rss-open");
        const activePanel = mapRssRow.dataset.activePanel || "";

        if (currentlyOpen && activePanel === panelName) {
            mapRssRow.classList.remove("rss-open");
            mapRssRow.dataset.activePanel = "";
            rssPanel?.classList.remove("active");
            weatherPanel?.classList.remove("active");
            if (rssBtn) rssBtn.textContent = "View RSS Feed";
            if (weatherBtn) weatherBtn.textContent = "View Weather";
            speak("Hiding side panel");
            setTimeout(() => window.dispatchEvent(new Event("resize")), 200);
            return;
        }

        if (panelName === "rss" && !rssFrame.getAttribute("src")) {
            const cityParam = "<?= isset($currentCityName) ? urlencode(strtolower($currentCityName)) : ''; ?>";
            rssFrame.setAttribute('src', 'rss_view.php?city=' + cityParam);
        }

        mapRssRow.classList.add("rss-open");
        mapRssRow.dataset.activePanel = panelName;
        rssPanel?.classList.toggle("active", panelName === "rss");
        weatherPanel?.classList.toggle("active", panelName === "weather");
        if (rssBtn) rssBtn.textContent = panelName === "rss" ? "Hide RSS Feed" : "View RSS Feed";
        if (weatherBtn) weatherBtn.textContent = panelName === "weather" ? "Hide Weather" : "View Weather";
        speak("View " + panelName);
        setTimeout(() => window.dispatchEvent(new Event("resize")), 200);
    }

    if (rssBtn) rssBtn.addEventListener("click", () => toggleSidePanel("rss"));
    if (weatherBtn) weatherBtn.addEventListener("click", () => toggleSidePanel("weather"));
})();
</script>
</body>
</html>