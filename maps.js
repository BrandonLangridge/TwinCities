/* maps.js */

// Get the city from the URL (e.g. ?city=Cologne). Default to Liverpool if missing. 
const params = new URLSearchParams(window.location.search);
const rawCity = params.get("city") || "Liverpool";
const cityKey = rawCity.toLowerCase();

// Metadata for supported cities to center the map correctly
const cities = {
  liverpool: { name: "Liverpool", center: [53.4084, -2.9916], zoom: 13 },
  cologne: { name: "Cologne", center: [50.9413, 6.9583], zoom: 13 }
};

// Validation: Stop execution if the user tries to load an unsupported city
if (!cities[cityKey]) {
  document.getElementById("map").innerHTML = "<h2>City not found</h2>";
  throw new Error("Invalid city");
}

const activeCity = cities[cityKey];

// MAP INITIALISATION
const map = L.map("map", {
  scrollWheelZoom: false,
  zoomControl: false,      
  doubleClickZoom: false,  
  touchZoom: false,        
  boxZoom: false           
}).setView(activeCity.center, activeCity.zoom);

// Load the map tiles from OpenStreetMap
L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
  attribution: "&copy; OpenStreetMap contributors"
}).addTo(map);

// POINTS OF INTEREST (POI) RENDERING
// If the 'pois' global array exists, filter and display markers for the current city.
if (typeof pois !== 'undefined') {
  const markerArray = []; 

  pois
    .filter(poi => poi.town.toLowerCase() === cityKey) // Only show POIs for the active city
    .forEach(poi => {
      // Create a marker and add it to our tracking array for later auto-fitting
      const marker = L.marker([poi.lat, poi.lng]).addTo(map);
      markerArray.push(marker);

      // Define the HTML structure for the hover tooltip
      const tooltipContent = `
        <div style="padding: 5px; min-width: 150px;">
          <strong style="color: #0b5fff;">${poi.name}</strong><br>
          <small>Type: ${poi.type}</small><br>
          <small>Opened: ${poi.yearOpened}</small><br>
          <small>Entry: ${poi.entryFee}</small><br>
          <strong>Rating: ${poi.rating} ★</strong>
        </div>
      `;

      // Attach tooltip: 'sticky' follows the mouse, 'direction top' keeps it above the pin
      marker.bindTooltip(tooltipContent, { 
        direction: "top", 
        sticky: true,
        boundary: 'viewport' 
      });

      //REDIRECT LOGIC
      //Clicking a marker takes the user to a detailed view page.
      marker.on("click", () => {
        // Formats name for Wikipedia-style URLs (e.g., "The Beatles Story" -> "The_Beatles_Story")
        const wikiFormattedName = poi.name.replace(/\s+/g, '_');
        window.location.href = `details.php?poi=${wikiFormattedName}`;
      });
    });

  // AUTO-FIT LOGIC
  // Automatically adjusts the map zoom/position so all markers are visible at once.
  if (markerArray.length > 0) {
    const group = new L.featureGroup(markerArray);
    map.fitBounds(group.getBounds(), { padding: [50, 50] }); 
  }
}

// LIFECYCLE & RESET LOGIC
//Fixes "zombie" states (like open tooltips or grey map tiles) 
//when the user clicks the 'Back' button in their browser.
window.addEventListener('pageshow', function(event) {
    if (map) {
        map.closePopup();
        map.eachLayer(function(layer) {
            if (layer.closeTooltip) {
                layer.closeTooltip();
            }
        });
        // Forces Leaflet to recalculate the container size to prevent rendering glitches
        map.invalidateSize();
    }
});







