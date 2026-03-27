<?php
/* photo_widget.php
 *
 * Photo Widget: Displays a grid of images for a city, combining user uploads
 * and Pixabay API photos, with pagination and caching support.
 */

// 1. Load configuration (API keys, DB connection, etc.)
$config = require_once 'config.php';

// 2. Widget settings
$api_key = PIXABAY_KEY;       // Pixabay API key from config
$per_page = 3;                // Number of photos to show per page
$cache_lifetime = 3600;       // Cache time in seconds for API photos (1 hour)

// --- Utility Functions ---

/**
 * Checks if a URL is valid and non-empty
 */
function is_usable_photo_url(string $url): bool
{
    return $url !== '' && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Determines the current page number for a city paginator.
 * Defaults to 1 if not set or invalid.
 */
function get_page_for_city(string $city_key): int
{
    $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
    return $page < 1 ? 1 : $page;
}

/**
 * Fetch user-uploaded photos from the local database for a specific city and page.
 */
function get_local_user_photos(PDO $pdo, int $city_id, int $page, int $per_page): array
{
    // ORDER BY photo_id DESC ensures the newest uploads appear first
    $stmt = $pdo->prepare("SELECT image_url FROM Photo WHERE city_id = ? AND caption = 'USER_UPLOAD' ORDER BY photo_id DESC LIMIT ? OFFSET ?");

    $offset = ($page - 1) * $per_page;
    $stmt->bindValue(1, $city_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

/**
 * Retrieve cached Pixabay photos for a city and page, filtered by TTL.
 */
function get_cached_api_photos(PDO $pdo, int $city_id, int $page, int $slots, int $cache_lifetime): array
{
    $cutoff = date('Y-m-d H:i:s', time() - $cache_lifetime);

    $stmt = $pdo->prepare('SELECT image_url, caption FROM Photo WHERE city_id=? AND page_num=? AND cached_at >= ? ORDER BY photo_id ASC LIMIT ?');
    $stmt->bindValue(1, (string)$city_id, PDO::PARAM_STR);
    $stmt->bindValue(2, $page, PDO::PARAM_INT);
    $stmt->bindValue(3, $cutoff, PDO::PARAM_STR);
    $stmt->bindValue(4, $slots, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $valid_rows = [];

    foreach ($rows as $row) {
        if (is_usable_photo_url($row['image_url'] ?? '')) {
            $valid_rows[] = $row;
        }
    }

    return ['photos' => array_slice($valid_rows, 0, $slots)];
}

/**
 * Fetch photos from Pixabay API and insert them into the local database.
 * Only fetches as many as needed to fill the remaining slots.
 */
function fetch_pixabay_photos(PDO $pdo, string $api_key, int $city_id, string $city_name, int $page, int $needed_count, int $per_page): array
{
    // Cleanup old cached API photos older than 1 hour
    $cache_lifetime = 3600;
    $cutoff = date('Y-m-d H:i:s', time() - $cache_lifetime);
    $cleanup = $pdo->prepare("DELETE FROM Photo WHERE caption != 'USER_UPLOAD' AND cached_at < ?");
    $cleanup->execute([$cutoff]);

    if ($needed_count <= 0) {
        return [];
    }

    // Build Pixabay API request URL
    $url = 'https://pixabay.com/api/?key=' . $api_key
        . '&q=' . urlencode($city_name)
        . '&page=' . $page
        . '&per_page=' . $per_page
        . '&image_type=photo&safesearch=true';

    $ctx = stream_context_create(['http' => ['timeout' => 15]]);
    $api_json = @file_get_contents($url, false, $ctx);
    $api = $api_json ? json_decode($api_json, true) : ['hits' => []];
    $hits = (isset($api['hits']) && is_array($api['hits'])) ? $api['hits'] : [];

    $insert = $pdo->prepare('INSERT INTO Photo (city_id, page_num, image_url, caption, cached_at) VALUES (?, ?, ?, ?, NOW())');
    $result = [];

    foreach ($hits as $photo) {
        $img_url = $photo['webformatURL'] ?? ($photo['previewURL'] ?? ($photo['largeImageURL'] ?? ''));
        if (!is_usable_photo_url($img_url)) {
            continue;
        }

        $caption = $photo['pageURL'] ?? '';
        $insert->execute([(string)$city_id, $page, $img_url, $caption]);
        $result[] = ['image_url' => $img_url, 'caption' => $caption];

        if (count($result) >= $needed_count) {
            break;
        }
    }

    return $result;
}

// --- Main flow ---

// Only include the current city if it's valid
$cities = [];
if (isset($currentCityId, $currentCityName) && (int)$currentCityId > 0 && (string)$currentCityName !== '') {
    $cities[(int)$currentCityId] = (string)$currentCityName;
}

$display_data = [];

// Process each city
foreach ($cities as $id => $city_name) {
    $city_key = strtolower($city_name);
    $page = get_page_for_city($city_key);

    // 1. Get local user-uploaded photos
    $display_user = get_local_user_photos($pdo, (int)$id, $page, $per_page);

    // Determine how many more photos are needed from API
    $slots = $per_page - count($display_user);
    $display_api = [];

    if ($slots > 0) {
        // 2. Try to get cached API photos first
        $cache = get_cached_api_photos($pdo, (int)$id, $page, $slots, $cache_lifetime);
        $display_api = $cache['photos'];

        // 3. Fetch missing photos from Pixabay if needed
        $missing = $slots - count($display_api);
        if ($missing > 0) {
            $fetched = fetch_pixabay_photos($pdo, $api_key, (int)$id, $city_name, $page, $missing, $per_page);
            $display_api = array_merge($display_api, $fetched);
        }
    }

    $display_data[$id] = [
        'name'  => $city_name,
        'key'   => $city_key,
        'page'  => $page,
        'user'  => $display_user,
        'api'   => $display_api
    ];
}
?>

<!-- --- HTML OUTPUT --- -->
<?php foreach ($display_data as $id => $data):
    $city_name = $data['name'];
    $page      = $data['page'];

    // --- PREVIOUS BUTTON ---
    $prev_params = $_GET;
    $prev_page = max(1, $page - 1);
    $prev_params['p'] = $prev_page;
    $prev_params['announce'] = "{$city_name}_pictures_page_{$prev_page}";
    $prev = "?" . http_build_query($prev_params) . "#photo-widget";
    $prev_class = ($page <= 1) ? "hidden" : "";

    // --- NEXT BUTTON ---
    $next_params = $_GET;
    $next_page = $page + 1;
    $next_params['p'] = $next_page;
    $next_params['announce'] = "{$city_name}_pictures_page_{$next_page}";
    $next = "?" . http_build_query($next_params) . "#photo-widget";
?>
    <div id="photo-widget" class="city-card photo-widget-card">

        <?php
        // Display a message if present (success/failure from photo upload)
        $msg = $_SESSION['upload_msg'] ?? $_GET['msg'] ?? null;
        unset($_SESSION['upload_msg']);
        ?>
        <?php if ($msg): ?>
            <div style="margin:10px; padding:10px; border-radius:4px; text-align:center; font-size:0.85em;
            <?= $msg === 'success' ? 'background:#e6f4ea; color:#1e7e34;' : 'background:#f8d7da; color:#721c24;' ?>">
                <?php
                if ($msg === 'too_big') echo "Rejected: File over 2MB.";
                elseif ($msg === 'wrong_type') echo "Rejected: Use JPG or PNG.";
                ?>
            </div>
        <?php endif; ?>

        <!-- Navigation -->
        <div class="nav">
            <a href="<?= htmlspecialchars($prev) ?>" class="btn <?= $prev_class ?>">Prev</a>
            <div class="city-header">
                <h2 class="city-title">
                    Pictures of <?= htmlspecialchars($data['name']) ?>
                    <!-- Upload form -->
                    <form action="" method="POST" enctype="multipart/form-data" style="display:inline;">
                        <label class="add-btn">+ Add<input type="file" name="new_photo" accept=".jpg,.jpeg,.png" onchange="this.form.submit()"></label>
                        <input type="hidden" name="city_id" value="<?= $id ?>">
                    </form>
                </h2>
                <span class="page-counter">Page <?= $page ?></span>
            </div>
            <a href="<?= htmlspecialchars($next) ?>" class="btn">Next</a>
        </div>

        <!-- Photo grid -->
        <div class="grid">
            <!-- User-uploaded photos -->
            <?php foreach ($data['user'] as $lp): ?>
                <div style="position:relative;">
                    <span class="user-badge">User</span>
                    <img src="<?= htmlspecialchars($lp) ?>" alt="User upload" loading="lazy">
                </div>
            <?php endforeach; ?>

            <!-- Pixabay API photos -->
            <?php foreach ($data['api'] as $img): ?>
                <a href="<?= htmlspecialchars($img['caption']) ?>" target="_blank" style="position:relative; display:block;">
                    <img src="<?= htmlspecialchars($img['image_url']) ?>" alt="Pixabay image" loading="lazy">
                </a>
            <?php endforeach; ?>

            <!-- Placeholder for missing photos -->
            <?php
            $total_shown = count($data['user']) + count($data['api']);
            if ($total_shown < 3):
            ?>
                <div class="photo-placeholder-card" style="grid-column: span <?= (3 - $total_shown) ?>; display:flex; align-items:center; justify-content:center; background:#f9f9f9; border:1px dashed #ccc; border-radius:8px; min-height:200px; padding:20px; text-align:center;">
                    <p style="margin:0; color:#666; font-size:0.9em;"><strong>Pixabay photos are currently unavailable <br>We're sorry about that!</strong></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>