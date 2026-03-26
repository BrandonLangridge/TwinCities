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

// Minimal city filter: if `?city=` is provided, use it to restrict results.
$filterCity = null;
if (!empty($_GET['city'])) {
  $filterCity = trim((string)$_GET['city']);
}

// Fetch all cities sorted alphabetically. We use fetchAll with FETCH_ASSOC
// so each row comes back as a named array rather than a numbered one.
$cities = [];
if ($filterCity) {
  $stmt = $pdo->prepare("SELECT city_id, name, country, population, latitude, longitude, currency, description FROM Cities WHERE LOWER(name) = LOWER(?) ORDER BY name ASC");
  $stmt->execute([$filterCity]);
  $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
  $cities = $pdo->query("
    SELECT city_id, name, country, population, latitude, longitude, currency, description
    FROM   Cities ORDER BY name ASC
  ")->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all places of interest joined with their city.
// The JOIN is needed because Place_of_Interest only stores a city_id —
// we need the city name and country to display alongside each POI.
// The LIMIT comes from config so we don't accidentally dump too many rows.
$pois = [];
if ($filterCity) {
  $stmt = $pdo->prepare("SELECT p.poi_id, p.name, p.type, p.capacity, p.latitude, p.longitude, p.description, c.name AS city_name, c.country FROM Place_of_Interest p JOIN Cities c ON c.city_id = p.city_id WHERE LOWER(c.name) = LOWER(?) ORDER BY p.name ASC LIMIT ?");
  $stmt->bindValue(1, $filterCity, PDO::PARAM_STR);
  $stmt->bindValue(2, (int)$rssMaxItems, PDO::PARAM_INT);
  $stmt->execute();
  $pois = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
  $pois = $pdo->query("
    SELECT p.poi_id, p.name, p.type, p.capacity, p.latitude, p.longitude,
           p.description, c.name AS city_name, c.country
    FROM   Place_of_Interest p
    JOIN   Cities c ON c.city_id = p.city_id
    ORDER  BY c.name ASC, p.name ASC
    LIMIT  {$rssMaxItems}
  ")->fetchAll(PDO::FETCH_ASSOC);
}

// The News table is optional, so we wrap this in a try/catch.
// If the table doesn't exist yet, $news just stays as an empty array
// and the page still loads fine — it shows a placeholder message instead.
$news = [];
try {
  if ($filterCity) {
    $stmt = $pdo->prepare("SELECT n.news_id, n.headline, n.body, n.published_at, c.name AS city_name FROM News n JOIN Cities c ON c.city_id = n.city_id WHERE LOWER(c.name) = LOWER(?) ORDER BY n.published_at DESC LIMIT ?");
    $stmt->bindValue(1, $filterCity, PDO::PARAM_STR);
    $stmt->bindValue(2, (int)$rssMaxItems, PDO::PARAM_INT);
    $stmt->execute();
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $news = $pdo->query("
      SELECT n.news_id, n.headline, n.body, n.published_at, c.name AS city_name
      FROM   News n
      JOIN   Cities c ON c.city_id = n.city_id
      ORDER  BY n.published_at DESC
      LIMIT  {$rssMaxItems}
    ")->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (PDOException $e) { /* News table not created yet — skip quietly */ }

// This lookup maps each POI type to a Font Awesome icon class.
// When we loop through the POIs below, we use $typeIcons[$poi['type']]
// to pick the right icon automatically based on what type the POI is.
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

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>RSS Feed Viewer | Twin Cities</title>

  <link rel="stylesheet" href="styles.css">

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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

  <div class="max-w-3xl mx-auto px-4 pt-10 pb-16">

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

      <div class="flex flex-wrap gap-3 items-center">
        <a href="rss_feed.php" class="raw-link" onclick="showRawXmlInPanel(event)">
          <i class="fa-solid fa-code mr-1"></i>View Raw XML For Both Cities
        </a>
      </div>

    </div>


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

        <?php if (!empty($city['description'])): ?>
          <p><?= htmlspecialchars($city['description']) ?></p>
        <?php endif; ?>

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
          <a href="place.php?poi_id=<?= (int)$poi['poi_id'] ?>"
              style="color:inherit; text-decoration:none;">
            <?= htmlspecialchars($poi['name']) ?>
          </a>
        </h3>

        <?php if (!empty($poi['description'])): ?>
          <p><?= htmlspecialchars($poi['description']) ?></p>
        <?php endif; ?>

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

        <div class="rss-meta">
          <i class="fa-solid fa-location-crosshairs mr-1"></i>
          <?= htmlspecialchars($poi['latitude']) ?>, <?= htmlspecialchars($poi['longitude']) ?>
        </div>

        <div class="mt-3">
          <span class="rss-badge">
            <i class="<?= $poiIcon ?> mr-1"></i>
            <?= htmlspecialchars($poi['type']) ?>
          </span>
        </div>

      </div>
    <?php endforeach; ?>


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

          <div class="rss-meta flex flex-wrap gap-3">
            <span>
              <i class="fa-regular fa-calendar mr-1"></i>
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

  <script>
    // Speak a message via screen reader / speech synthesis
    function speak(text) {
      try { console.log('SPEAK:', text); } catch (e) {}
      const ariaLiveEl = document.querySelector('[aria-live="polite"]');
      if (ariaLiveEl) ariaLiveEl.textContent = text;
      try {
        if (!localStorage || localStorage.getItem('voiceEnabled') !== 'true' || !window.speechSynthesis) return;
      } catch (e) { return; }
      window.speechSynthesis.cancel();
      const msg = new SpeechSynthesisUtterance(text);
      window.speechSynthesis.speak(msg);
    }

    function goBackToViewer() {
      const url = window.location.pathname + window.location.search + (window.location.search ? '&' : '?') + 'announce=rss_back';
      window.location.href = url;
    }

    // Show raw XML feed inside the page
    function showRawXmlInPanel(event) {
      if (event) event.preventDefault();
      const wrapper = document.querySelector('.max-w-3xl');
      if (!wrapper) return;

      fetch('rss_feed.php' + window.location.search, { cache: 'no-store' })
        .then((response) => response.text())
        .then((xmlText) => {
          wrapper.innerHTML = `
            <div class="flex flex-wrap gap-3 items-center mb-4">
              <button type="button" class="toggle-button" onclick="goBackToViewer()">
                  <i class="fa-solid fa-arrow-left mr-2"></i>Back to RSS Viewer
              </button>
            </div>
            <pre style="background:#f8fafc; padding:20px; border-radius:8px; border:1px solid #e2e8f0; overflow-x:auto; font-size:12px;"></pre>
          `;

          const output = wrapper.querySelector('pre');
          if (output) {
            output.textContent = xmlText;
            speak('Viewing raw XML');
          }
        })
        .catch(() => {
          wrapper.innerHTML = `
            <p>Could not load raw XML feed in-panel.</p>
            <button type="button" class="toggle-button" onclick="goBackToViewer()">Back to RSS Viewer</button>
          `;
        });
    }

    // Read the announce parameter from URL and speak a phrase if present
    (function() {
      const params = new URLSearchParams(window.location.search);
      const ann = params.get('announce');
      if (!ann) return;
      const phrases = { 'rss_back': 'Back to RSS Viewer' };
      if (phrases[ann]) speak(phrases[ann]);
      params.delete('announce');
      const newUrl = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
      history.replaceState(null, '', newUrl);
    })();
  </script>

</body>
</html>