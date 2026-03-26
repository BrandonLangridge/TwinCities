<?php

/**
 * weather_widget.php - Cleaned Version
 * * This function fetches weather data from an external API and 
 * renders a 7-day forecast table and current conditions.
 */

function renderWeatherWidget($cityName, $lat, $lon, $weatherBase, $units, $weatherCodes)
{

    // Mapping from 'metric' to 'celsius' to combat any errors
    // Open-Meteo API specifically looks for 'celsius' or 'fahrenheit' strings
    $apiUnits = ($units === 'metric') ? 'celsius' : (($units === 'imperial') ? 'fahrenheit' : $units);

    $baseUrl = rtrim($weatherBase, '/');
    
    // Define parameters for the API call including daily variables and timezone
    $queryParams = [
        'current_weather'  => 'true',
        'latitude'         => $lat,
        'longitude'        => $lon,
        'daily'            => 'temperature_2m_max,temperature_2m_min,sunrise,sunset,weathercode,precipitation_sum',
        'timezone'         => 'auto',
        'forecast_days'    => 7,
        'temperature_unit' => $apiUnits
    ];

    // Construct the full URL with encoded query parameters
    $url = $baseUrl . "/forecast?" . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

    // Initialize cURL session to fetch the weather data
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification for compatibility
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);           // Set a 10-second timeout limit
    $response = curl_exec($ch);
    curl_close($ch);

    // Decode the JSON response into an associative array
    $data = json_decode($response, true);

    // Error Handling: If API fails or returns invalid data, show a fallback message
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
        return;
    }

    ?>
    <article class="city" aria-labelledby="label-<?= htmlspecialchars($cityName) ?>">
        <h2 id="label-<?= htmlspecialchars($cityName) ?>">Weather for <?= htmlspecialchars($cityName) ?></h2>

        <?php 
        // Display Current Weather section if data is available
        if (isset($data["current_weather"])): $current = $data["current_weather"]; ?>
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
                    $daily = $data["daily"];
                    // Loop through the 7-day forecast data
                    for ($i = 0; $i < count($daily["time"]); $i++):
                        // Convert API date string to a readable format (e.g., Mon, 12 Oct)
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