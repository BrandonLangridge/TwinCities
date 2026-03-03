<?php
/*
    rss_view.php
    This is the human-readable version of the RSS feed — a styled HTML and CSS page
    that shows the same data as rss_feed.php but in a format the user can
    actually browse. It's what opens when you click "View RSS Feed" on the homepage.
    All data is fetched fresh from the database on every page load.
*/

// config.php gives us the $pdo database connection and the $config settings array.
// We only need max_items from config here — the rest is handled in rss_feed.php.
$config = require_once __DIR__ . '/config.php';

$rssMaxItems = (int) $config['rss']['max_items'];

// Fetch all cities sorted alphabetically. We use fetchAll with FETCH_ASSOC
// so each row comes back as a named array rather than a numbered one.
$cities = $pdo->query("
    SELECT city_id, name, country, population, latitude, longitude, currency, description
    FROM   Cities ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all places of interest joined with their city.
// The JOIN is needed because Place_of_Interest only stores a city_id —
// we need the city name and country to display alongside each POI.
$pois = $pdo->query("
    SELECT p.poi_id, p.name, p.type, p.capacity, p.latitude, p.longitude,
           p.description, c.name AS city_name, c.country
    FROM   Place_of_Interest p
    JOIN   Cities c ON c.city_id = p.city_id
    ORDER  BY c.name ASC, p.name ASC
    LIMIT  {$rssMaxItems}
")->fetchAll(PDO::FETCH_ASSOC);

// The News table is optional, so we wrap this in a try/catch.
// If the table doesn't exist yet, $news just stays as an empty array
// and the page still loads fine — it shows a placeholder message instead.
$news = [];
try {
    $news = $pdo->query("
        SELECT n.news_id, n.headline, n.body, n.published_at, c.name AS city_name
        FROM   News n
        JOIN   Cities c ON c.city_id = n.city_id
        ORDER  BY n.published_at DESC
        LIMIT  {$rssMaxItems}
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* News table not created yet — skip quietly */ }

// This lookup maps each POI type to a Font Awesome icon class.
// When we loop through the POIs below, we use $typeIcons[$poi['type']]
// to pick the right icon automatically based on what type the POI is.
// The fallback at the bottom of the array handles any unknown types.
$typeIcons = [
    "Museum"            => "fa-solid fa-building-columns",
    "Religious Site"    => "fa-solid fa-place-of-worship",
    "Historic Building" => "fa-solid fa-landmark",
    "Art Gallery"       => "fa-solid fa-palette",
    "Sports Venue"      => "fa-solid fa-futbol",
    "Park"              => "fa-solid fa-tree",
    "Landmark"          => "fa-solid fa-monument",
    "Zoo"               => "fa-solid fa-paw",
];
?>


<!-- DESIGN -->

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RSS Feed Viewer | Twin Cities</title>

  <!-- Our main stylesheet — keeps the buttons, colours and typography consistent
       with the rest of the app (maps.php, index.php etc.) -->
  <link rel="stylesheet" href="styles.css">

  <!-- Tailwind CSS via CDN for layout and spacing utilities.
       We use it alongside styles.css rather than replacing it,
       so existing classes like .toggle-button still work. -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Font Awesome for the icons next to each section and item -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- We extend Tailwind with our brand blue so we can use text-brand
       in the template and have it match the --accent colour in styles.css -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { brand: '#0b5fff' }
        }
      }
    }
  </script>

  <style>
    /* Card styles for each feed item — rounded border with a hover shadow */
    .rss-item           { background:#fff; border:1px solid var(--border); border-radius:12px; padding:18px 22px; margin-bottom:14px; transition:box-shadow .2s; }
    .rss-item:hover     { box-shadow:0 4px 14px rgba(0,0,0,.10); }
    .rss-item h3        { margin:0 0 6px; font-size:1rem; color:var(--accent); }
    .rss-item p         { margin:0 0 8px; color:#475569; font-size:.9rem; line-height:1.6; }

    /* Small coloured pill label shown at the bottom of each card */
    .rss-badge          { display:inline-block; padding:2px 10px; border-radius:6px; background:#eff6ff; color:var(--accent); font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; }

    /* Muted grey text used for coordinates and dates */
    .rss-meta           { font-size:.78rem; color:#94a3b8; margin-top:6px; }

    /* The orange "View Raw XML" button */
    .raw-link           { font-size:.82rem; color:#fff; background:#e05d1e; padding:4px 12px; border-radius:6px; text-decoration:none; font-weight:600; }
    .raw-link:hover     { background:#c04c10; }

    /* Shown when a section has no data to display */
    .empty-note         { color:#94a3b8; font-style:italic; font-size:.9rem; }

    /* Section heading with an underline in the brand colour */
    .rss-section-title  { font-size:1.1rem; font-weight:700; color:var(--accent); padding-bottom:6px; border-bottom:2px solid var(--accent); }
  </style>
</head>

<body>

  <!-- Outer wrapper — max-w-3xl centres the content and px-4 adds
       side padding so it doesn't touch the screen edges on mobile -->
  <div class="max-w-3xl mx-auto px-4 pt-10 pb-16">

    <!-- PAGE HEADER
         On desktop this is a single row: title on the left, buttons on the right.
         On mobile (flex-col) the title stacks above the buttons so nothing overflows. -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">

      <div>
        <h1 class="text-2xl font-bold flex items-center gap-2" style="color:var(--text);">
          <i class="fa-solid fa-satellite-dish text-brand"></i>
          RSS Feed Viewer
        </h1>
        <p class="mt-1 text-sm text-gray-500">
          Live data pulled from the <strong>city_twin_db</strong> MySQL database
        </p>
      </div>

      <!-- flex-wrap here means if the screen is very narrow, the two buttons
           wrap onto a second line rather than getting cut off -->
      <div class="flex flex-wrap gap-3 items-center">
        <!-- Relative path — works regardless of the base_url set in config.php -->
        <a href="rss_feed.php" class="raw-link" target="_blank">
          <i class="fa-solid fa-code mr-1"></i>View Raw XML
        </a>
        <button onclick="history.back()" class="toggle-button" style="height:34px; font-size:13px;">
          <i class="fa-solid fa-arrow-left mr-2"></i>Back
        </button>
      </div>

    </div>


    <!-- ===================================================
         SECTION 1 — CITIES
         Loops through every city row returned from the database.
         If the table is empty for some reason, we show a short message.
    =================================================== -->
    <div class="rss-section-title flex items-center gap-2 mb-4 mt-8">
      <i class="fa-solid fa-city"></i> Cities
    </div>

    <?php if (empty($cities)): ?>
      <p class="empty-note">No cities found in the database.</p>
    <?php endif; ?>

    <?php foreach ($cities as $city): ?>
      <div class="rss-item">

        <h3 class="flex items-center gap-2">
          <i class="fa-solid fa-globe text-brand text-sm"></i>
          <?= htmlspecialchars($city['name']) ?>, <?= htmlspecialchars($city['country']) ?>
        </h3>

        <!-- Only show the description paragraph if one is stored in the database -->
        <?php if (!empty($city['description'])): ?>
          <p><?= htmlspecialchars($city['description']) ?></p>
        <?php endif; ?>

        <!-- Key facts row. On desktop these sit in a line separated by dots.
             On mobile they stack vertically — the dot separators are hidden
             using Tailwind's hidden sm:inline pattern. -->
        <div class="flex flex-col sm:flex-row sm:flex-wrap gap-1 sm:gap-0 text-sm text-gray-500 mb-3">
          <span>
            <i class="fa-solid fa-people-group mr-1 text-brand"></i>
            <strong>Population:</strong> <?= number_format((int)$city['population']) ?>
          </span>
          <span class="hidden sm:inline mx-2 text-gray-300">&bull;</span>
          <span>
            <i class="fa-solid fa-coins mr-1 text-brand"></i>
            <strong>Currency:</strong> <?= htmlspecialchars($city['currency']) ?>
          </span>
          <span class="hidden sm:inline mx-2 text-gray-300">&bull;</span>
          <span>
            <i class="fa-solid fa-compass mr-1 text-brand"></i>
            <strong>Coordinates:</strong>
            <?= htmlspecialchars($city['latitude']) ?>, <?= htmlspecialchars($city['longitude']) ?>
          </span>
        </div>

        <span class="rss-badge">
          <i class="fa-solid fa-city mr-1"></i>City
        </span>

      </div>
    <?php endforeach; ?>


    <!-- ===================================================
         SECTION 2 — PLACES OF INTEREST
         For each POI we look up its icon from $typeIcons at the top.
         If the type isn't in our list, we fall back to a generic pin icon.
    =================================================== -->
    <div class="rss-section-title flex items-center gap-2 mb-4 mt-8">
      <i class="fa-solid fa-map-pin"></i> Places of Interest
    </div>

    <?php if (empty($pois)): ?>
      <p class="empty-note">
        No places of interest found in the database. Run <code>seed_pois.sql</code>
        in phpMyAdmin to populate this section.
      </p>
    <?php endif; ?>

    <?php foreach ($pois as $poi):
      // Pick the icon for this POI type, defaulting to a location dot if unknown
      $poiIcon = $typeIcons[$poi['type']] ?? "fa-solid fa-location-dot";
    ?>
      <div class="rss-item">

        <h3 class="flex items-center gap-2">
          <i class="<?= $poiIcon ?> text-brand text-sm"></i>
          <!-- Clicking the name links through to the place detail page -->
          <a href="place.php?poi_id=<?= (int)$poi['poi_id'] ?>"
             style="color:inherit; text-decoration:none;">
            <?= htmlspecialchars($poi['name']) ?>
          </a>
        </h3>

        <?php if (!empty($poi['description'])): ?>
          <p><?= htmlspecialchars($poi['description']) ?></p>
        <?php endif; ?>

        <!-- Location and capacity row — same responsive stacking as the city section.
             Capacity is only shown if a value exists in the database for this POI. -->
        <div class="flex flex-col sm:flex-row sm:flex-wrap gap-1 sm:gap-0 text-sm text-gray-500 mb-3">
          <span>
            <i class="fa-solid fa-city mr-1 text-brand"></i>
            <strong>Location:</strong>
            <?= htmlspecialchars($poi['city_name']) ?>, <?= htmlspecialchars($poi['country']) ?>
          </span>
          <?php if (!empty($poi['capacity'])): ?>
            <span class="hidden sm:inline mx-2 text-gray-300">&bull;</span>
            <span>
              <i class="fa-solid fa-users mr-1 text-brand"></i>
              <strong>Capacity:</strong> <?= number_format((int)$poi['capacity']) ?>
            </span>
          <?php endif; ?>
        </div>

        <!-- Coordinates displayed in smaller muted text below the main details -->
        <div class="rss-meta">
          <i class="fa-solid fa-location-crosshairs mr-1"></i>
          <?= htmlspecialchars($poi['latitude']) ?>, <?= htmlspecialchars($poi['longitude']) ?>
        </div>

        <!-- The badge reuses the same icon as the heading for consistency -->
        <div class="mt-3">
          <span class="rss-badge">
            <i class="<?= $poiIcon ?> mr-1"></i>
            <?= htmlspecialchars($poi['type']) ?>
          </span>
        </div>

      </div>
    <?php endforeach; ?>


    <!-- ===================================================
         SECTION 3 — NEWS
         If $news is empty (either no rows or no table yet),
         we show a placeholder message. Otherwise we loop and
         format the published_at date for display.
    =================================================== -->
    <div class="rss-section-title flex items-center gap-2 mb-4 mt-8">
      <i class="fa-solid fa-newspaper"></i> News
    </div>

    <?php if (empty($news)): ?>
      <p class="empty-note">
        No news items found. Run <code>news_table.sql</code> in phpMyAdmin
        to create the News table and add sample items.
      </p>
    <?php else: ?>
      <?php foreach ($news as $item): ?>
        <div class="rss-item">

          <h3 class="flex items-center gap-2">
            <i class="fa-solid fa-newspaper text-brand text-sm"></i>
            <?= htmlspecialchars($item['headline']) ?>
          </h3>

          <p><?= htmlspecialchars($item['body']) ?></p>

          <!-- Date and city shown together in muted text at the bottom of the card -->
          <div class="rss-meta flex flex-wrap gap-3">
            <span>
              <i class="fa-regular fa-calendar mr-1"></i>
              <!-- strtotime converts the MySQL datetime so date() can format it nicely -->
              <?= htmlspecialchars(date("j M Y", strtotime($item['published_at']))) ?>
            </span>
            <span>
              <i class="fa-solid fa-city mr-1"></i>
              <?= htmlspecialchars($item['city_name']) ?>
            </span>
          </div>

          <div class="mt-3">
            <span class="rss-badge">
              <i class="fa-solid fa-newspaper mr-1"></i>News
            </span>
          </div>

        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>

</body>
</html>