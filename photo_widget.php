<?php

/**
 * photo_widget.php - Full Updated Version
 * * This file serves as both a standalone page and a modular widget.
 * 1. Environment detection: Checks if it's being included or run directly.
 * 2. File upload processing: Validates and saves user-submitted images.
 * 3. Data merging: Combines local database uploads with external API results.
 * 4. UI rendering: Displays a paginated photo grid with a modal viewer.
 */

// --- SESSION MANAGEMENT ---
// Sessions are used to persist upload error messages across the PRG (Post-Redirect-Get) pattern.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. BOOTSTRAP / STANDALONE CHECK ---
// If $pdo isn't defined, the file is likely being accessed directly rather than included.
if (!isset($pdo)) {
    // Load configuration and core logic functions
    $config = require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/photo_logic.php';

    // Default city data for the standalone switcher UI
    $cities = [
        "Liverpool" => ["image" => "app_images/liverpool.jpg"],
        "Cologne"   => ["image" => "app_images/cologne.jpg"]
    ];

    // Determine current city from URL; default to Liverpool
    $currentCityName = isset($_GET['city']) ? ucfirst($_GET['city']) : 'Liverpool';

    // Lookup the internal ID for the current city to query the database correctly
    $stmt = $pdo->prepare("SELECT city_id FROM City WHERE name = ? LIMIT 1");
    $stmt->execute([$currentCityName]);
    $currentCityId = $stmt->fetchColumn() ?: 1;

    $isStandalone = true;
} else {
    // Variable was already set by a parent file (e.g., index.php)
    $isStandalone = false;
}

// Ensure business logic functions are available regardless of entry point
require_once __DIR__ . '/photo_logic.php';

// --- 2. UPLOAD PROCESSING ---
// Handle POST requests triggered by the hidden file input
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['new_photo'])) {
    $upload_city_id = (int)($_POST['city_id'] ?? $currentCityId);
    $file = $_FILES['new_photo'];

    // Security/Validation Check: File size (limit 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        $_SESSION['upload_msg'] = 'too_big';
    }
    // Security/Validation Check: MIME type whitelist
    elseif (!in_array($file['type'], ['image/jpeg', 'image/jpg', 'image/png'])) {
        $_SESSION['upload_msg'] = 'wrong_type';
    }
    // If no upload errors, move the file to the local directory
    elseif ($file['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/user_pics/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        // Generate a unique filename using timestamp to prevent overwriting
        $filename = time() . '_' . basename($file['name']);

        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            // Save the file path reference in the database
            $stmt = $pdo->prepare("INSERT INTO Photo (city_id, image_url, caption) VALUES (?, ?, 'USER_UPLOAD')");
            $stmt->execute([$upload_city_id, 'user_pics/' . $filename]);
        }
    }

    // REDIRECT (Post-Redirect-Get pattern) to prevent duplicate uploads on refresh
    $ref = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    $url_parts = parse_url($ref);
    parse_str($url_parts['query'] ?? '', $query);

    // Always return to page 1 to show the newly uploaded photo
    $query['p'] = 1;

    // Accessibility: Trigger screen reader announcements if integrated into a larger page
    if (!$isStandalone) {
        $query['announce'] = 'picture_added_to_page_1';
    }

    header("Location: " . ($url_parts['path'] ?? 'index.php') . '?' . http_build_query($query) . '#photo-widget');
    exit;
}

// --- 3. DATA VIEWING & PAGINATION ---
$per_page = 3; // Number of images to show per page
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;

// First, fetch photos uploaded by users from the local DB
$user_photos = get_local_user_photos($pdo, (int)$currentCityId, $page, $per_page);

// If user photos don't fill the 'per_page' limit, fill the remaining slots with API photos
$slots = $per_page - count($user_photos);
$api_photos = [];

if ($slots > 0) {
    // Try to get cached Pixabay results from local DB first to save API hits
    $api_photos = get_cached_api_photos($pdo, (int)$currentCityId, $page, $slots, 3600);

    // If cache is empty or insufficient, fetch live from Pixabay
    if (count($api_photos) < $slots) {
        $fetched = fetch_pixabay_photos($pdo, PIXABAY_KEY, (int)$currentCityId, $currentCityName, $page, $slots - count($api_photos), $per_page);
        $api_photos = array_merge($api_photos, $fetched);
    }
}

// --- 4. NAVIGATION PREPARATION ---
$current_file = basename($_SERVER['PHP_SELF']);
$otherCityName = (strtolower($currentCityName) === 'liverpool') ? 'Cologne' : 'Liverpool';

$prev_page = max(1, $page - 1);
$next_page = $page + 1;

// Define "announce" strings for screen readers (typically handled by the parent container)
$ann_p = !$isStandalone ? "&announce=" . $currentCityName . "_pictures_page_" . $prev_page : "";
$ann_n = !$isStandalone ? "&announce=" . $currentCityName . "_pictures_page_" . $next_page : "";
$ann_s = !$isStandalone ? "&announce=Switching_to_" . $otherCityName : "";

// Generate URLs for navigation buttons
$prev_url = "?city=" . strtolower($currentCityName) . "&p=" . $prev_page . $ann_p . "#photo-widget";
$next_url = "?city=" . strtolower($currentCityName) . "&p=" . $next_page . $ann_n . "#photo-widget";
$switch_url = $current_file . "?city=" . strtolower($otherCityName) . "&p=1" . $ann_s . "#photo-widget";
?>

<?php if ($isStandalone): ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="styles.css">
        <link rel="icon" type="image/png" href="app_images/cityfav.png">
        <title>Photos of <?= htmlspecialchars($currentCityName) ?></title>
    </head>

    <body class="map-page standalone-body">
        <header class="standalone-header">
            <a class="city city-mini-switch" href="<?= htmlspecialchars($switch_url) ?>">
                <img src="app_images/<?= strtolower($otherCityName) ?>.jpg" alt="<?= $otherCityName ?>" class="switch-thumb">
                <h2 class="switch-title"><?= $otherCityName ?></h2>
                <span class="city-button">Switch</span>
            </a>
        </header>
    <?php endif; ?>

    <div id="photo-widget" class="city-card photo-widget-card">

        <?php
        // Retrieve and clear upload messages from the session
        $msg = $_SESSION['upload_msg'] ?? null;
        unset($_SESSION['upload_msg']);
        if ($msg && $msg !== 'success'): ?>
            <div class="upload-error-msg">
                <?= ($msg === 'too_big') ? "Rejected: File over 2MB." : "Rejected: Use JPG or PNG."; ?>
            </div>
        <?php endif; ?>

        <div class="nav">
            <a href="<?= htmlspecialchars($prev_url) ?>" class="btn <?= ($page <= 1) ? 'hidden' : '' ?>">Prev</a>

            <div class="city-header">
                <h2 class="city-title">
                    Pictures of <?= htmlspecialchars($currentCityName) ?>

                    <form action="photo_widget.php" method="POST" enctype="multipart/form-data" style="display:inline;">
                        <label class="add-btn">+ Add
                            <input type="file" name="new_photo" accept=".jpg,.jpeg,.png" style="display:none;" onchange="this.form.submit()">
                        </label>
                        <input type="hidden" name="city_id" value="<?= $currentCityId ?>">
                    </form>
                </h2>
                <span class="page-counter">Page <?= $page ?></span>
            </div>

            <a href="<?= htmlspecialchars($next_url) ?>" class="btn">Next</a>
        </div>

        <div class="grid">
            <?php foreach ($user_photos as $lp): ?>
                <div class="photo-container">
                    <span class="user-badge">User</span>
                    <img src="<?= htmlspecialchars($lp) ?>" alt="User upload" loading="lazy" onclick="openPhotoModal(this.src)">
                </div>
            <?php endforeach; ?>

            <?php foreach ($api_photos as $img): ?>
                <div class="photo-container">
                    <img src="<?= htmlspecialchars($img['image_url']) ?>" alt="Pixabay image" loading="lazy"
                        onclick="openPhotoModal(this.src, '<?= htmlspecialchars($img['caption']) ?>')">
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="imageModal" class="photo-modal">
        <img id="modalImg" class="modal-content">
        <a id="pixabayLink" class="add-btn pixabay-external-link" target="_blank">
            See the image's Pixabay page
        </a>
    </div>

    <script>
        /**
         * Opens the modal and populates the image/link.
         * @param {string} src - The image URL
         * @param {string|null} pixUrl - Optional URL to the Pixabay source page
         */
        function openPhotoModal(src, pixUrl = null) {
            const modal = document.getElementById('imageModal');
            const pixLink = document.getElementById('pixabayLink');
            const modalImg = document.getElementById('modalImg');

            modalImg.src = src;
            modal.style.display = 'flex';

            // Show external link only if it's an API image
            if (pixUrl) {
                pixLink.href = pixUrl;
                pixLink.style.display = 'inline-block';
            } else {
                pixLink.style.display = 'none';
            }
        }

        // Close modal when clicking anywhere on the background
        document.getElementById('imageModal').addEventListener('click', (e) => {
            if (e.button === 0) document.getElementById('imageModal').style.display = 'none';
        });

        // Close modal when clicking the external link (allows navigation to happen)
        document.getElementById('pixabayLink').addEventListener('click', (e) => {
            if (e.button === 0) document.getElementById('imageModal').style.display = 'none';
        });
    </script>

    <?php if ($isStandalone): ?>
    </body>

    </html>
<?php endif; ?>