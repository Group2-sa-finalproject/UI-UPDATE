<?php
session_start();
require_once "../config/database.php";

// Allow logged-in collectors only
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    !in_array($_SESSION["role"], ["collector", "collectors"])
) {
    header("Location: ../index.php");
    exit;
}

$collector_id = (int) $_SESSION["user_id"];

// Get regular routes assigned to this collector
$stmt = $conn->prepare("
    SELECT
        cr.id AS route_id,
        cr.route_name,
        cr.status AS route_status,
        rs.id AS stop_id,
        rs.stop_order,
        rs.status AS stop_status,
        h.id AS household_id,
        h.house_address,
        h.latitude,
        h.longitude,
        u.fullname
    FROM collection_routes cr
    INNER JOIN route_stops rs
        ON rs.route_id = cr.id
    INNER JOIN households h
        ON h.id = rs.household_id
    LEFT JOIN users u
        ON u.id = h.user_id
    WHERE cr.collector_id = ?
      AND cr.route_type = 'regular'
      AND cr.status IN ('assigned', 'in_progress')
    ORDER BY cr.id DESC, rs.stop_order ASC
");

if (!$stmt) {
    die("Database query failed: " . $conn->error);
}

$stmt->bind_param("i", $collector_id);
$stmt->execute();

$result = $stmt->get_result();

$routes = [];

while ($row = $result->fetch_assoc()) {
    $routes[] = $row;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Regular Collection Route</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body {
            background: #f4f7f6;
            font-family: Arial, sans-serif;
            padding-top: 85px;
        }

        .navbar {
            background: #198754;
            min-height: 65px;
        }

        .navbar-brand {
            color: white !important;
            font-weight: bold;
        }

        .page-container {
            max-width: 1200px;
            margin: 25px auto;
        }

        .route-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,.07);
            margin-bottom: 20px;
        }

        /* Route Cards: One Full-Width Card Per Row */
        .routes-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .routes-grid .route-card {
            margin-bottom: 0;
            width: 100%;
        }

        /* Household Stops: Two Cards Per Row */
        .stops-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        /* Stop Card */
        .stop-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: #f8faf9;
            border: 1px solid #e5eae7;
            border-radius: 10px;
            padding: 16px;
            margin: 0;
            min-width: 0;
        }

        .stop-number {
            min-width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #198754;
            color: white;
            border-radius: 50%;
            font-weight: bold;
        }

        .stop-details {
            flex: 1;
            min-width: 0;
        }

        .stop-name {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .stop-address {
            font-size: 13px;
            color: #6c757d;
            overflow-wrap: anywhere;
        }

        .stop-status {
            display: inline-block;
            background: #e8f5ed;
            color: #198754;
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .stop-meta {
            font-size: 12px;
            color: #6c757d;
        }

        /* Mobile */
        @media (max-width: 768px) {
            .stops-grid {
                grid-template-columns: 1fr;
            }
        }


        #map {
            width: 100%;
            height: 500px;
            border-radius: 10px;
        }

        .stop-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
        }

        .btn-green {
            background: #198754;
            color: white;
        }

        .btn-green:hover {
            background: #146c43;
            color: white;
        }
    </style>
</head>

<body>


<main class="container page-container">
    

    <h2 class="fw-bold">Regular Collection Route</h2>

    <p class="text-secondary">
        View the regular collection routes assigned to you by the admin.
    </p>

    <?php if (count($routes) > 0): ?>

        <?php
        $groupedRoutes = [];

        foreach ($routes as $route) {
            $groupedRoutes[$route["route_id"]][] = $route;
        }
        ?>

        <div class="routes-grid">

            <?php foreach ($groupedRoutes as $routeId => $stops): ?>

                <div class="route-card">

                <!-- Route Header -->
                <div class="route-header">

                    <div>
                        <h5 class="route-title">
                            <?= htmlspecialchars($stops[0]["route_name"]); ?>
                        </h5>

                        <p class="route-subtitle">
                            <?= count($stops); ?> household stop(s) assigned
                        </p>
                    </div>

                    <span class="route-status">
                        <?= htmlspecialchars($stops[0]["route_status"]); ?>
                    </span>

                </div>

                <!-- Driving Route Button -->
                <button
                    type="button"
                    class="btn btn-green mb-4"
                    onclick="showRegularRoute(<?= (int) $routeId ?>)">
                    View Driving Route
                </button>

                <!-- Household Stops -->             
                <h6 class="stops-heading mb-3">Household Stops</h6>

                <div class="stops-grid">

                    <?php foreach ($stops as $stop): ?>

                        <div class="stop-item">

                            <div class="stop-number">
                                <?= (int) $stop["stop_order"]; ?>
                            </div>

                            <div class="stop-details">

                                <h6 class="stop-name">
                                    <?= htmlspecialchars($stop["fullname"] ?? "Resident"); ?>
                                </h6>

                                <p class="stop-address mb-2">
                                    <strong>Address:</strong>
                                    <?= htmlspecialchars($stop["house_address"] ?? "No address"); ?>
                                </p>

                                <span class="stop-status">
                                    <?= htmlspecialchars($stop["stop_status"]); ?>
                                </span>

                                <div class="stop-meta mt-2">
                                    Household ID: <?= (int) $stop["household_id"]; ?>
                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="route-card">
            <p class="text-muted mb-0">
                No regular collection routes have been assigned to you yet.
            </p>
        </div>

    <?php endif; ?>

    <div class="route-card">
    <h5 class="fw-bold mb-3">Route Map</h5>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-green"
                onclick="showAllStops()">
            <i class="fas fa-house"></i> Show All Stops
        </button>

        <button type="button" class="btn btn-primary"
                onclick="showSelectedRouteStops()">
            <i class="fas fa-route"></i> Highlight Selected Route
        </button>
    </div>

    <div id="map"></div>
    <p id="routeStatus" class="text-secondary mt-3">
        Loading route map...
    </p>

</main>
<script>

const regularStops = <?= json_encode(array_map(function ($stop) {
    return [
        "route_id" => (int) $stop["route_id"],
        "household_id" => (int) $stop["household_id"],
        "stop_order" => (int) $stop["stop_order"],
        "name" => $stop["fullname"] ?? "Resident",
        "address" => $stop["house_address"] ?? "No address",
        "latitude" => is_numeric($stop["latitude"]) ? (float) $stop["latitude"] : null,
        "longitude" => is_numeric($stop["longitude"]) ? (float) $stop["longitude"] : null
    ];
}, $routes), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

let map;
let Route;
let currentPosition = null;
let currentLocationMarker = null;
let routePolylines = [];
let householdMarkers = [];
let routeMarkers = [];
let infoWindow;
let selectedRouteId = null;

// Prevent invalid or missing GPS coordinates from appearing at 0,0.
function hasValidCoordinates(stop) {
    return stop.latitude !== null &&
           stop.longitude !== null &&
           Number.isFinite(Number(stop.latitude)) &&
           Number.isFinite(Number(stop.longitude)) &&
           Math.abs(Number(stop.latitude)) <= 90 &&
           Math.abs(Number(stop.longitude)) <= 180;
}

// Escape values before placing them inside an info window.
function escapeHTML(value) {
    return String(value ?? "").replace(/[&<>"']/g, char => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    })[char]);
}

function getStopInfo(stop, isRouteStop) {
    return `
        <div style="min-width:190px;max-width:260px;padding:5px;">
            <strong style="font-size:14px;">
                ${escapeHTML(stop.name)}
            </strong>

            <hr style="margin:7px 0;">

            <div style="font-size:12px;margin-bottom:5px;">
                <strong>Address:</strong>
                ${escapeHTML(stop.address)}
            </div>

            <div style="font-size:12px;margin-bottom:5px;">
                <strong>Household ID:</strong>
                #${escapeHTML(stop.household_id)}
            </div>

            <div style="font-size:12px;">
                <strong>Stop Order:</strong>
                ${escapeHTML(stop.stop_order)}
            </div>

            ${isRouteStop ? `
                <div style="color:#1976d2;font-size:11px;font-weight:bold;margin-top:6px;">
                    Selected Route Stop
                </div>
            ` : ""}
        </div>
    `;
}

async function initMap() {
    const routesLibrary = await google.maps.importLibrary("routes");
    Route = routesLibrary.Route;

    map = new google.maps.Map(document.getElementById("map"), {
        center: { lat: 15.4802, lng: 120.5979 },
        zoom: 12,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true
    });

    infoWindow = new google.maps.InfoWindow();

    createHouseholdMarkers();

    // Initially display all household stops as green circles.
    showAllStops();

    if (!navigator.geolocation) {
        document.getElementById("routeStatus").textContent =
            "Your browser does not support GPS.";
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function(position) {
            currentPosition = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };

            currentLocationMarker = new google.maps.Marker({
                position: currentPosition,
                map: map,
                title: "Your Current Location",
                icon: {
                    url: "https://maps.google.com/mapfiles/ms/icons/blue-dot.png"
                }
            });

            document.getElementById("routeStatus").textContent =
                "GPS ready. Select a route to view its stops or driving directions.";
        },
        function(error) {
            document.getElementById("routeStatus").textContent =
                "Unable to get GPS location: " + error.message;
        },
        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    );
}

// Create green circular markers for every household.
function createHouseholdMarkers() {
    householdMarkers.forEach(marker => marker.setMap(null));
    householdMarkers = [];

    regularStops.forEach(stop => {
        if (!hasValidCoordinates(stop)) return;

        const position = {
            lat: Number(stop.latitude),
            lng: Number(stop.longitude)
        };

        const marker = new google.maps.Marker({
            position: position,
            map: null,
            title: stop.name,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 8,
                fillColor: "#198754",
                fillOpacity: 1,
                strokeColor: "#ffffff",
                strokeWeight: 2
            }
        });

        marker.addListener("click", () => {
            infoWindow.setContent(getStopInfo(stop, false));
            infoWindow.open(map, marker);
        });

        householdMarkers.push(marker);
    });
}

// Create blue numbered markers for a selected route.
function createRouteMarkers(stops) {
    routeMarkers.forEach(marker => marker.setMap(null));
    routeMarkers = [];

    stops.forEach(stop => {
        if (!hasValidCoordinates(stop)) return;

        const position = {
            lat: Number(stop.latitude),
            lng: Number(stop.longitude)
        };

        const marker = new google.maps.Marker({
            position: position,
            map: null,
            title: `Stop ${stop.stop_order}: ${stop.name}`,
            label: {
                text: String(stop.stop_order),
                color: "#ffffff",
                fontWeight: "bold",
                fontSize: "11px"
            },
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 11,
                fillColor: "#1976d2",
                fillOpacity: 1,
                strokeColor: "#ffffff",
                strokeWeight: 2
            }
        });

        marker.addListener("click", () => {
            infoWindow.setContent(getStopInfo(stop, true));
            infoWindow.open(map, marker);
        });

        routeMarkers.push(marker);
    });
}

// Remove any previous driving route line.
function clearRoutePolylines() {
    routePolylines.forEach(polyline => polyline.setMap(null));
    routePolylines = [];
}

// Fit the map around the visible markers.
function fitToMarkers(markers) {
    if (!markers.length) return;

    const bounds = new google.maps.LatLngBounds();

    markers.forEach(marker => {
        bounds.extend(marker.getPosition());
    });

    if (markers.length === 1) {
        map.setCenter(bounds.getCenter());
        map.setZoom(16);
    } else {
        map.fitBounds(bounds);
    }
}

// Display all households using green circular markers.
function showAllStops() {
    selectedRouteId = null;

    clearRoutePolylines();

    routeMarkers.forEach(marker => marker.setMap(null));
    householdMarkers.forEach(marker => marker.setMap(map));

    infoWindow.close();

    fitToMarkers(householdMarkers);

    document.getElementById("routeStatus").textContent =
        `${householdMarkers.length} household stop(s) displayed using green markers.`;
}

// Display only the selected route using blue numbered markers.
function showSelectedRouteStops(routeId = selectedRouteId) {
    if (!routeId) {
        document.getElementById("routeStatus").textContent =
            "Please select a route first using its View Driving Route button.";
        return;
    }

    selectedRouteId = Number(routeId);

    const stops = regularStops
        .filter(stop => stop.route_id === selectedRouteId)
        .sort((a, b) => a.stop_order - b.stop_order)
        .filter(hasValidCoordinates);

    if (!stops.length) {
        document.getElementById("routeStatus").textContent =
            "No household stops with valid GPS coordinates were found for this route.";
        return;
    }

    clearRoutePolylines();

    householdMarkers.forEach(marker => marker.setMap(null));

    createRouteMarkers(stops);

    routeMarkers.forEach(marker => marker.setMap(map));

    infoWindow.close();

    fitToMarkers(routeMarkers);

    document.getElementById("routeStatus").textContent =
        `Route ${selectedRouteId}: ${routeMarkers.length} numbered household stop(s) displayed.`;
}

// Calculate and display the selected route from the collector's GPS location.
async function showRegularRoute(routeId) {
    const message = document.getElementById("routeStatus");

    const stops = regularStops
        .filter(stop => stop.route_id === Number(routeId))
        .sort((a, b) => a.stop_order - b.stop_order)
        .filter(hasValidCoordinates);

    if (!stops.length) {
        message.textContent =
            "No household stops with valid GPS coordinates were found for this route.";
        return;
    }

    // Display the selected route's blue numbered markers first.
    showSelectedRouteStops(Number(routeId));

    if (!currentPosition) {
        message.textContent =
            "Route stops are displayed, but your current GPS location is unavailable. Allow location access to calculate driving directions.";
        return;
    }

    message.textContent = "Calculating regular driving route...";

    const destinationStop = stops[stops.length - 1];

    const request = {
        origin: currentPosition,

        destination: {
            lat: Number(destinationStop.latitude),
            lng: Number(destinationStop.longitude)
        },

        intermediates: stops.slice(0, -1).map(stop => ({
            location: {
                lat: Number(stop.latitude),
                lng: Number(stop.longitude)
            }
        })),

        travelMode: "DRIVING",

        fields: ["path"]
    };

    try {
        const { routes } = await Route.computeRoutes(request);

        if (!routes || routes.length === 0) {
            message.textContent = "No driving route found.";
            return;
        }

        clearRoutePolylines();

        const polylines = routes[0].createPolylines();

        polylines.forEach(polyline => {
            polyline.setMap(map);
            routePolylines.push(polyline);
        });

        // Keep the blue numbered household markers visible.
        routeMarkers.forEach(marker => marker.setMap(map));

        if (currentLocationMarker) {
            currentLocationMarker.setMap(map);
        }

        message.textContent =
            "Driving route displayed successfully with numbered household stops.";

    } catch (error) {
        console.error("Regular Routes API error:", error);

        message.textContent =
            "Route error: " + (error.message || "Unable to calculate the driving route.");
    }
} 
</script>

<script async
    src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&loading=async&callback=initMap">
</script>
