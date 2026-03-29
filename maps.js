/* maps.js */

// Get the city from the URL (e.g. ?city=Cologne). Default to Liverpool if missing. 
const params = new URLSearchParams(window.location.search);
const rawCity = params.get("city") || "Liverpool";
const cityKey = rawCity.toLowerCase();

// Metadata for supported cities to center the map correctly.
const cities = {
  liverpool: { name: "Liverpool", center: [53.4084, -2.9916], zoom: 13 },
  cologne: { name: "Cologne", center: [50.9413, 6.9583], zoom: 13 }
};

// Validation: Stop execution if the user tries to load an unsupported city (e.g. not liverpool or Cologne).
if (!cities[cityKey]) {
  document.getElementById("map").innerHTML = "<h2>City not found</h2>";
  throw new Error("Invalid city");
}

const activeCity = cities[cityKey];

// MAP INITIALISATION
//L.map connects the JS logic to the <div id="map"> in maps.php.
const map = L.map("map", {
  scrollWheelZoom: true, 
  zoomControl: false,      
  doubleClickZoom: false,  
  touchZoom: false,        
  boxZoom: false           
}).setView(activeCity.center, activeCity.zoom);

// Load the map tiles from OpenStreetMap.
L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
  attribution: "&copy; OpenStreetMap contributors"
}).addTo(map);

// POINTS OF INTEREST (POI) RENDERING
if (typeof pois !== 'undefined') {
  const markerArray = []; 

  pois
    /* Instead of drawing on every marker from the DB, 
     only process the ones that belong to the current city.*/
    .filter(poi => poi.town.toLowerCase() === cityKey) 
    .forEach(poi => {
      // Converts raw coordinates into a visual pin on the map.
      const marker = L.marker([poi.lat, poi.lng]).addTo(map);
      markerArray.push(marker);

      const tooltipContent = `
        <div style="padding: 5px; min-width: 150px;">
          <strong style="color: #0b5fff;">${poi.name}</strong><br>
          <small>Type: ${poi.type}</small><br>
          <small>Opened: ${poi.yearOpened}</small><br>
          <small>Entry: ${poi.entryFee}</small><br>
          <strong>Rating: ${poi.rating} ★</strong>
        </div>
      `;
      //Creates the hover effect of the box displaying the POI info.
      marker.bindTooltip(tooltipContent, { 
        direction: "top", 
        sticky: true,
        boundary: 'viewport' 
      });

      marker.on("click", () => {
        openPoiModal(poi);
      });
    });

  if (markerArray.length > 0) {
    const group = new L.featureGroup(markerArray);
    map.fitBounds(group.getBounds(), { padding: [50, 50] }); 
  }
}

// MODAL OPENING LOGIC
/* async tells the browser to fetch the info from the internet and let
 me know when its done without freezing the rest of the page.*/
async function openPoiModal(poi) {
  const modal = document.getElementById("poiModal");
  const modalBody = document.getElementById("poiModalBody");

  modalBody.innerHTML = "<p>Loading details...</p>";
  modal.style.display = "flex";

  try {
    /* Hardening restored: encodeURIComponent ensures names with spaces/special chars don't break the URL
    allowing the wiki API to find the right information page.*/
    const response = await fetch(`https://en.wikipedia.org/api/rest_v1/page/summary/${encodeURIComponent(poi.name)}`);
    
    if (!response.ok) throw new Error("Wikipedia data not found");
    
    const data = await response.json();

    modalBody.innerHTML = `
      <h2 style="margin:0 0 8px 0;">${data.title}</h2>
      <button id="poiReadBtn" class="poi-read-btn" type="button">🔊 Read aloud</button>
      ${data.thumbnail ? `<img src="${data.thumbnail.source}" style="max-width:100%; margin-bottom:12px;">` : ""}
      <p id="poiExtract">${data.extract}</p>
      <div style="font-size: 0.85em; color: #666; border-top: 1px solid #ddd; padding-top: 10px;">
        <a href="${data.content_urls.desktop.page}" target="_blank" rel="noopener noreferrer">Read full article on Wikipedia →</a>
      </div>
    `;

    document.getElementById('poiReadBtn').addEventListener('click', () => {
      const text = data.title + '. ' + data.extract;
      // Hardening restored: checking for browser support before calling speech API.
      if ('speechSynthesis' in window) {
        //Prevents a "choir" of overlapping audio if user clicks mutiple POIs.
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(new SpeechSynthesisUtterance(text));
      } else {
        alert("Text-to-speech is not supported in this browser.");
      }
    });

  } catch (error) {
    // Hardening restored: simple error fallback if fetch fails.
    modalBody.innerHTML = `<h2>${poi.name}</h2><p>Could not load details from Wikipedia at this time.</p>`;
    console.error("Wiki Fetch Error:", error);
  }
}

// LIFECYCLE & RESET LOGIC
window.addEventListener('pageshow', function(event) {
    if (map) {
        map.closePopup();
        map.eachLayer(function(layer) {
            if (layer.closeTooltip) {
                layer.closeTooltip();
            }
        });
        map.invalidateSize();
    }
});
