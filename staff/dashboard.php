<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}
include('../db_connect.php');
require_once('../includes/Notification.php');

$user_id = $_SESSION['user_id'];

// Check if staff record exists, if not create one
$staff_check = $conn->query("SELECT * FROM staff WHERE staff_id = $user_id");
if ($staff_check->num_rows === 0) {
    // Create staff record if it doesn't exist
    $user_info = $conn->query("SELECT full_name, phone FROM users WHERE user_id = $user_id")->fetch_assoc();
    $conn->query("INSERT INTO staff (staff_id, name, phone, position, status) VALUES ($user_id, '{$user_info['full_name']}', '{$user_info['phone']}', 'Collection Staff', 'Active')");
}

// Fetch staff's details
$staff = $conn->query("SELECT * FROM staff WHERE staff_id = $user_id")->fetch_assoc();
$truck_id = $staff['truck_id'];

// Fetch assignments with user contact details
$assignments = $conn->query("
    SELECT a.assignment_id, wr.request_id, wr.waste_type, wr.quantity_kg, wr.address, wr.status AS request_status, a.status AS assignment_status,
           u.full_name, u.phone, u.email
    FROM assignments a
    JOIN waste_requests wr ON a.request_id = wr.request_id
    JOIN users u ON wr.user_id = u.user_id
    WHERE a.staff_id = $user_id AND a.status IN ('Assigned', 'In Progress')
    ORDER BY a.assigned_date DESC
");

// Count statistics
$total_assigned = $conn->query("SELECT COUNT(*) as count FROM assignments WHERE staff_id = $user_id AND status = 'Assigned'")->fetch_assoc()['count'];
$in_progress = $conn->query("SELECT COUNT(*) as count FROM assignments WHERE staff_id = $user_id AND status = 'In Progress'")->fetch_assoc()['count'];
$completed_today = $conn->query("SELECT COUNT(*) as count FROM assignments WHERE staff_id = $user_id AND status = 'Completed' AND DATE(assigned_date) = CURDATE()")->fetch_assoc()['count'];

// Fetch truck details if assigned
$truck = null;
if ($truck_id) {
    $truck = $conn->query("SELECT * FROM trucks WHERE truck_id = $truck_id")->fetch_assoc();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $assignment_id = $_POST['assignment_id'];
    $new_status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE assignments SET status = ? WHERE assignment_id = ?");
    $stmt->bind_param("si", $new_status, $assignment_id);
    $stmt->execute();

    // Get assignment details for notifications
    $assignment_info = $conn->query("
        SELECT a.request_id, wr.user_id, wr.waste_type, wr.quantity_kg, wr.address,
               s.name as staff_name, u.full_name as user_name
        FROM assignments a
        JOIN waste_requests wr ON a.request_id = wr.request_id
        JOIN users u ON wr.user_id = u.user_id
        JOIN staff s ON a.staff_id = s.staff_id
        WHERE a.assignment_id = $assignment_id
    ")->fetch_assoc();

    $notif = new Notification($conn);

    if ($new_status === 'Completed') {
        $conn->query("UPDATE waste_requests SET status = 'Collected' WHERE request_id = (SELECT request_id FROM assignments WHERE assignment_id = $assignment_id)");
        
        // Notify user
        $notif->create(
            $assignment_info['user_id'],
            "Collection Completed",
            "Your waste has been collected successfully by {$assignment_info['staff_name']}. Request #{$assignment_info['request_id']} is now complete.",
            'request',
            $assignment_info['request_id'],
            '../user/view_requests.php'
        );
        
        // Notify all admins
        $notif->createForRole(
            'admin',
            "Collection Completed by Staff",
            "{$assignment_info['staff_name']} completed collection for {$assignment_info['user_name']} - {$assignment_info['waste_type']} ({$assignment_info['quantity_kg']} kg).",
            'assignment',
            $assignment_info['request_id']
        );
        
        // Send email to user
        $notif->sendEmail(
            $assignment_info['user_id'],
            "Waste Collection Completed",
            "Your waste collection has been completed successfully!\n\nRequest ID: #{$assignment_info['request_id']}\nCollected by: {$assignment_info['staff_name']}\nWaste Type: {$assignment_info['waste_type']}\nQuantity: {$assignment_info['quantity_kg']} kg\n\nThank you for using our service!"
        );
    } elseif ($new_status === 'In Progress') {
        // Notify user that collection is in progress
        $notif->create(
            $assignment_info['user_id'],
            "Collection In Progress",
            "{$assignment_info['staff_name']} has started working on your request #{$assignment_info['request_id']}. They're on their way!",
            'assignment',
            $assignment_info['request_id'],
            '../user/view_requests.php'
        );
        
        // Notify admins
        $notif->createForRole(
            'admin',
            "Staff Started Collection",
            "{$assignment_info['staff_name']} started collection for request #{$assignment_info['request_id']} ({$assignment_info['user_name']}).",
            'assignment',
            $assignment_info['request_id']
        );
    }

    header("Location: dashboard.php?success=1");
    exit();
}

$success_msg = isset($_GET['success']) ? "Status updated successfully!" : "";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Smart Waste Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/truck-info.css">
    <link rel="stylesheet" href="../assets/css/table-controls.css">
    <link rel="stylesheet" href="../assets/css/map-view.css">
    <link rel="stylesheet" href="../assets/css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-tasks"></i>
                <span>My Assignments</span>
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-truck"></i>
                <span>My Truck</span>
            </a>
            <a href="#" class="nav-item">
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

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <header class="top-bar">
            <div class="top-bar-left">
                <button class="menu-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>Staff Dashboard</h1>
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

        <!-- Dashboard Content -->
        <div class="dashboard-container">
            <?php if ($success_msg): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $total_assigned ?></h3>
                        <p>Assigned Tasks</p>
                    </div>
                </div>

                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $in_progress ?></h3>
                        <p>In Progress</p>
                    </div>
                </div>

                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $completed_today ?></h3>
                        <p>Completed Today</p>
                    </div>
                </div>

                <div class="stat-card stat-info">
                    <div class="stat-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $truck ? htmlspecialchars($truck['truck_number']) : 'N/A' ?></h3>
                        <p>Assigned Truck</p>
                    </div>
                </div>
            </div>

            <!-- Truck Information Card -->
            <div class="card truck-info-card">
                <div class="card-header">
                    <h2><i class="fas fa-truck"></i> Assigned Truck Information</h2>
                </div>
                <div class="card-body">
                    <?php if ($truck): ?>
                        <div class="truck-details">
                            <!-- Basic Information -->
                            <div class="truck-detail-group">
                                <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                                <div class="detail-grid">
                                    <div class="truck-detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-hashtag"></i>
                                        </div>
                                        <div class="detail-content">
                                            <span class="detail-label">Truck Number</span>
                                            <span class="detail-value"><?= htmlspecialchars($truck['truck_number']) ?></span>
                                        </div>
                                    </div>
                                    <div class="truck-detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-truck"></i>
                                        </div>
                                        <div class="detail-content">
                                            <span class="detail-label">Vehicle Type</span>
                                            <span class="detail-value"><?= htmlspecialchars($truck['vehicle_type'] ?? 'Standard Waste Truck') ?></span>
                                        </div>
                                    </div>
                                    <div class="truck-detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div class="detail-content">
                                            <span class="detail-label">Manufacture Year</span>
                                            <span class="detail-value"><?= $truck['manufacture_year'] ?? 'N/A' ?></span>
                                        </div>
                                    </div>
                                    <div class="truck-detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-weight"></i>
                                        </div>
                                        <div class="detail-content">
                                            <span class="detail-label">Capacity</span>
                                            <span class="detail-value"><?= number_format($truck['capacity_kg'], 2) ?> kg</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Operational Status -->
                            <div class="truck-detail-group">
                                <h3><i class="fas fa-cog"></i> Operational Status</h3>
                                <div class="detail-grid">
                                    <div class="truck-detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-info-circle"></i>
                                        </div>
                                        <div class="detail-content">
                                            <span class="detail-label">Current Status</span>
                                            <span class="detail-value status-badge status-<?= strtolower($truck['status']) ?>">
                                                <?= htmlspecialchars($truck['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="truck-detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-gas-pump"></i>
                                        </div>
                                        <div class="detail-content">
                                            <span class="detail-label">Fuel Level</span>
                                            <div class="progress-bar">
                                                <div class="progress" style="width: <?= $truck['fuel_level'] ?? 0 ?>%"></div>
                                            </div>
                                            <span class="detail-value"><?= number_format($truck['fuel_level'] ?? 0, 1) ?>%</span>
                                        </div>
                                    </div>
                                    <div class="truck-detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-road"></i>
                                        </div>
                                        <div class="detail-content">
                                            <span class="detail-label">Total Distance</span>
                                            <span class="detail-value"><?= number_format($truck['total_distance'] ?? 0, 2) ?> km</span>
                                        </div>
                                    </div>
                                    <div class="truck-detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </div>
                                        <div class="detail-content">
                                            <span class="detail-label">Current Location</span>
                                            <span class="detail-value"><?= htmlspecialchars($truck['current_location'] ?? 'Not Available') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Maintenance Information -->
                            <div class="truck-detail-group">
                                <h3><i class="fas fa-wrench"></i> Maintenance</h3>
                                <div class="detail-grid">
                                    <div class="truck-detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                        <div class="detail-content">
                                            <span class="detail-label">Last Maintenance</span>
                                            <span class="detail-value">
                                                <?= $truck['last_maintenance'] ? date('M d, Y', strtotime($truck['last_maintenance'])) : 'Not Available' ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="truck-detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div class="detail-content">
                                            <span class="detail-label">Next Maintenance</span>
                                            <span class="detail-value">
                                                <?= $truck['next_maintenance'] ? date('M d, Y', strtotime($truck['next_maintenance'])) : 'Not Scheduled' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($truck['maintenance_notes'])): ?>
                                    <div class="maintenance-notes">
                                        <h4>Maintenance Notes</h4>
                                        <p><?= nl2br(htmlspecialchars($truck['maintenance_notes'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Driver Information -->
                            <div class="truck-detail-group">
                                <h3><i class="fas fa-user"></i> Driver Information</h3>
                                <div class="detail-grid">
                                    <div class="truck-detail-item">
                                        <div class="detail-icon">
                                            <i class="fas fa-id-card"></i>
                                        </div>
                                        <div class="detail-content">
                                            <span class="detail-label">Driver Name</span>
                                            <span class="detail-value"><?= htmlspecialchars($truck['driver_name']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-truck-loading"></i>
                            <h3>No Truck Assigned</h3>
                            <p>Please contact your administrator to get a truck assigned to you.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Map View -->
            <div class="card map-section">
                <div class="card-header">
                    <h2><i class="fas fa-map-marked-alt"></i> Assignment Locations</h2>
                </div>
                <div class="card-body">
                    <div id="assignments-map"></div>
                </div>
            </div>

            <!-- Current Assignments -->
            <div class="card assignments-card">
                <div class="card-header">
                    <h2><i class="fas fa-tasks"></i> Current Assignments</h2>
                    <span class="badge"><?= $assignments->num_rows ?> Active</span>
                </div>
                <div class="card-body">
                    <?php if ($assignments->num_rows > 0): ?>
                        <!-- Search and Filter Controls -->
                        <div class="table-controls">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="assignment-search" placeholder="Search assignments...">
                            </div>
                            <div class="filter-controls">
                                <select id="filter-status" class="filter-select">
                                    <option value="">All Statuses</option>
                                    <option value="Assigned">Assigned</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                </select>
                                <select id="filter-waste-type" class="filter-select">
                                    <option value="">All Waste Types</option>
                                    <option value="Organic">Organic</option>
                                    <option value="Plastic">Plastic</option>
                                    <option value="Paper">Paper</option>
                                    <option value="Metal">Metal</option>
                                    <option value="Glass">Glass</option>
                                    <option value="E-waste">E-waste</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table sortable">
                                <thead>
                                    <tr>
                                        <th data-sortable data-sort="id">ID <i class="fas fa-sort"></i></th>
                                        <th>Customer</th>
                                        <th data-sortable data-sort="wasteType">Waste Type <i class="fas fa-sort"></i></th>
                                        <th data-sortable data-sort="quantity">Quantity <i class="fas fa-sort"></i></th>
                                        <th>Location & Navigation</th>
                                        <th data-sortable data-sort="assignmentStatus">Status <i class="fas fa-sort"></i></th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($a = $assignments->fetch_assoc()): 
                                        $encoded_address = urlencode($a['address']);
                                        $google_maps_url = "https://www.google.com/maps/search/?api=1&query=" . $encoded_address;
                                        $waze_url = "https://www.waze.com/ul?q=" . $encoded_address;
                                    ?>
                                        <tr>
                                            <td data-label="ID"><strong>#<?= $a['request_id'] ?></strong></td>
                                            <td data-label="Customer">
                                                <div style="line-height: 1.6;">
                                                    <strong><i class="fas fa-user"></i> <?= htmlspecialchars($a['full_name']) ?></strong><br>
                                                    <a href="tel:<?= htmlspecialchars($a['phone']) ?>" style="color: #11998e; text-decoration: none;">
                                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($a['phone']) ?>
                                                    </a>
                                                </div>
                                            </td>
                                            <td data-label="Waste Type">
                                                <span class="waste-type">
                                                    <i class="fas fa-recycle"></i>
                                                    <?= htmlspecialchars($a['waste_type']) ?>
                                                </span>
                                            </td>
                                            <td data-label="Quantity"><strong><?= number_format($a['quantity_kg'], 2) ?> kg</strong></td>
                                            <td data-label="Location" style="min-width: 250px;">
                                                <div class="location-info">
                                                    <div class="address-cell" style="margin-bottom: 8px;">
                                                        <i class="fas fa-map-marker-alt" style="color: #e74c3c;"></i>
                                                        <strong><?= htmlspecialchars($a['address']) ?></strong>
                                                    </div>
                                                    <div class="navigation-links" style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                        <a href="<?= $google_maps_url ?>" target="_blank" 
                                                           style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: #4285f4; color: white; border-radius: 5px; text-decoration: none; font-size: 0.85em;">
                                                            <i class="fas fa-map"></i> Google Maps
                                                        </a>
                                                        <a href="<?= $waze_url ?>" target="_blank"
                                                           style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: #33ccff; color: white; border-radius: 5px; text-decoration: none; font-size: 0.85em;">
                                                            <i class="fas fa-route"></i> Waze
                                                        </a>
                                                        <a href="tel:<?= htmlspecialchars($a['phone']) ?>"
                                                           style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: #27ae60; color: white; border-radius: 5px; text-decoration: none; font-size: 0.85em;">
                                                            <i class="fas fa-phone-alt"></i> Call
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                            <td data-label="Status">
                                                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $a['assignment_status'])) ?>">
                                                    <?= htmlspecialchars($a['assignment_status']) ?>
                                                </span>
                                            </td>
                                            <td data-label="Actions">
                                                <form method="post" class="status-form">
                                                    <input type="hidden" name="assignment_id" value="<?= $a['assignment_id'] ?>">
                                                    <select name="status" class="status-select">
                                                        <option value="Assigned" <?= $a['assignment_status'] === 'Assigned' ? 'selected' : '' ?>>Assigned</option>
                                                        <option value="In Progress" <?= $a['assignment_status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                                        <option value="Completed" <?= $a['assignment_status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                    </select>
                                                    <button type="submit" name="update_status" class="btn btn-update">
                                                        <i class="fas fa-save"></i> Update
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No Active Assignments</h3>
                            <p>You don't have any active assignments at the moment. Check back later or contact your administrator.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Sidebar toggle for mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');

        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('sidebar-active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                    mainContent.classList.remove('sidebar-active');
                }
            }
        });

        // Auto-hide success message
        setTimeout(() => {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }
        }, 3000);
    </script>
    }
    </script>
    <script src="../assets/js/assignments.js"></script>
    <script src="../assets/js/map-view.js"></script>
    <script async
        src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&callback=initMap">
    </script>
    <script>
        // Update date and time
        function updateDateTime() {
            const now = new Date();

            // Update date
            const dateOptions = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', dateOptions);

            // Update time
            const timeOptions = {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', timeOptions);
        }

        // Update immediately and then every second
        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>
    <script src="../assets/js/notifications.js"></script>
</body>

</html>