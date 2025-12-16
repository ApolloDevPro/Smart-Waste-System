<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
include('../db_connect.php');

// Fetch all requests with user locations
$all_requests = $conn->query("
    SELECT wr.request_id, wr.waste_type, wr.quantity_kg, wr.address, wr.status, wr.request_date,
           u.full_name, u.phone, u.email,
           a.assignment_id, a.status as assignment_status,
           s.name as staff_name, t.truck_number
    FROM waste_requests wr
    JOIN users u ON wr.user_id = u.user_id
    LEFT JOIN assignments a ON wr.request_id = a.request_id
    LEFT JOIN staff s ON a.staff_id = s.staff_id
    LEFT JOIN trucks t ON a.truck_id = t.truck_id
    WHERE wr.status IN ('Pending', 'Approved', 'In Progress')
    ORDER BY wr.request_date DESC
");

$total_locations = $all_requests->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Locations Map - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard-custom.css">
    <link rel="stylesheet" href="../assets/css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        main { padding: 20px; max-width: 100%; }
        
        #admin-locations-map {
            height: 700px;
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
            margin-bottom: 20px;
        }
        
        .locations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .location-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        
        .location-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .location-item.pending { border-left-color: #ffc107; }
        .location-item.approved { border-left-color: #28a745; }
        .location-item.in-progress { border-left-color: #17a2b8; }
        
        .location-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #eee;
        }
        
        .location-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #11998e;
        }
        
        .location-details {
            font-size: 0.9em;
            line-height: 1.8;
            margin-bottom: 10px;
        }
        
        .location-details div {
            margin: 4px 0;
        }
        
        .nav-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .nav-button {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.85em;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .nav-button.maps { background: #4285f4; color: white; }
        .nav-button.waze { background: #33ccff; color: white; }
        .nav-button.call { background: #27ae60; color: white; }
        
        .nav-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 10px 20px;
            background: #f0f0f0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .filter-tab.active {
            background: #11998e;
            color: white;
        }
        
        .filter-tab:hover {
            background: #0e7a6e;
            color: white;
        }
    </style>
</head>

<body>
    <header class="dashboard-header">
        <div class="header-top">
            <div class="header-left">
                <h1><i class="fas fa-map-marked-alt"></i> All User Locations</h1>
                <div class="admin-info">Welcome Admin <?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></div>
            </div>
            <div class="dashboard-clock" id="dashboardClock"></div>
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">0</span>
            </div>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <!-- Notification Dropdown -->
        <div class="notification-dropdown">
            <div class="notification-permission-banner">
                <span class="permission-text">
                    <i class="fas fa-info-circle"></i> Enable browser notifications for real-time alerts
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
        
        <div class="header-top">
        <nav class="admin-nav">
            <button class="nav-btn" onclick="location.href='dashboard.php'"><i class="fas fa-home"></i> Dashboard</button>
            <button class="nav-btn" onclick="location.href='manage_trucks.php'"><i class="fas fa-truck"></i> Trucks</button>
            <button class="nav-btn" onclick="location.href='manage_staff.php'"><i class="fas fa-users"></i> Staff</button>
            <button class="nav-btn" onclick="location.href='assign_tast.php'"><i class="fas fa-tasks"></i> Assign Tasks</button>
            <button class="nav-btn" onclick="location.href='view_requests.php'"><i class="fas fa-list"></i> Requests</button>
            <button class="nav-btn active" onclick="location.href='locations_map.php'"><i class="fas fa-map-marked-alt"></i> Locations</button>
            <button class="nav-btn" onclick="location.href='reports.php'"><i class="fas fa-chart-bar"></i> Reports</button>
        </nav>
    </header>

    <main>
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h2 style="margin: 0;"><i class="fas fa-map-marked-alt"></i> All User Request Locations (<?= $total_locations ?>)</h2>
                <button onclick="centerMap()" style="padding: 10px 20px; background: #11998e; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    <i class="fas fa-crosshairs"></i> Center Map
                </button>
            </div>
            
            <div class="filter-tabs">
                <button class="filter-tab active" onclick="filterMap('all')">All (<?= $total_locations ?>)</button>
                <button class="filter-tab" onclick="filterMap('Pending')">Pending</button>
                <button class="filter-tab" onclick="filterMap('Approved')">Approved</button>
                <button class="filter-tab" onclick="filterMap('In Progress')">In Progress</button>
            </div>
            
            <?php if ($total_locations > 0): ?>
                <div id="admin-locations-map"></div>
                
                <h3 style="margin-top: 30px; margin-bottom: 15px;"><i class="fas fa-list"></i> All Locations</h3>
                <div class="locations-grid">
                    <?php 
                    $all_requests->data_seek(0);
                    $index = 1;
                    while ($loc = $all_requests->fetch_assoc()): 
                        $encoded_address = urlencode($loc['address']);
                        $google_maps_url = "https://www.google.com/maps/search/?api=1&query=" . $encoded_address;
                        $waze_url = "https://www.waze.com/ul?q=" . $encoded_address;
                        $status_class = strtolower(str_replace(' ', '-', $loc['status']));
                    ?>
                        <div class="location-item <?= $status_class ?>" data-status="<?= $loc['status'] ?>">
                            <div class="location-header">
                                <div class="location-title">
                                    <?= $index ?>. Request #<?= $loc['request_id'] ?>
                                </div>
                                <span class="status-badge status-<?= str_replace(' ', '.', $loc['status']) ?>">
                                    <?= $loc['status'] ?>
                                </span>
                            </div>
                            <div class="location-details">
                                <div><i class="fas fa-user"></i> <strong><?= htmlspecialchars($loc['full_name']) ?></strong></div>
                                <div><i class="fas fa-phone"></i> <?= htmlspecialchars($loc['phone']) ?></div>
                                <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($loc['email']) ?></div>
                                <div><i class="fas fa-recycle"></i> <?= htmlspecialchars($loc['waste_type']) ?> (<?= number_format($loc['quantity_kg'], 1) ?> kg)</div>
                                <div><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($loc['address']) ?></div>
                                <div><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($loc['request_date'])) ?></div>
                                <?php if ($loc['staff_name']): ?>
                                    <div><i class="fas fa-user-tie"></i> Assigned to: <?= htmlspecialchars($loc['staff_name']) ?> (<?= htmlspecialchars($loc['truck_number']) ?>)</div>
                                <?php else: ?>
                                    <div style="color: #e67e22;"><i class="fas fa-exclamation-triangle"></i> Not yet assigned</div>
                                <?php endif; ?>
                            </div>
                            <div class="nav-buttons">
                                <a href="<?= $google_maps_url ?>" target="_blank" class="nav-button maps">
                                    <i class="fas fa-map"></i> Google Maps
                                </a>
                                <a href="<?= $waze_url ?>" target="_blank" class="nav-button waze">
                                    <i class="fas fa-route"></i> Waze
                                </a>
                                <a href="tel:<?= htmlspecialchars($loc['phone']) ?>" class="nav-button call">
                                    <i class="fas fa-phone-alt"></i> Call User
                                </a>
                            </div>
                        </div>
                    <?php 
                        $index++;
                    endwhile; 
                    ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 80px 20px;">
                    <i class="fas fa-map-marked-alt" style="font-size: 5rem; color: #ddd; margin-bottom: 20px;"></i>
                    <h3>No Active Requests</h3>
                    <p>There are no pending, approved, or in-progress requests with locations to display.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function updateClock() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const date = now.toLocaleDateString(undefined, options);
            const time = now.toLocaleTimeString();
            document.getElementById('dashboardClock').innerHTML = `${date} | ${time}`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        let map;
        let markers = [];
        let bounds;
        let allMarkers = [];

        function initAdminMap() {
            bounds = new google.maps.LatLngBounds();
            
            map = new google.maps.Map(document.getElementById('admin-locations-map'), {
                zoom: 12,
                center: { lat: 0.3476, lng: 32.5825 },
                styles: [
                    {
                        featureType: "poi",
                        elementType: "labels",
                        stylers: [{ visibility: "off" }]
                    }
                ],
                mapTypeControl: true,
                fullscreenControl: true,
                streetViewControl: true,
                zoomControl: true
            });

            loadAllLocations();
        }

        function loadAllLocations() {
            const locations = <?= json_encode($all_requests->fetch_all(MYSQLI_ASSOC)) ?>;
            const geocoder = new google.maps.Geocoder();
            
            locations.forEach((loc, index) => {
                geocoder.geocode({ address: loc.address }, (results, status) => {
                    if (status === 'OK') {
                        const position = results[0].geometry.location;
                        bounds.extend(position);
                        
                        const markerColor = getStatusColor(loc.status);
                        
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
                                fillColor: markerColor,
                                fillOpacity: 0.9,
                                strokeColor: '#ffffff',
                                strokeWeight: 2,
                                scale: 14
                            }
                        });

                        const encodedAddress = encodeURIComponent(loc.address);
                        const googleMapsUrl = `https://www.google.com/maps/search/?api=1&query=${encodedAddress}`;
                        const wazeUrl = `https://www.waze.com/ul?q=${encodedAddress}`;
                        
                        const assignmentInfo = loc.staff_name ? 
                            `<p style="margin: 5px 0;"><strong><i class="fas fa-user-tie"></i> Assigned to:</strong> ${loc.staff_name} (${loc.truck_number})</p>` :
                            `<p style="margin: 5px 0; color: #e67e22;"><strong><i class="fas fa-exclamation-triangle"></i></strong> Not yet assigned</p>`;

                        const infoContent = `
                            <div style="min-width: 280px; font-family: Arial, sans-serif;">
                                <h3 style="margin: 0 0 12px 0; color: #11998e; border-bottom: 2px solid #11998e; padding-bottom: 6px;">
                                    ${index + 1}. Request #${loc.request_id}
                                </h3>
                                <p style="margin: 5px 0;"><strong><i class="fas fa-user"></i> Customer:</strong> ${loc.full_name}</p>
                                <p style="margin: 5px 0;"><strong><i class="fas fa-phone"></i> Phone:</strong> <a href="tel:${loc.phone}">${loc.phone}</a></p>
                                <p style="margin: 5px 0;"><strong><i class="fas fa-envelope"></i> Email:</strong> ${loc.email}</p>
                                <p style="margin: 5px 0;"><strong><i class="fas fa-map-marker-alt"></i> Address:</strong><br>${loc.address}</p>
                                <p style="margin: 5px 0;"><strong><i class="fas fa-recycle"></i> Waste:</strong> ${loc.waste_type} (${Number(loc.quantity_kg).toFixed(1)} kg)</p>
                                <p style="margin: 5px 0;"><strong><i class="fas fa-info-circle"></i> Status:</strong> <span class="status-badge status-${loc.status.replace(' ', '.')}">${loc.status}</span></p>
                                ${assignmentInfo}
                                <p style="margin: 5px 0;"><strong><i class="fas fa-calendar"></i> Date:</strong> ${new Date(loc.request_date).toLocaleDateString()}</p>
                                <div style="margin-top: 12px; display: flex; gap: 5px; flex-wrap: wrap;">
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

                        allMarkers.push({
                            marker: marker,
                            status: loc.status,
                            data: loc
                        });
                        
                        if (index === locations.length - 1) {
                            map.fitBounds(bounds);
                        }
                    }
                });
            });
        }

        function getStatusColor(status) {
            const colors = {
                'Pending': '#ffc107',
                'Approved': '#28a745',
                'In Progress': '#17a2b8',
                'Collected': '#6c757d',
                'Rejected': '#dc3545'
            };
            return colors[status] || '#17a2b8';
        }

        function filterMap(status) {
            // Update active tab
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filter markers
            allMarkers.forEach(item => {
                if (status === 'all' || item.status === status) {
                    item.marker.setVisible(true);
                } else {
                    item.marker.setVisible(false);
                }
            });
            
            // Filter location cards
            document.querySelectorAll('.location-item').forEach(card => {
                const cardStatus = card.getAttribute('data-status');
                if (status === 'all' || cardStatus === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Recalculate bounds for visible markers
            const newBounds = new google.maps.LatLngBounds();
            allMarkers.forEach(item => {
                if (item.marker.getVisible()) {
                    newBounds.extend(item.marker.getPosition());
                }
            });
            if (!newBounds.isEmpty()) {
                map.fitBounds(newBounds);
            }
        }

        function centerMap() {
            if (bounds && !bounds.isEmpty()) {
                map.fitBounds(bounds);
            }
        }
    </script>
    <script async src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&callback=initAdminMap"></script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
