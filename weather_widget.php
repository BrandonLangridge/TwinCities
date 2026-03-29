<?php
// Weather widget renderer (safe + resilient)

// Ensure config is loaded
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

// Render weather UI
function renderWeatherWidget($cityName, $lat, $lon, $weatherBase, $units, $weatherCodes)
{
    // Convert units for API
    $apiUnits = ($units === 'metric') ? 'celsius' : (($units === 'imperial') ? 'fahrenheit' : $units);

    // Clean base URL
    $baseUrl  = rtrim($weatherBase, '/');
    
    // Build API query
    $queryParams = [
        'current_weather'  => 'true', // include current data
        'latitude'         => $lat,
        'longitude'        => $lon,
        'daily'            => 'temperature_2m_max,temperature_2m_min,sunrise,sunset,weathercode,precipitation_sum',
        'timezone'         => 'auto', // auto timezone
        'forecast_days'    => 7,      // 7-day forecast
        'temperature_unit' => $apiUnits
    ];

    // Final API URL
    $url = $baseUrl . "/forecast?" . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

    // Init cURL
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);            // target URL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // return response
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// skip SSL check
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);          // timeout
          
    // Execute request
    $response = curl_exec($ch);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        $errorMsg = curl_error($ch);
        curl_close($ch);

        // Throw for global handler
        throw new Exception("Weather Connectivity Error: " . $errorMsg);
    }

    // Close cURL
    curl_close($ch);

    // Decode JSON
    $data = json_decode($response, true);

    // Validate API response
    if (!$data || !isset($data["daily"])) {
?>
        <article class="city weather-error-placeholder">
            <h2>Weather for <?= htmlspecialchars($cityName) ?></h2>
            <div class="weather-fallback-card">
                <span class="fallback-icon">☁️</span>
                <p><strong>Weather Service Offline</strong></p>
                <p>We’re having trouble reaching the forecast provider. Please try refreshing the page in a few moments.</p>
            </div>
        </article>
<?php
        return; // stop safely
    }

    // Start main UI
?>
    <article class="city" aria-labelledby="label-<?= htmlspecialchars($cityName) ?>">
        <h2 id="label-<?= htmlspecialchars($cityName) ?>">Weather for <?= htmlspecialchars($cityName) ?></h2>

        <?php 
        // Show current weather if available
        if (isset($data["current_weather"])): 
            $current = $data["current_weather"]; 
        ?>
            <dl class="current-weather-list">
                <div>
                    <dt>Current Temp:</dt>
                    <dd><?= $current['temperature'] ?>&deg;<?= ($units === 'imperial' ? 'F' : 'C') ?></dd>
                </div>
                <div>
                    <dt>Wind Speed:</dt>
                    <dd><?= $current['windspeed'] ?> km/h</dd>
                </div>
                <div>
                    <dt>Weather:</dt>
                    <dd><?= $weatherCodes[$current['weathercode']] ?? 'Clear' ?></dd>
                </div>
            </dl>
        <?php endif; ?>

        <!-- Forecast table -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>High</th>
                        <th>Low</th>
                        <th>Description</th>
                        <th>Rain</th>
                        <th>Sunrise</th>
                        <th>Sunset</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $daily = $data["daily"]; // daily data

                    // Loop through days
                    for ($i = 0; $i < count($daily["time"]); $i++):
                        
                        // Format date
                        $day = date("D, j M", strtotime($daily["time"][$i]));
                    ?>
                        <tr>
                            <td class="left-edge date"><?= $day ?></td>
                            <td class="high"><?= number_format($daily["temperature_2m_max"][$i], 1) ?>&deg;</td>
                            <td class="low"><?= number_format($daily["temperature_2m_min"][$i], 1) ?>&deg;</td>
                            <td class="weather-desc"><?= $weatherCodes[$daily["weathercode"][$i]] ?? 'Clear' ?></td>
                            <td class="precip-cell"><?= number_format($daily["precipitation_sum"][$i], 1) ?>mm</td>
                            <td class="rise"><?= date("H:i", strtotime($daily["sunrise"][$i])) ?></td>
                            <td class="set right-edge"><?= date("H:i", strtotime($daily["sunset"][$i])) ?></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </article>
<?php
}