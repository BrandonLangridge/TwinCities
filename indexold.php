<?php 
  /* index.php */

  // 1. Link to config.php
  // This provides the $pdo connection and ensures consistent DB settings.
  $config = require __DIR__ . '/config.php'; 

  // 2. DATABASE AUTO-CHECK BLOCK:
  // We try a simple query to see if the 'Cities' table exists.
  try {
      $pdo->query("SELECT 1 FROM Cities LIMIT 1");
  } catch (Exception $e) {
      // If the query fails, the database is likely not set up.
      // Redirect to your setup.php file.
      header("Location: setup.php");
      exit;
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($config['rss']['title']) ?></title>
  <link rel="stylesheet" href="styles.css">
</head>

<body class="index-body">

  <header>
    <h1>
      Twin Cities:
      <span class="h1-accent">Liverpool</span> &
      <span class="h1-accent">Cologne</span>
    </h1>
    <p>Click a city below to explore its map, weather and points of interest</p>

    <div class="toggle-container">
      <button id="voiceToggle" class="toggle-button">Voice Feedback: OFF</button>
      
      <select id="colorModeSelect" class="toggle-button color-select-dropdown">
        <option value="none" style="color: #333;">Color-Blind Mode: OFF</option>
        <option value="protan" style="color: #333;">Protanopia</option>
        <option value="deutan" style="color: #333;">Deuteranopia</option>
        <option value="tritan" style="color: #333;">Tritanopia</option>
      </select>

      <button id="rssJump" class="toggle-button" style="background: #ee802f;">View RSS Feed</button>
  </div>
  </header>

  <main>
    <div class="city-container">
      <a class="city" href="maps.php?city=liverpool">
        <img src="liverpool.jpg" alt="Liverpool city skyline">
        <h2>Liverpool, UK</h2>
        <span class="city-button">Explore Liverpool</span>
      </a>

      <a class="city" href="maps.php?city=cologne">
        <img src="cologne.jpg" alt="Cologne skyline with cathedral">
        <h2>Cologne, Germany</h2>
        <span class="city-button">Explore Cologne</span>
      </a>
    </div>
  </main>

  <div id="aria-live" aria-live="polite" style="position:absolute; left:-9999px;"></div>

  <svg style="display:none" version="1.1" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <filter id="protanopia">
        <feColorMatrix type="matrix" values="0.567, 0.433, 0, 0, 0, 0.558, 0.442, 0, 0, 0, 0, 0.242, 0.758, 0, 0, 0, 0, 0, 1, 0" />
      </filter>
      <filter id="deuteranopia">
        <feColorMatrix type="matrix" values="0.625, 0.375, 0, 0, 0, 0.7, 0.3, 0, 0, 0, 0, 0.3, 0.7, 0, 0, 0, 0, 0, 1, 0" />
      </filter>
      <filter id="tritanopia">
        <feColorMatrix type="matrix" values="0.95, 0.05, 0, 0, 0, 0, 0.433, 0.567, 0, 0, 0, 0.475, 0.525, 0, 0, 0, 0, 0, 1, 0" />
      </filter>
    </defs>
  </svg>

  <script>
    /* Retrieve user preferences from localStorage to ensure settings 
     stay active across page refreshes and navigation. */
    let voiceEnabled = localStorage.getItem("voiceEnabled") === "true";
    let savedColorMode = localStorage.getItem("colorMode") || "none";

    const voiceBtn = document.getElementById("voiceToggle");
    const colorSelect = document.getElementById("colorModeSelect");
    const rssBtn = document.getElementById("rssJump");
    const ariaLive = document.getElementById("aria-live");

    /* COLOR-BLIND MODE
       Apply visual filters globally. */
    function applyColorMode(mode) {
      const root = document.documentElement; // Apply to <html> tag
      root.classList.remove("protan", "deutan", "tritan");
      if (mode !== "none") {
        root.classList.add(mode);
      }
    }

    // Initialise UI based on saved preference
    applyColorMode(savedMode = savedColorMode);
    // Sanity check for dropdown value
    colorSelect.value = (savedColorMode === "true" || savedColorMode === "false") ? "none" : savedColorMode;

    /* VOICE FEEDBACK
       Voice Feedback using the Web Speech API. */
    voiceBtn.textContent = `Voice Feedback: ${voiceEnabled ? "ON" : "OFF"}`;

    function speak(text) {
      if (!voiceEnabled || !window.speechSynthesis) return;
      window.speechSynthesis.cancel(); // Clear current queue to prevent overlapping audio
      const msg = new SpeechSynthesisUtterance(text);
      window.speechSynthesis.speak(msg);
      // Synchronize text with ARIA-live for screen reader users
      ariaLive.textContent = text; 
    }

    // Toggle Voice Feedback
    voiceBtn.addEventListener("click", () => {
      voiceEnabled = !voiceEnabled;
      localStorage.setItem("voiceEnabled", voiceEnabled);
      voiceBtn.textContent = `Voice Feedback: ${voiceEnabled ? "ON" : "OFF"}`;
      if (voiceEnabled) speak("Voice feedback enabled");
    });

    // Handle Color Mode Selection
    colorSelect.addEventListener("change", (e) => {
      const mode = e.target.value;
      applyColorMode(mode);
      localStorage.setItem("colorMode", mode);
      if (voiceEnabled) speak(mode === "none" ? "Color blind mode disabled" : `${mode} mode enabled`);
    });

    // Navigate to RSS with Voice confirmation
    rssBtn.addEventListener("click", () => {
      if (voiceEnabled) {
        speak("Opening the city news feed viewer");
        // Delay navigation slightly so the user can hear the confirmation
        setTimeout(() => { window.open("rss_view.php", "_blank"); }, 1000);
      } else {
        window.open("rss_view.php", "_blank");
      }
    });

    /* Adds voice confirmation to city card clicks when Voice is ON. */
    document.querySelectorAll(".city").forEach(city => {
      city.addEventListener("click", (e) => {
        const target = city.getAttribute('href');
        if (voiceEnabled) {
          e.preventDefault(); // Stop immediate navigation
          speak(`Navigating to ${city.querySelector("h2").textContent}`);
          setTimeout(() => { window.location.href = target; }, 1000);
        }
      });
    });
  </script>
</body>
</html>







