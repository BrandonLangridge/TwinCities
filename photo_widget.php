<?php

// photo_widget.php
// Serves as both a standalone page and a modular widget using environment check to see if standalone or included
// Upload Handler: Validates file size/type and saves local path to the database.
// Post-Redirect-Get: Redirects after POST to prevent resubmission and resets to page 1.

// Session Management: Checks if a session is already active and if not it starts one to store upload messages for user feedback.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Environment Detection: Checks if $pdo exists to determine if the file is running as a standalone page or an included widget.
// Setup Logic: If standalone, it manually loads configurations and resolves the city_id from the URL parameters.
// Standalone Flag: Sets $isStandalone to control whether full HTML headers and footers are rendered.

// if pdo is not set, we are running this file directly
if (!isset($pdo)) {

    // require_once: Loads config.php and halts with a fatal error if missing.
    // once: Prevents redeclaration errors by ensuring the file is only loaded one time.
    require_once 'config.php';
    require_once 'photo_logic.php';


    // Default city data for the standalone switcher UI
    $cities = [
        "Liverpool" => ["image" => "app_images/liverpool.jpg"],
        "Cologne"   => ["image" => "app_images/cologne.jpg"]
    ];

    // Determine current city from URL. If none provided, default to Liverpool.
    $currentCityName = isset($_GET['city']) ? ucfirst($_GET['city']) : 'Liverpool';

    // Lookup the internal ID for the current city to query the database correctly
    $stmt = $pdo->prepare("SELECT city_id FROM City WHERE name = ? LIMIT 1");
    $stmt->execute([$currentCityName]);
    $currentCityId = $stmt->fetchColumn() ?: 1;

    $isStandalone = true;
} else {
    // Variable was already set by a parent file (index.php)
    $isStandalone = false;
}

// require these if running standalone 
require_once 'config.php';
require_once 'photo_logic.php';

// Upload handling: Checks for a POST request containing a file.
// Validation: Verifies the file is under 2MB and is a JPEG or PNG.
// Storage: Creates the user_pics/ folder and saves the file with a unique timestamped name.
// Database: Records the new image path and its city_id in the Photo table.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['new_photo'])) {
    $upload_city_id = (int)($_POST['city_id'] ?? $currentCityId);
    $file = $_FILES['new_photo'];

    
    if ($file['size'] > 2 * 1024 * 1024) {
        $_SESSION['upload_msg'] = 'too_big';
    }
    
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
    // Sends the browser to the updated URL with the new query parameters and anchors to the photo widget section.
    header("Location: " . ($url_parts['path'] ?? 'index.php') . '?' . http_build_query($query) . '#photo-widget');
    exit;
}

// Sets a 3-image limit per page and sanitises the current page number from the URL.
$per_page = 3;
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;

// Fetch photos uploaded by users from the local DB
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

// Identifies the current file and calculates values for the city switcher and pagination links.
$current_file = basename($_SERVER['PHP_SELF']);
$otherCityName = (strtolower($currentCityName) === 'liverpool') ? 'Cologne' : 'Liverpool';
$prev_page = max(1, $page - 1);
$next_page = $page + 1;

// Define "announce" strings for screen readers (handled by the parent container)
$ann_p = !$isStandalone ? "&announce=" . $currentCityName . "_pictures_page_" . $prev_page : "";
$ann_n = !$isStandalone ? "&announce=" . $currentCityName . "_pictures_page_" . $next_page : "";
$ann_s = !$isStandalone ? "&announce=Switching_to_" . $otherCityName : "";

// Generate URLs for navigation buttons
$prev_url = "?city=" . strtolower($currentCityName) . "&p=" . $prev_page . $ann_p . "#photo-widget";
$next_url = "?city=" . strtolower($currentCityName) . "&p=" . $next_page . $ann_n . "#photo-widget";
$switch_url = $current_file . "?city=" . strtolower($otherCityName) . "&p=1" . $ann_s . "#photo-widget";
?>
<!-- if not imported by another page then display the widget itself -->
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
        // Fetches errors from the session abd immediately deletes it to prevent re-display then renders the specific alert to the user.
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
        // Displays modal for bigger images, toggling the Pixabay link based on the image source.
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