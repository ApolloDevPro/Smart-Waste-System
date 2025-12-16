<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}
include('../db_connect.php');

$user_id = $_SESSION['user_id'];

// Fetch staff's truck details
$truck_query = $conn->query("
    SELECT t.*, s.name as assigned_staff
    FROM staff s
    JOIN trucks t ON s.truck_id = t.truck_id
    WHERE s.staff_id = $user_id
");
$truck = $truck_query->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Truck - Smart Waste Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/truck-info.css">
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
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="my_assignments.php" class="nav-item">
                <i class="fas fa-tasks"></i>
                <span>My Assignments</span>
            </a>
            <a href="my_truck.php" class="nav-item active">
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
                <h1>My Truck Information</h1>
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

        <div class="dashboard-container">
            <?php if ($truck): ?>
                <div class="card truck-info-card">
                    <div class="card-header">
                        <h2><i class="fas fa-truck"></i> My Truck Information</h2>
                    </div>
                    <div class="card-body">
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
    </main>
</body>

</html>