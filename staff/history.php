<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}
include('../db_connect.php');

$user_id = $_SESSION['user_id'];

// Fetch all completed assignments history
$history = $conn->query("
    SELECT a.assignment_id, a.assigned_date, a.status, 
           wr.request_id, wr.waste_type, wr.quantity_kg, wr.address,
           t.truck_number
    FROM assignments a
    JOIN waste_requests wr ON a.request_id = wr.request_id
    LEFT JOIN trucks t ON a.truck_id = t.truck_id
    WHERE a.staff_id = $user_id AND a.status = 'Completed'
    ORDER BY a.assigned_date DESC
");

// Get statistics
$total_collections = $conn->query("
    SELECT COUNT(*) as total,
           SUM(wr.quantity_kg) as total_weight
    FROM assignments a
    JOIN waste_requests wr ON a.request_id = wr.request_id
    WHERE a.staff_id = $user_id AND a.status = 'Completed'
")->fetch_assoc();

$monthly_collections = $conn->query("
    SELECT COUNT(*) as monthly_total,
           SUM(wr.quantity_kg) as monthly_weight
    FROM assignments a
    JOIN waste_requests wr ON a.request_id = wr.request_id
    WHERE a.staff_id = $user_id 
    AND a.status = 'Completed'
    AND MONTH(a.assigned_date) = MONTH(CURRENT_DATE())
    AND YEAR(a.assigned_date) = YEAR(CURRENT_DATE())
")->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Collection History - Smart Waste Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/table-controls.css">
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
            <a href="my_truck.php" class="nav-item">
                <i class="fas fa-truck"></i>
                <span>My Truck</span>
            </a>
            <a href="history.php" class="nav-item active">
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
                <h1>Collection History</h1>
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
            <!-- Statistics Summary -->
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <i class="fas fa-truck-loading"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= number_format($total_collections['total']) ?></h3>
                        <p>Total Collections</p>
                    </div>
                </div>

                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <i class="fas fa-weight-scale"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= number_format($total_collections['total_weight'], 2) ?> kg</h3>
                        <p>Total Weight Collected</p>
                    </div>
                </div>

                <div class="stat-card stat-info">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= number_format($monthly_collections['monthly_total']) ?></h3>
                        <p>Collections This Month</p>
                    </div>
                </div>

                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <i class="fas fa-scale-balanced"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= number_format($monthly_collections['monthly_weight'], 2) ?> kg</h3>
                        <p>Weight This Month</p>
                    </div>
                </div>
            </div>

            <!-- Collection History Table -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Collection History</h2>
                </div>
                <div class="card-body">
                    <div class="table-controls">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="history-search" placeholder="Search history...">
                        </div>
                        <div class="filter-controls">
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
                                    <th data-sortable data-sort="date">Date <i class="fas fa-sort"></i></th>
                                    <th data-sortable data-sort="request">Request ID <i class="fas fa-sort"></i></th>
                                    <th data-sortable data-sort="truck">Truck <i class="fas fa-sort"></i></th>
                                    <th data-sortable data-sort="type">Waste Type <i class="fas fa-sort"></i></th>
                                    <th data-sortable data-sort="quantity">Quantity <i class="fas fa-sort"></i></th>
                                    <th data-sortable data-sort="address">Collection Address <i class="fas fa-sort"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($record = $history->fetch_assoc()): ?>
                                    <tr>
                                        <td data-label="Date"><?= date('M d, Y', strtotime($record['assigned_date'])) ?></td>
                                        <td data-label="Request ID">#<?= $record['request_id'] ?></td>
                                        <td data-label="Truck"><?= htmlspecialchars($record['truck_number']) ?></td>
                                        <td data-label="Type">
                                            <span class="waste-type">
                                                <i class="fas fa-recycle"></i>
                                                <?= htmlspecialchars($record['waste_type']) ?>
                                            </span>
                                        </td>
                                        <td data-label="Quantity"><?= number_format($record['quantity_kg'], 2) ?> kg</td>
                                        <td data-label="Address">
                                            <div class="address-cell">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars($record['address']) ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/assignments.js"></script>
</body>

</html>