<?php
/* place.php */

// Link to config.php
// This provides the $pdo connection and ensures consistent DB settings.
require_once "config.php"; 

// Check for 'poi_id'
if (!isset($_GET["poi_id"])) {
    echo "<div style='padding:20px; font-family:sans-serif;'>No place selected.</div>";
    exit;
}

/* Cast to INT for security, ensuring if a user tries to inject malicious text into
the URL (e.g. place.php?poi_id=DROP TABLE) it will be converted to 0. */
$poiId = (int) $_GET["poi_id"];

/* DATA RETRIEVAL
   Joins Place_of_Interest and City to get full details. */
try {
    $sql = "
      SELECT
        p.name AS place_name,
        p.type AS place_type,
        p.latitude,
        p.longitude,
        p.description,
        c.name AS city_name,
        c.country
      FROM Place_of_Interest p
      JOIN City c ON c.city_id = p.city_id
      WHERE p.poi_id = :id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(":id", $poiId, PDO::PARAM_INT);
    $stmt->execute();
    $place = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Query Error: " . $e->getMessage());
}

if (!$place) {
    echo "<div style='padding:20px; font-family:sans-serif;'>Place not found.</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Browser tab shows the actual name of the place, better for book marking. -->
  <title><?= htmlspecialchars($place["place_name"]) ?> | Details</title>

  <style>
    .container { max-width: 800px; margin: 40px auto; padding: 20px; font-family: sans-serif; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .content { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #ddd; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .details h1 { margin-top: 0; color: #2c3e50; }
    .meta { color: #666; font-size: 0.9rem; margin-bottom: 15px; font-weight: 600; text-transform: uppercase; }
    .specs { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .spec-item b { display: block; font-size: 0.75rem; text-transform: uppercase; color: #999; }
    .colorblind { filter: saturate(0.5) contrast(1.2); }
  </style>
</head>

<body>
  <!-- Tells screnn readers exactly where the primary content begins -->
  <div class="container" role="main">
    <div class="header">
      <a href="javascript:history.back()" class="toggle-button" style="text-decoration:none; padding: 8px 16px; background: #eee; border-radius: 4px; color: #333;">← Back</a>
    </div>

    <div id="output" aria-live="polite">
      <div class="content">
        <div class="details">
          <div class="meta">
            <?= htmlspecialchars($place["place_type"]) ?> &bull; 
            <?= htmlspecialchars($place["city_name"]) ?>, <?= htmlspecialchars($place["country"]) ?>
          </div>
          
          <h1><?= htmlspecialchars($place["place_name"]) ?></h1>
          
          <p style="line-height:1.6; color:#444;"><?= nl2br(htmlspecialchars($place["description"])) ?></p>
          
          <div class="specs">
            <div class="spec-item">
              <b>Location</b>
              <?= htmlspecialchars($place["city_name"]) ?>
            </div>
            <div class="spec-item">
              <b>Coordinates</b>
              <?= htmlspecialchars($place["latitude"]) ?>, <?= htmlspecialchars($place["longitude"]) ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    /* PERSISTENT ACCESSIBILITY */
    // Even if the user refreshes the page the colourblind settings remain.
    let colorBlindEnabled = false;
    try { colorBlindEnabled = localStorage.getItem("colorBlindEnabled") === "true"; } catch (e) { colorBlindEnabled = false; }
    if (colorBlindEnabled) { document.body.classList.add("colorblind"); }
  </script>
</body>
</html>
