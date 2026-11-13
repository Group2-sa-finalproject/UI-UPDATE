<?php
// This file is included inside dashboard.php.
// Do not add another HTML, HEAD, BODY, or NAVBAR.
?>

<style>
/* Live Collector Tracking - presentation styles only */
.tracking-page {
    color: #24382d;
}

.tracking-page-title {
    color: #173f2b;
    font-weight: 750;
    letter-spacing: -.5px;
    margin-bottom: 7px;
}

.tracking-page-subtitle {
    color: #748278;
    font-size: 14px;
    margin-bottom: 0;
}

.tracking-heading-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    margin-right: 10px;
    border-radius: 12px;
    background: #e8f4ec;
    color: #23834a;
    font-size: 18px;
    vertical-align: middle;
}

.tracking-card {
    background: #fff;
    padding: 24px;
    border: 1px solid #e8eee9;
    border-radius: 16px;
    box-shadow: 0 5px 22px rgba(25, 65, 42, .055);
    margin-bottom: 22px;
}

.tracking-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 18px;
}

.tracking-section-title {
    color: #234b34;
    font-size: 16px;
    font-weight: 700;
    margin: 0;
}

.tracking-section-description {
    color: #87938b;
    font-size: 12px;
    margin: 5px 0 0;
}

.tracking-live-label {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 11px;
    border-radius: 30px;
    background: #eaf7ee;
    color: #23834a;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .35px;
    text-transform: uppercase;
}

.tracking-live-dot {
    width: 7px;
    height: 7px;
    display: inline-block;
    border-radius: 50%;
    background: #28a653;
    box-shadow: 0 0 0 4px rgba(40, 166, 83, .12);
}

.tracking-map-wrap {
    overflow: hidden;
    border: 1px solid #e4ebe6;
    border-radius: 12px;
    background: #edf2ee;
}

#collectorMap {
    width: 100%;
    height: 550px;
    background: #e9efeb;
    z-index: 1;
}

.tracking-updated {
    color: #7b8980;
    font-size: 12px;
    background: #f6f9f6;
    border: 1px solid #edf1ed;
    border-radius: 8px;
    padding: 8px 11px;
}

.tracking-error {
    border-radius: 10px;
    font-size: 13px;
}

#collectorList {
    color: #7a887f;
}

.collector-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 15px 14px;
    border: 1px solid #edf1ed;
    border-radius: 11px;
    margin-bottom: 9px;
    transition: background .2s ease, border-color .2s ease, transform .2s ease;
}

.collector-row:last-child {
    margin-bottom: 0;
}

.collector-row:hover {
    background: #f7fbf8;
    border-color: #cfe4d5;
    transform: translateY(-1px);
}

.collector-name {
    color: #234b34;
    font-weight: 700;
    font-size: 14px;
}

.collector-coordinates {
    font-size: 11px;
    color: #849188;
    margin-top: 5px;
    letter-spacing: .15px;
}

.tracking-status {
    white-space: nowrap;
    min-width: 68px;
    text-align: center;
    padding: 7px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 700;
}

.tracking-status.bg-success {
    background: #e7f6eb !important;
    color: #218348 !important;
}

.tracking-status.bg-secondary {
    background: #eef0ef !important;
    color: #68736c !important;
}

@media (max-width: 600px) {
    #collectorMap {
        height: 400px;
    }

    .tracking-card {
        padding: 16px;
        border-radius: 13px;
    }

    .tracking-page-title {
        font-size: 22px;
    }

    .tracking-updated {
        width: 100%;
    }
}

<style>
/* PROFESSIONAL LIVE TRACKING DESIGN */
.tracking-page {
    color: #26372d;
}

.tracking-page-title {
    color: #193d2a;
    font-size: 25px;
    font-weight: 700;
    letter-spacing: -.4px;
    margin-bottom: 6px;
}

.tracking-page-subtitle {
    color: #7b887f;
    font-size: 13px;
    margin: 0;
}

.tracking-card {
    background: #ffffff;
    padding: 25px;
    border: 1px solid #e5ebe6;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(30, 60, 40, .04);
    margin-bottom: 22px;
}

.tracking-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding-bottom: 17px;
    margin-bottom: 18px;
    border-bottom: 1px solid #edf1ed;
}

.tracking-section-title {
    color: #234b34;
    font-size: 16px;
    font-weight: 700;
    margin: 0;
}

.tracking-section-description {
    color: #87938b;
    font-size: 12px;
    margin: 6px 0 0;
}

.tracking-live-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 13px;
    border: 1px solid #dceee1;
    border-radius: 6px;
    background: #f2f9f4;
    color: #287344;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .4px;
    text-transform: uppercase;
}

.tracking-live-dot {
    width: 7px;
    height: 7px;
    display: inline-block;
    border-radius: 50%;
    background: #299653;
}

.tracking-map-wrap {
    overflow: hidden;
    border: 1px solid #e3e9e4;
    border-radius: 9px;
    background: #f0f3f0;
}

#collectorMap {
    width: 100%;
    height: 540px;
    background: #e9efeb;
    z-index: 1;
}

.tracking-updated {
    color: #69786e;
    font-size: 11px;
    background: #f7f9f7;
    border: 1px solid #e8ede9;
    border-radius: 6px;
    padding: 8px 11px;
}

.tracking-error {
    border-radius: 8px;
    font-size: 12px;
}

#collectorList {
    color: #7a887f;
}

.collector-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    padding: 16px 17px;
    border: 1px solid #e8eee9;
    border-radius: 8px;
    margin-bottom: 10px;
    background: #ffffff;
    transition: background .2s ease, border-color .2s ease;
}

.collector-row:last-child {
    margin-bottom: 0;
}

.collector-row:hover {
    background: #f8fbf8;
    border-color: #cbded0;
}

.collector-name {
    color: #294a35;
    font-weight: 650;
    font-size: 14px;
}

.collector-coordinates {
    font-size: 11px;
    color: #87938b;
    margin-top: 5px;
}

.tracking-status {
    white-space: nowrap;
    min-width: 70px;
    text-align: center;
    padding: 7px 11px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
}

.tracking-status.bg-success {
    background: #eaf5ed !important;
    color: #287344 !important;
}

.tracking-status.bg-secondary {
    background: #f0f2f0 !important;
    color: #68736c !important;
}

@media (max-width: 600px) {
    #collectorMap {
        height: 390px;
    }

    .tracking-card {
        padding: 16px;
    }

    .tracking-page-title {
        font-size: 21px;
    }

    .tracking-updated {
        width: 100%;
    }
}
</style>

<div class="tracking-page">

    <!-- PAGE TITLE -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="tracking-page-title">
                Live Collector Tracking
            </h2>

            <p class="tracking-page-subtitle">
                Monitor the latest GPS locations reported by your waste collectors.
            </p>
        </div>

        <span class="tracking-live-label">
            <span class="tracking-live-dot"></span>
            Live Monitoring
        </span>
    </div>

    <!-- MAP CARD -->
    <div class="tracking-card">

        <div class="tracking-card-header">
            <div>
                <h5 class="tracking-section-title">
                    Collector Locations
                </h5>

                <p class="tracking-section-description">
                    View the current GPS positions of your waste collectors.
                </p>
            </div>

            <span id="trackingLastUpdated" class="tracking-updated">
                Waiting for location data...
            </span>
        </div>

        <div class="tracking-map-wrap">
            <div id="collectorMap"></div>
        </div>

        <div id="trackingError"
             class="alert alert-warning tracking-error mt-3 d-none">
        </div>

    </div>

    <!-- COLLECTOR STATUS CARD -->
    <div class="tracking-card">

        <div class="tracking-card-header">
            <div>
                <h5 class="tracking-section-title">
                    Collector Status
                </h5>

                <p class="tracking-section-description">
                    View collector availability and their latest GPS coordinates.
                </p>
            </div>
        </div>

        <div id="collectorList" class="text-secondary">
            Loading collectors...
        </div>

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

    // CHECK LEAFLET
    if (typeof L === "undefined") {

        listElement.textContent =
            "Leaflet map library did not load. Check your internet connection.";

        errorElement.textContent =
            "Map library unavailable. Reload the page after checking your connection.";

        errorElement.classList.remove("d-none");

        window.ecoTrackInitialized = false;

        return;
    }

    // INITIALIZE MAP
    const map = L.map("collectorMap").setView(
        [15.4802, 120.5979],
        12
    );

    const markerByCollector = {};

    let firstValidLocation = true;
    let requestInProgress = false;

    // OPENSTREETMAP
    L.tileLayer(
        "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
        {
            attribution: "&copy; OpenStreetMap contributors",
            maxZoom: 19
        }
    ).addTo(map);

    // MAP SIZE FIX
    setTimeout(function () {
        map.invalidateSize();
    }, 300);


    // SHOW ERROR
    function showError(message) {

        errorElement.textContent = message;

        errorElement.classList.remove("d-none");

    }


    // CLEAR ERROR
    function clearError() {

        errorElement.textContent = "";

        errorElement.classList.add("d-none");

    }


    // CREATE COLLECTOR ROW
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
        row.style.cursor = "pointer";

        row.addEventListener("click", function () {

            const point = [
                Number(collector.latitude),
                Number(collector.longitude)
            ];

            map.setView(point, 17);

            if (markerByCollector[collector.id]) {
                markerByCollector[collector.id].openPopup();
            }

        });

        return row;

    }


    // FETCH COLLECTOR LOCATIONS
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

                // VALIDATE GPS DATA
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

                const point = [lat, lng];

                const safeName = String(
                    collector.fullname || "Collector"
                );

                // UPDATE EXISTING MARKER
                if (markerByCollector[id]) {

                    markerByCollector[id].setLatLng(point);

                } else {

                    markerByCollector[id] = L.marker(point).addTo(map);

                }

                // CREATE POPUP
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

                markerByCollector[id].bindPopup(popup);

                // ADD TO COLLECTOR LIST
                listElement.appendChild(
                    makeCollectorRow(collector)
                );

                // CENTER MAP ON FIRST COLLECTOR
                if (firstValidLocation) {

                    map.setView(point, 15);

                    firstValidLocation = false;

                }

            });


            // REMOVE OLD MARKERS
            Object.keys(markerByCollector).forEach(function (id) {

                if (!receivedIds.has(String(id))) {

                    map.removeLayer(markerByCollector[id]);

                    delete markerByCollector[id];

                }

            });


            // NO GPS RECORDS
            if (validCount === 0) {

                listElement.textContent =
                    "No collector GPS records found. Check whether collectors have shared their location.";

            }


            // LAST UPDATED
            updatedElement.textContent =
                "Last checked: " +
                new Date().toLocaleTimeString();

        } catch (error) {

            console.error(
                "Collector tracking error:",
                error
            );

            showError(error.message);

            listElement.textContent =
                "Unable to load collector locations. See the error message above.";

        } finally {

            requestInProgress = false;

        }

    }


    // INITIAL LOAD
    loadCollectors();


    // AUTOMATIC REFRESH EVERY 5 SECONDS
    const trackingInterval = setInterval(
        loadCollectors,
        5000
    );


    // RESPONSIVE MAP
    window.addEventListener("resize", function () {

        map.invalidateSize();

    });

})();

</script>
