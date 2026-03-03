<?php
/*
    rss_feed.php
    This file generates the actual RSS 2.0 XML feed for the Twin Cities app.
    When you open it in a browser or feed reader, it outputs raw XML — not HTML.
    All the data it serves comes live from the MySQL database, so it always
    reflects whatever is currently stored in the Cities, Place_of_Interest,
    and News tables.
*/

// We pull in config.php first because it sets up two things we need:
// the $pdo database connection, and the $config settings array which
// holds our RSS title, base URL, and item limit.
$config = require_once __DIR__ . '/config.php';

// Read the RSS-specific settings from the config array.
// Keeping these in config.php means if we ever need to change the site title
// or base URL, we only have to do it in one place across the whole app.
$rssTitle       = $config['rss']['title'];
$rssDescription = $config['rss']['description'];
$rssBaseUrl     = rtrim($config['rss']['base_url'], '/');
$rssMaxItems    = (int) $config['rss']['max_items'];

// XML requires certain characters to be escaped — for example, & must become &amp;
// and < must become &lt;. This helper function handles that automatically
// whenever we output a PHP variable into the XML.
function xmlEscape(string $text): string {
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// RSS requires dates to follow the RFC 2822 format, e.g. "Mon, 01 Jan 2025 12:00:00 +0000".
// MySQL stores dates differently, so this helper converts them.
// If no date is provided at all, it falls back to the current date and time.
function rssDate(?string $mysqlDate): string {
    if (!$mysqlDate) return date(DATE_RSS);
    return date(DATE_RSS, strtotime($mysqlDate));
}

// ---------------------------------------------------------------------------
// DATABASE QUERIES
// We run all three queries up front before any output starts.
// This is important because once we start sending the XML header,
// we can't go back and change it if a database error occurs halfway through.
// ---------------------------------------------------------------------------

try {

    // Fetch every city from the database, sorted alphabetically.
    $stmtCities = $pdo->query("
        SELECT city_id, name, country, population, latitude, longitude,
               currency, description
        FROM   Cities
        ORDER  BY name ASC
    ");
    $cities = $stmtCities->fetchAll(PDO::FETCH_ASSOC);

    // Fetch places of interest joined with their parent city.
    // We need the JOIN here because the Place_of_Interest table only stores
    // a city_id — we need the actual city name and country for the feed output.
    // The LIMIT comes from config so we don't accidentally dump too many rows.
    $stmtPOIs = $pdo->query("
        SELECT p.poi_id, p.name, p.type, p.capacity,
               p.latitude, p.longitude, p.description,
               c.name AS city_name, c.country
        FROM   Place_of_Interest p
        JOIN   Cities c ON c.city_id = p.city_id
        ORDER  BY c.name ASC, p.name ASC
        LIMIT  {$rssMaxItems}
    ");
    $pois = $stmtPOIs->fetchAll(PDO::FETCH_ASSOC);

    // The News table is optional — it won't exist in every deployment.
    // We wrap this query in its own try/catch so that if the table is missing,
    // we just get an empty array and the rest of the feed still works fine.
    $news = [];
    try {
        $stmtNews = $pdo->query("
            SELECT n.news_id, n.headline, n.body, n.published_at,
                   c.name AS city_name
            FROM   News n
            JOIN   Cities c ON c.city_id = n.city_id
            ORDER  BY n.published_at DESC
            LIMIT  {$rssMaxItems}
        ");
        $news = $stmtNews->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $news = [];
    }

} catch (PDOException $e) {
    // If the main database queries fail, we still want to output valid XML
    // rather than a broken page. This outputs a minimal well-formed RSS feed
    // containing the error message so we can diagnose what went wrong.
    header('Content-Type: application/rss+xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<rss version="2.0"><channel>';
    echo '<title>Error</title><link>' . $rssBaseUrl . '</link>';
    echo '<description>Database error: ' . xmlEscape($e->getMessage()) . '</description>';
    echo '</channel></rss>';
    exit;
}

// ---------------------------------------------------------------------------
// OUTPUT THE FEED
// Now that all the data is ready and no errors occurred, we can safely
// set the Content-Type header and start writing the XML.
// The Content-Type tells the browser (or feed reader) that this is RSS,
// not a normal HTML page — that's what makes it render as raw XML.
// ---------------------------------------------------------------------------

header('Content-Type: application/rss+xml; charset=utf-8');

// We use echo for the XML declaration because PHP's opening <?php tag would
// conflict with <?xml if we put it directly in the template below.
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0"
     xmlns:geo="http://www.w3.org/2003/01/geo/wgs84_pos#"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:atom="http://www.w3.org/2005/Atom">

  <!--
    The three xmlns attributes extend the standard RSS format with extra namespaces:
    - geo  lets us include latitude/longitude on each item
    - dc   gives us <dc:subject> for labelling item categories
    - atom lets us add a self-referencing link, which RSS validators expect
  -->

  <channel>

    <!-- These three tags are required by the RSS 2.0 spec for every feed -->
    <title><?= xmlEscape($rssTitle) ?></title>
    <link><?= xmlEscape($rssBaseUrl . '/index.php') ?></link>
    <description><?= xmlEscape($rssDescription) ?></description>

    <language>en-gb</language>
    <lastBuildDate><?= date(DATE_RSS) ?></lastBuildDate>
    <generator>Twin Cities PHP RSS Generator</generator>

    <!-- The atom:link self-reference tells feed readers the URL of this feed.
         RSS validators check for this so we include it as best practice. -->
    <atom:link href="<?= xmlEscape($rssBaseUrl . '/rss_feed.php') ?>"
               rel="self" type="application/rss+xml" />

    <!-- =======================================================
         SECTION 1 — CITIES
         One <item> per city row in the database.
         The link on each item points to that city's map page.
    ======================================================= -->
    <?php foreach ($cities as $city): ?>

    <item>
      <title>City: <?= xmlEscape($city['name']) ?>, <?= xmlEscape($city['country']) ?></title>

      <!-- The link goes to maps.php for this city, same as clicking it on the homepage -->
      <link><?= xmlEscape($rssBaseUrl . '/maps.php?city=' . strtolower($city['name'])) ?></link>

      <!-- guid is a unique identifier for this item in the feed.
           We use the URL and set isPermaLink="true" so feed readers know they can visit it. -->
      <guid isPermaLink="true"><?= xmlEscape($rssBaseUrl . '/maps.php?city=' . strtolower($city['name'])) ?></guid>

      <!-- CDATA lets us include HTML markup inside the description without escaping
           every tag manually — the feed reader renders it as formatted content. -->
      <description><![CDATA[
        <p><strong><?= htmlspecialchars($city['name']) ?></strong> is located in
        <?= htmlspecialchars($city['country']) ?>.</p>
        <ul>
          <li><strong>Population:</strong> <?= number_format((int)$city['population']) ?></li>
          <li><strong>Currency:</strong> <?= htmlspecialchars($city['currency']) ?></li>
          <li><strong>Coordinates:</strong> <?= htmlspecialchars($city['latitude']) ?>,
              <?= htmlspecialchars($city['longitude']) ?></li>
        </ul>
        <?php if (!empty($city['description'])): ?>
          <p><?= htmlspecialchars($city['description']) ?></p>
        <?php endif; ?>
      ]]></description>

      <dc:subject>City</dc:subject>

      <!-- Geo coordinates pulled straight from the Cities table -->
      <geo:lat><?= xmlEscape($city['latitude']) ?></geo:lat>
      <geo:long><?= xmlEscape($city['longitude']) ?></geo:long>
    </item>

    <?php endforeach; ?>

    <!-- =======================================================
         SECTION 2 — PLACES OF INTEREST
         One <item> per POI. The link points to place.php with the
         poi_id so users can open the full detail page directly.
    ======================================================= -->
    <?php foreach ($pois as $poi): ?>

    <item>
      <!-- We include the type and city in the title so items are easy
           to tell apart when they appear in a feed reader list view. -->
      <title><?= xmlEscape($poi['name']) ?> (<?= xmlEscape($poi['type']) ?>) – <?= xmlEscape($poi['city_name']) ?></title>

      <link><?= xmlEscape($rssBaseUrl . '/place.php?poi_id=' . (int)$poi['poi_id']) ?></link>
      <guid isPermaLink="true"><?= xmlEscape($rssBaseUrl . '/place.php?poi_id=' . (int)$poi['poi_id']) ?></guid>

      <description><![CDATA[
        <p><strong><?= htmlspecialchars($poi['name']) ?></strong> is a
        <?= htmlspecialchars($poi['type']) ?> located in
        <?= htmlspecialchars($poi['city_name']) ?>, <?= htmlspecialchars($poi['country']) ?>.</p>

        <?php if (!empty($poi['capacity'])): ?>
          <p><strong>Capacity:</strong> <?= number_format((int)$poi['capacity']) ?></p>
        <?php endif; ?>

        <?php if (!empty($poi['description'])): ?>
          <p><?= htmlspecialchars($poi['description']) ?></p>
        <?php endif; ?>

        <p><em>Coordinates: <?= htmlspecialchars($poi['latitude']) ?>,
           <?= htmlspecialchars($poi['longitude']) ?></em></p>
      ]]></description>

      <!-- category tags the item with its POI type, e.g. "Museum" or "Park" -->
      <category><?= xmlEscape($poi['type']) ?></category>
      <dc:subject><?= xmlEscape($poi['city_name']) ?></dc:subject>
      <geo:lat><?= xmlEscape($poi['latitude']) ?></geo:lat>
      <geo:long><?= xmlEscape($poi['longitude']) ?></geo:long>
    </item>

    <?php endforeach; ?>

    <!-- =======================================================
         SECTION 3 — NEWS
         Only rendered if the News table exists and has rows.
         pubDate uses the rssDate() helper defined at the top
         to convert the MySQL timestamp to RFC 2822 format.
    ======================================================= -->
    <?php foreach ($news as $item): ?>

    <item>
      <title><?= xmlEscape($item['headline']) ?> – <?= xmlEscape($item['city_name']) ?></title>
      <link><?= xmlEscape($rssBaseUrl . '/index.php') ?></link>

      <!-- Each news item gets a unique guid built from its database ID -->
      <guid><?= xmlEscape($rssBaseUrl . '/news/' . (int)$item['news_id']) ?></guid>

      <!-- pubDate is converted from MySQL format by our rssDate() helper -->
      <pubDate><?= rssDate($item['published_at']) ?></pubDate>

      <description><![CDATA[<?= htmlspecialchars($item['body']) ?>]]></description>
      <dc:subject>News – <?= xmlEscape($item['city_name']) ?></dc:subject>
    </item>

    <?php endforeach; ?>

  </channel>
</rss>