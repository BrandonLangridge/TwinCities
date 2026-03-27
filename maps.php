<?php
/* maps.php */

require_once "config.php"; 

// Map Component
?>

<!-- Map Container -->
<div id="map-wrapper">
  <div id="map"></div>
</div>

<!-- POI (Point of Interest) Modal -->
<div id="poiModal" class="poi-modal" style="display: none;">
  <div class="poi-modal-content" style="position:relative;">
    <!-- Close button for the POI modal -->
    <button id="poiModalClose" class="poi-modal-close" aria-label="Close modal">&times;</button>
    <!-- Container where POI details will be injected -->
    <div id="poiModalBody"></div>
  </div>
</div>

<!-- Leaflet JS library -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<!-- Main map script with cache-busting query string -->
<script src="maps.js?v=<?= urlencode($assetVersion); ?>"></script>

<script>
  // Modal close functionality (specific to map POIs)
  (function() {
    const poiModal = document.getElementById("poiModal");
    const poiModalClose = document.getElementById("poiModalClose");

    // Close the modal when the 'X' button is clicked
    if (poiModalClose) {
      poiModalClose.addEventListener("click", () => {
        // Stop any active speech synthesis (if used)
        if (window.speechSynthesis) window.speechSynthesis.cancel();
        // Hide the modal
        poiModal.style.display = "none";
      });
    }

    // Close the modal when clicking outside the modal content
    window.addEventListener("click", (e) => {
      if (e.target === poiModal) {
        if (window.speechSynthesis) window.speechSynthesis.cancel();
        poiModal.style.display = "none";
      }
    });
  })();
</script>