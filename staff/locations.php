<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}
include('../db_connect.php');

$user_id = $_SESSION['user_id'];

// Fetch all assignments with location data
$assignments = $conn->query("
    SELECT a.assignment_id, a.assigned_date, wr.request_id, wr.waste_type, 
           wr.quantity_kg, wr.address, wr.status AS request_status, 
           a.status AS assignment_status,
           u.full_name, u.phone, u.email
    FROM assignments a
    JOIN waste_requests wr ON a.request_id = wr.request_id
    JOIN users u ON wr.user_id = u.user_id
    WHERE a.staff_id = $user_id AND a.status IN ('Assigned', 'In Progress')
    ORDER BY a.assigned_date ASC
");

$assignment_count = $assignments->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Locations - Smart Waste Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #locations-map {
            height: 600px;
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .location-card {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid #11998e;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .location-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(5px);
        }
        
        .location-card h4 {
            margin: 0 0 10px 0;
            color: #11998e;
        }
        
        .location-info {
            font-size: 0.9em;
            line-height: 1.8;
        }
        
        .nav-buttons {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.85em;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .nav-btn.maps { background: #4285f4; color: white; }
        .nav-btn.waze { background: #33ccff; color: white; }
        .nav-btn.call { background: #27ae60; color: white; }
        
        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .sidebar-wrapper {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
            height: calc(100vh - 200px);
        }
        
        .locations-list {
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .locations-list::-webkit-scrollbar {
            width: 8px;
        }
        
        .locations-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .locations-list::-webkit-scrollbar-thumb {
            background: #11998e;
            border-radius: 4px;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-assigned { background: #17a2b8; }
        .status-in-progress { background: #ffc107; }
        
        @media (max-width: 992px) {
            .sidebar-wrapper {
                grid-template-columns: 1fr;
            }
            
            .locations-list {
                max-height: 400px;
            }
        }
    </style>
</head>

<body class="dashboard-page">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-recycle"></i>
                <span>SmartWaste</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="my_assignments.php" class="nav-item">
                <i class="fas fa-tasks"></i>
                <span>My Assignments</span>
            </a>
            <a href="locations.php" class="nav-item active">
                <i class="fas fa-map-marked-alt"></i>
                <span>Locations Map</span>
            </a>
            <a href="my_truck.php" class="nav-item">
                <i class="fas fa-truck"></i>
                <span>My Truck</span>
            </a>
            <a href="history.php" class="nav-item">
                <i class="fas fa-history"></i>
                <span>History</span>
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php" class="nav-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <!-- Top Bar -->
        <header class="top-bar">
            <div class="top-bar-left">
                <button class="menu-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1><i class="fas fa-map-marked-alt"></i> Assignment Locations</h1>
            </div>
            <div class="top-bar-right">
                <div class="datetime-display">
                    <div class="date">
                        <i class="fas fa-calendar-alt"></i>
                        <span id="currentDate">Loading...</span>
                    </div>
                    <div class="time">
                        <i class="fas fa-clock"></i>
                        <span id="currentTime">Loading...</span>
                    </div>
                </div>
                <div class="notification-bell">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">0</span>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></span>
                        <span class="user-role">Collection Staff</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Notification Dropdown -->
        <div class="notification-dropdown">
            <div class="notification-permission-banner">
                <span class="permission-text">
                    <i class="fas fa-info-circle"></i> Enable browser notifications for assignment alerts
                </span>
                <button class="enable-notifications-btn">Enable</button>
            </div>
            <div class="notification-header">
                <h3><i class="fas fa-bell"></i> Notifications</h3>
                <button class="mark-all-read">Mark all read</button>
            </div>
            <div class="notification-list">
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>Loading notifications...</p>
                </div>
            </div>
        </div>

        <div class="dashboard-container">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-map-marked-alt"></i> All Assignment Locations (<?= $assignment_count ?>)</h2>
                    <button onclick="centerMap()" style="padding: 8px 15px; background: #11998e; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-crosshairs"></i> Center Map
                    </button>
                </div>
                <div class="card-body">
                    <?php if ($assignment_count > 0): ?>
                        <div class="sidebar-wrapper">
                            <!-- Locations List -->
                            <div class="locations-list">
                                <?php 
                                $assignments->data_seek(0); // Reset pointer
                                $location_index = 1;
                                while ($loc = $assignments->fetch_assoc()): 
                                    $encoded_address = urlencode($loc['address']);
                                    $google_maps_url = "https://www.google.com/maps/search/?api=1&query=" . $encoded_address;
                                    $waze_url = "https://www.waze.com/ul?q=" . $encoded_address;
                                    $status_class = strtolower(str_replace(' ', '-', $loc['assignment_status']));
                                ?>
                                    <div class="location-card" data-request-id="<?= $loc['request_id'] ?>">
                                        <h4>
                                            <span class="status-indicator status-<?= $status_class ?>"></span>
                                            <?= $location_index ?>. Request #<?= $loc['request_id'] ?>
                                        </h4>
                                        <div class="location-info">
                                            <div><i class="fas fa-user"></i> <strong><?= htmlspecialchars($loc['full_name']) ?></strong></div>
                                            <div><i class="fas fa-phone"></i> <?= htmlspecialchars($loc['phone']) ?></div>
                                            <div><i class="fas fa-recycle"></i> <?= htmlspecialchars($loc['waste_type']) ?> (<?= number_format($loc['quantity_kg'], 1) ?> kg)</div>
                                            <div><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($loc['address']) ?></div>
                                            <div><i class="fas fa-info-circle"></i> Status: <strong><?= $loc['assignment_status'] ?></strong></div>
                                        </div>
                                        <div class="nav-buttons">
                                            <a href="<?= $google_maps_url ?>" target="_blank" class="nav-btn maps">
                                                <i class="fas fa-map"></i> Google Maps
                                            </a>
                                            <a href="<?= $waze_url ?>" target="_blank" class="nav-btn waze">
                                                <i class="fas fa-route"></i> Waze
                                            </a>
                                            <a href="tel:<?= htmlspecialchars($loc['phone']) ?>" class="nav-btn call">
                                                <i class="fas fa-phone-alt"></i> Call
                                            </a>
                                        </div>
                                    </div>
                                <?php 
                                    $location_index++;
                                endwhile; 
                                ?>
                            </div>

                            <!-- Map -->
                            <div>
                                <div id="locations-map"></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                            <i class="fas fa-map-marked-alt" style="font-size: 4rem; color: #ddd; margin-bottom: 20px;"></i>
                            <h3>No Active Assignments</h3>
                            <p>You don't have any active assignments with locations to display.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Sidebar toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }

        // Update date and time
        function updateDateTime() {
            const now = new Date();
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
            
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', dateOptions);
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', timeOptions);
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Map initialization
        let map;
        let markers = [];
        let bounds;

        function initLocationsMap() {
            bounds = new google.maps.LatLngBounds();
            
            map = new google.maps.Map(document.getElementById('locations-map'), {
                zoom: 12,
                center: { lat: 0.3476, lng: 32.5825 }, // Kampala coordinates
                styles: [
                    {
                        featureType: "poi",
                        elementType: "labels",
                        stylers: [{ visibility: "off" }]
                    }
                ],
                mapTypeControl: true,
                fullscreenControl: true,
                streetViewControl: true
            });

            loadLocations();
        }

        function loadLocations() {
            const locations = <?= json_encode($assignments->fetch_all(MYSQLI_ASSOC)) ?>;
            const geocoder = new google.maps.Geocoder();
            
            locations.forEach((loc, index) => {
                geocoder.geocode({ address: loc.address }, (results, status) => {
                    if (status === 'OK') {
                        const position = results[0].geometry.location;
                        bounds.extend(position);
                        
                        const marker = new google.maps.Marker({
                            position: position,
                            map: map,
                            title: `Request #${loc.request_id}`,
                            label: {
                                text: String(index + 1),
                                color: 'white',
                                fontWeight: 'bold'
                            },
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                fillColor: loc.assignment_status === 'In Progress' ? '#ffc107' : '#17a2b8',
                                fillOpacity: 0.9,
                                strokeColor: '#ffffff',
                                strokeWeight: 2,
                                scale: 12
                            }
                        });

                        const encodedAddress = encodeURIComponent(loc.address);
                        const googleMapsUrl = `https://www.google.com/maps/search/?api=1&query=${encodedAddress}`;
                        const wazeUrl = `https://www.waze.com/ul?q=${encodedAddress}`;

                        const infoContent = `
                            <div style="min-width: 250px; font-family: Arial, sans-serif;">
                                <h3 style="margin: 0 0 10px 0; color: #11998e; border-bottom: 2px solid #11998e; padding-bottom: 5px;">
                                    ${index + 1}. Request #${loc.request_id}
                                </h3>
                                <p style="margin: 5px 0;"><strong><i class="fas fa-user"></i> Customer:</strong> ${loc.full_name}</p>
                                <p style="margin: 5px 0;"><strong><i class="fas fa-phone"></i> Phone:</strong> <a href="tel:${loc.phone}">${loc.phone}</a></p>
                                <p style="margin: 5px 0;"><strong><i class="fas fa-map-marker-alt"></i> Address:</strong><br>${loc.address}</p>
                                <p style="margin: 5px 0;"><strong><i class="fas fa-recycle"></i> Waste:</strong> ${loc.waste_type} (${Number(loc.quantity_kg).toFixed(1)} kg)</p>
                                <p style="margin: 5px 0;"><strong><i class="fas fa-info-circle"></i> Status:</strong> ${loc.assignment_status}</p>
                                <div style="margin-top: 10px; display: flex; gap: 5px; flex-wrap: wrap;">
                                    <a href="${googleMapsUrl}" target="_blank" style="display: inline-flex; align-items: center; gap: 5px; padding: 8px 12px; background: #4285f4; color: white; text-decoration: none; border-radius: 5px; font-size: 0.9em;">
                                        <i class="fas fa-map"></i> Navigate
                                    </a>
                                    <a href="${wazeUrl}" target="_blank" style="display: inline-flex; align-items: center; gap: 5px; padding: 8px 12px; background: #33ccff; color: white; text-decoration: none; border-radius: 5px; font-size: 0.9em;">
                                        <i class="fas fa-route"></i> Waze
                                    </a>
                                    <a href="tel:${loc.phone}" style="display: inline-flex; align-items: center; gap: 5px; padding: 8px 12px; background: #27ae60; color: white; text-decoration: none; border-radius: 5px; font-size: 0.9em;">
                                        <i class="fas fa-phone-alt"></i> Call
                                    </a>
                                </div>
                            </div>
                        `;

                        const infoWindow = new google.maps.InfoWindow({
                            content: infoContent
                        });

                        marker.addListener('click', () => {
                            infoWindow.open(map, marker);
                        });

                        markers.push(marker);
                        
                        // Fit map to show all markers
                        if (index === locations.length - 1) {
                            map.fitBounds(bounds);
                        }
                    }
                });
            });
        }

        function centerMap() {
            if (bounds) {
                map.fitBounds(bounds);
            }
        }
    </script>
    <script async src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&callback=initLocationsMap"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
