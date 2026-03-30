<?php

// Handles URL validation and local DB fetching.
// Handles pagination math and cache cleanup.
// Handles API request building and connection stability.
// Handles JSON processing and URL fallback logic.
// Handles slot management and cache population.

// require_once: Loads config.php and halts with a fatal error if missing.
// once: Prevents redeclaration errors by ensuring the file is only loaded one time.
require_once 'config.php';

// is_usable_photo_url: Validates if a string is a non-empty web URL or a local user_pics/ path.
function is_usable_photo_url(string $url): bool {
    return $url !== '' && (filter_var($url, FILTER_VALIDATE_URL) !== false || strpos($url, 'user_pics/') === 0);
}

// get_local_user_photos: Fetches a paginated list of user-uploaded image URLs for a specific city.
// Logic: Calculates a row offset and executes a sanitised SQL query to prevent injection.
function get_local_user_photos($pdo, int $city_id, int $page, int $per_page): array {
    $offset = (int)(($page - 1) * $per_page);
    
    // Selecting image_url and filtering by USER_UPLOAD
    $sql = "SELECT image_url FROM Photo 
            WHERE city_id = :city_id 
            AND caption = 'USER_UPLOAD' 
            ORDER BY photo_id DESC 
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':city_id', (int)$city_id,  PDO::PARAM_INT);
    $stmt->bindValue(':limit',   (int)$per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset',  (int)$offset,   PDO::PARAM_INT);
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

// get_cached_api_photos: Retrieves valid, non-expired API images from the local cache.
// Logic: Uses a $cutoff timestamp to filter out any photos older than the defined $cache_lifetime.
function get_cached_api_photos($pdo, int $city_id, int $page, int $slots, int $cache_lifetime): array {
    $cutoff = date('Y-m-d H:i:s', time() - $cache_lifetime);
    
    $sql = "SELECT image_url, caption FROM Photo 
            WHERE city_id = :city_id 
            AND page_num = :page 
            AND cached_at >= :cutoff 
            AND caption != 'USER_UPLOAD' 
            ORDER BY photo_id ASC 
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':city_id', (int)$city_id, PDO::PARAM_INT);
    $stmt->bindValue(':page',    (int)$page,    PDO::PARAM_INT);
    $stmt->bindValue(':cutoff',  $cutoff,       PDO::PARAM_STR);
    $stmt->bindValue(':limit',   (int)$slots,   PDO::PARAM_INT);
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// fetch_pixabay_photos: Clears expired cache and pulls fresh images from Pixabay API.
// Cleanup: Deletes non-user photos older than one hour to keep the database light.
// API Request: Builds a secure, filtered URL and fetches JSON with a 15-second timeout.
// Error Handling: Throws exceptions if the connection fails or if the JSON is corrupted.
// Fallback Logic: If best photo size (webformatURL) is missing, it tries smaller or larger alternatives before giving up on that entry.
// Cache & Limit: Saves new results to the DB and stops once the $needed_count is met.
function fetch_pixabay_photos($pdo, string $api_key, int $city_id, string $city_name, int $page, int $needed_count, int $per_page): array {
    
    // 1. Cleanup old cached API photos (1 hour TTL)
    $cleanup_cutoff = date('Y-m-d H:i:s', time() - 3600);
    $cleanup = $pdo->prepare("DELETE FROM Photo WHERE caption != 'USER_UPLOAD' AND cached_at < ?");
    $cleanup->execute([$cleanup_cutoff]);

    if ($needed_count <= 0) {
        return [];
    }

    // 2. Build Pixabay API request URL
    $url = 'https://pixabay.com/api/?key=' . $api_key . 
           '&q=' . urlencode($city_name) . 
           '&page=' . $page . 
           '&per_page=' . $per_page . 
           '&image_type=photo&safesearch=true';
    
    // 3. 15-second timeout for stability - Replaced @ with Exception throwing
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
    $api_json = file_get_contents($url, false, $ctx);
    
    if ($api_json === false) {
        throw new Exception("Failed to connect to Pixabay API. Please check your internet connection.");
    }
    
    $api = json_decode($api_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Received invalid JSON response from Pixabay.");
    }

    $hits = (isset($api['hits']) && is_array($api['hits'])) ? $api['hits'] : [];
    
    $insert = $pdo->prepare('INSERT INTO Photo (city_id, page_num, image_url, caption, cached_at) VALUES (?, ?, ?, ?, NOW())');
    
    $result = [];
    foreach ($hits as $photo) {
        // 4. URL Fallback logic (Webformat -> Preview -> Large)
        $img_url = $photo['webformatURL'] ?? ($photo['previewURL'] ?? ($photo['largeImageURL'] ?? ''));
        
        if (is_usable_photo_url($img_url)) {
            $caption = $photo['pageURL'] ?? '';
            
            // Insert into DB cache using image_url column
            $insert->execute([(int)$city_id, (int)$page, $img_url, $caption]);
            
            // Add to current results array
            $result[] = ['image_url' => $img_url, 'caption' => $caption];
            
            // Stop once we have filled the requested slots
            if (count($result) >= $needed_count) {
                break;
            }
        }
    }
    return $result;
}