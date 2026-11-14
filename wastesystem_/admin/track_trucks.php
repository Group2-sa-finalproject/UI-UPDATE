
<?php
// This file is included inside dashboard.php.
// Do not add another HTML, HEAD, BODY, or NAVBAR.
?>

<style>
.tracking-page-title {
    color: #183d2b;
    font-weight: 700;
}

.tracking-card {
    background: #fff;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,.05);
    margin-bottom: 20px;
}

#collectorMap {
    width: 100%;
    height: 550px;
    border-radius: 10px;
    background: #e9efeb;
}

.collector-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 14px 5px;
    border-bottom: 1px solid #edf0ed;
    cursor: pointer;
}

.collector-row:last-child {
    border-bottom: 0;
}

.collector-name {
    color: #183d2b;
    font-weight: 600;
}

.collector-coordinates {
    font-size: 12px;
    color: #77847c;
    margin-top: 4px;
}

.tracking-status {
    white-space: nowrap;
}

@media (max-width: 600px) {
    #collectorMap {
        height: 400px;
    }

    .tracking-card {
        padding: 14px;
    }
}
</style>

<!-- PAGE TITLE -->
<div class="mb-4">
    <h2 class="tracking-page-title">
        <i class="fas fa-truck"></i>
        Live Collector Tracking
    </h2>

    <p class="text-secondary">
        Monitor the latest GPS locations reported by your waste collectors.
    </p>
</div>

<!-- MAP -->
<div class="tracking-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="fw-bold mb-0">
            <i class="fas fa-map-marked-alt"></i>
            Collector Locations
        </h5>

        <span id="trackingLastUpdated" class="text-secondary small">
            Waiting for location data...
        </span>
    </div>

    <div id="collectorMap"></div>

    <div id="trackingError" class="alert alert-warning mt-3 d-none"></div>
</div>

<!-- COLLECTOR STATUS -->
<div class="tracking-card">
    <h5 class="fw-bold mb-3">
        <i class="fas fa-users"></i>
        Collector Status
    </h5>

    <div id="collectorList" class="text-secondary">
        Loading collectors...
    </div>
</div>

<script>
(function () {
    "use strict";

    // Prevent duplicate initialization
    if (window.ecoTrackInitialized) {
        return;
    }

    window.ecoTrackInitialized = true;

    const mapElement = document.getElementById("collectorMap");
    const listElement = document.getElementById("collectorList");
    const updatedElement = document.getElementById("trackingLastUpdated");
    const errorElement = document.getElementById("trackingError");

    if (!mapElement || !listElement || !updatedElement || !errorElement) {
        window.ecoTrackInitialized = false;
        console.error("Tracking elements are missing.");
        return;
    }

    let map = null;
    let infoWindow = null;
    let requestInProgress = false;
    let firstValidLocation = true;

    const markerByCollector = {};

    // ==========================================
    // LOAD GOOGLE MAPS
    // ==========================================

    function loadGoogleMaps() {
        return new Promise(function (resolve, reject) {

            if (window.google && window.google.maps) {
                resolve();
                return;
            }

            // Google Maps callback
            window.__ecoTrackGoogleMapsLoaded = function () {
                resolve();
            };

            const script = document.createElement("script");

            // IMPORTANT:
            // Replace this with your existing restricted Google Maps API key.
            const apiKey = "AIzaSyCz8mz80N6FN03Q1q1nQFGeZLduXsZpNHY";

            script.src =
                "https://maps.googleapis.com/maps/api/js" +
                "?key=" + encodeURIComponent(apiKey) +
                "&callback=__ecoTrackGoogleMapsLoaded" +
                "&loading=async";

            script.async = true;
            script.onerror = function () {
                reject(new Error("Google Maps failed to load. Check your API key and internet connection."));
            };

            document.head.appendChild(script);
        });
    }

    // ==========================================
    // INITIALIZE GOOGLE MAP
    // ==========================================

    function initializeMap() {

        map = new google.maps.Map(mapElement, {
            center: {
                lat: 15.4802,
                lng: 120.5979
            },
            zoom: 12,
            mapTypeControl: true,
            streetViewControl: false,
            fullscreenControl: true
        });

        infoWindow = new google.maps.InfoWindow();

        // Fix map sizing after display
        setTimeout(function () {
            google.maps.event.trigger(map, "resize");
        }, 300);
    }

    // ==========================================
    // SHOW ERROR
    // ==========================================

    function showError(message) {
        errorElement.textContent = message;
        errorElement.classList.remove("d-none");
    }

    function clearError() {
        errorElement.textContent = "";
        errorElement.classList.add("d-none");
    }

    // ==========================================
    // CREATE COLLECTOR LIST ROW
    // ==========================================

    function makeCollectorRow(collector) {

        const row = document.createElement("div");
        row.className = "collector-row";

        const left = document.createElement("div");

        const name = document.createElement("div");
        name.className = "collector-name";
        name.textContent =
            collector.fullname || ("Collector #" + collector.id);

        const coordinates = document.createElement("div");
        coordinates.className = "collector-coordinates";

        coordinates.textContent =
            Number(collector.latitude).toFixed(6) +
            ", " +
            Number(collector.longitude).toFixed(6);

        left.appendChild(name);
        left.appendChild(coordinates);

        const status = document.createElement("span");
        status.className = "badge tracking-status";

        if (Number(collector.is_online) === 1) {
            status.classList.add("bg-success");
            status.textContent = "Online";
        } else {
            status.classList.add("bg-secondary");
            status.textContent = "Offline";
        }

        row.appendChild(left);
        row.appendChild(status);

        // Click collector to locate on map
        row.addEventListener("click", function () {

            const point = {
                lat: Number(collector.latitude),
                lng: Number(collector.longitude)
            };

            map.setCenter(point);
            map.setZoom(17);

            if (markerByCollector[collector.id]) {
                infoWindow.open({
                    map: map,
                    anchor: markerByCollector[collector.id]
                });
            }
        });

        return row;
    }

    // ==========================================
    // FETCH COLLECTOR LOCATIONS
    // ==========================================

    async function loadCollectors() {

        if (requestInProgress) {
            return;
        }

        requestInProgress = true;

        try {

            const response = await fetch(
                new URL("get_collector_locations.php", window.location.href),
                {
                    method: "GET",
                    cache: "no-store",
                    headers: {
                        "Accept": "application/json"
                    }
                }
            );

            const responseText = await response.text();

            if (!response.ok) {
                throw new Error(
                    "API returned HTTP " +
                    response.status +
                    ": " +
                    responseText.substring(0, 250)
                );
            }

            let collectors;

            try {
                collectors = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error(
                    "API did not return valid JSON. Response: " +
                    responseText.substring(0, 250)
                );
            }

            if (!Array.isArray(collectors)) {
                throw new Error(
                    collectors.error || "Unexpected API response."
                );
            }

            clearError();

            listElement.innerHTML = "";

            const receivedIds = new Set();
            let validCount = 0;

            collectors.forEach(function (collector) {

                const id = Number(collector.id);
                const lat = Number(collector.latitude);
                const lng = Number(collector.longitude);

                // Validate GPS data
                if (
                    !Number.isInteger(id) ||
                    !Number.isFinite(lat) ||
                    !Number.isFinite(lng) ||
                    lat < -90 ||
                    lat > 90 ||
                    lng < -180 ||
                    lng > 180
                ) {
                    return;
                }

                validCount++;
                receivedIds.add(String(id));

                const point = {
                    lat: lat,
                    lng: lng
                };

                const safeName = String(
                    collector.fullname || "Collector"
                );

                // Create or update marker
                if (markerByCollector[id]) {

                    markerByCollector[id].setPosition(point);

                } else {

                    markerByCollector[id] = new google.maps.Marker({
                        position: point,
                        map: map,
                        title: safeName,
                        animation: null
                    });
                }

                markerByCollector[id].setTitle(safeName);

                // Build popup safely using DOM elements
                const popup = document.createElement("div");

                const popupName = document.createElement("strong");
                popupName.textContent = safeName;

                const popupStatus = document.createElement("div");
                popupStatus.textContent =
                    Number(collector.is_online) === 1
                    ? "Online"
                    : "Offline";

                const popupUpdated = document.createElement("small");
                popupUpdated.textContent =
                    "Last GPS update: " +
                    (collector.updated_at || "Unknown");

                popup.appendChild(popupName);
                popup.appendChild(popupStatus);
                popup.appendChild(popupUpdated);

                markerByCollector[id].addListener("click", function () {
                    infoWindow.setContent(popup);
                    infoWindow.open({
                        map: map,
                        anchor: markerByCollector[id]
                    });
                });

                // Add collector to list
                listElement.appendChild(
                    makeCollectorRow(collector)
                );

                // Center map on first valid collector
                if (firstValidLocation) {
                    map.setCenter(point);
                    map.setZoom(15);
                    firstValidLocation = false;
                }

            });

            // Remove markers for collectors no longer returned by API
            Object.keys(markerByCollector).forEach(function (id) {

                if (!receivedIds.has(String(id))) {

                    markerByCollector[id].setMap(null);

                    delete markerByCollector[id];
                }
            });

            // No GPS records
            if (validCount === 0) {
                listElement.textContent =
                    "No collector GPS records found. Check whether collectors have shared their location.";
            }

            // Last updated
            updatedElement.textContent =
                "Last checked: " +
                new Date().toLocaleTimeString();

        } catch (error) {

            console.error("Collector tracking error:", error);

            showError(error.message);

            listElement.textContent =
                "Unable to load collector locations. See the error message above.";

        } finally {

            requestInProgress = false;

        }
    }

    // ==========================================
    // START TRACKING
    // ==========================================

    async function startTracking() {

        try {

            await loadGoogleMaps();

            initializeMap();

            // Initial load
            await loadCollectors();

            // Automatic refresh every 5 seconds
            window.ecoTrackTrackingInterval = setInterval(
                loadCollectors,
                5000
            );

            // Responsive map
            window.addEventListener("resize", function () {
                if (map) {
                    google.maps.event.trigger(map, "resize");
                }
            });

        } catch (error) {

            console.error("Google Maps initialization error:", error);

            showError(error.message);

            listElement.textContent =
                "Unable to initialize the tracking map.";

            window.ecoTrackInitialized = false;
        }
    }

    startTracking();

})();
</script>
