<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
include('../db_connect.php');

// Fetch dashboard statistics
$total_requests = $conn->query('SELECT COUNT(*) AS c FROM waste_requests')->fetch_assoc()['c'] ?? 0;
$pending_requests = $conn->query("SELECT COUNT(*) AS c FROM waste_requests WHERE status='Pending'")->fetch_assoc()['c'] ?? 0;
$total_collected = $conn->query("SELECT IFNULL(SUM(quantity_kg),0) AS s FROM waste_requests WHERE status='Collected'")->fetch_assoc()['s'] ?? 0;
$total_payments = $conn->query('SELECT IFNULL(SUM(amount),0) AS s FROM payments')->fetch_assoc()['s'] ?? 0;
$total_users = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='user'")->fetch_assoc()['c'] ?? 0;
$active_staff = $conn->query("SELECT COUNT(*) AS c FROM staff WHERE status='Active'")->fetch_assoc()['c'] ?? 0;
$available_trucks = $conn->query("SELECT COUNT(*) AS c FROM trucks WHERE status='Available'")->fetch_assoc()['c'] ?? 0;
$busy_trucks = $conn->query("SELECT COUNT(*) AS c FROM trucks WHERE status='Busy'")->fetch_assoc()['c'] ?? 0;

// Fetch recent requests
$requests_res = $conn->query("
    SELECT wr.request_id, u.full_name, wr.waste_type, wr.quantity_kg, wr.address, wr.status, wr.request_date
    FROM waste_requests wr
    JOIN users u ON wr.user_id = u.user_id
    ORDER BY wr.request_date DESC
    LIMIT 8
");

// Fetch chart data for waste types
$waste_types = $conn->query("SELECT waste_type, COUNT(*) AS count FROM waste_requests GROUP BY waste_type")->fetch_all(MYSQLI_ASSOC);

// Fetch status distribution
$status_data = $conn->query("SELECT status, COUNT(*) AS count FROM waste_requests GROUP BY status")->fetch_all(MYSQLI_ASSOC);

// Fetch recent payments
$recent_payments = $conn->query("
    SELECT p.payment_id, u.full_name, p.amount, p.payment_method, p.payment_status, p.payment_date
    FROM payments p
    JOIN users u ON p.user_id = u.user_id
    ORDER BY p.payment_date DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard-custom.css">
    <link rel="stylesheet" href="../assets/css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <header class="dashboard-header">
        <div class="header-top">
            <div class="header-left">
                <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
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
            <button class="nav-btn active" onclick="location.href='dashboard.php'"><i class="fas fa-home"></i> Dashboard</button>
            <button class="nav-btn" onclick="location.href='manage_trucks.php'"><i class="fas fa-truck"></i> Trucks</button>
            <button class="nav-btn" onclick="location.href='manage_staff.php'"><i class="fas fa-users"></i> Staff</button>
            <button class="nav-btn" onclick="location.href='assign_tast.php'"><i class="fas fa-tasks"></i> Assign Tasks</button>
            <button class="nav-btn" onclick="location.href='view_requests.php'"><i class="fas fa-list"></i> Requests</button>
            <button class="nav-btn" onclick="location.href='locations_map.php'"><i class="fas fa-map-marked-alt"></i> Locations</button>
            <button class="nav-btn" onclick="location.href='reports.php'"><i class="fas fa-chart-bar"></i> Reports</button>
        </nav>
    </header>

    <main>
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card card-requests">
                <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($total_requests) ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>
            <div class="stat-card card-pending">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($pending_requests) ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
            <div class="stat-card card-collected">
                <div class="stat-icon"><i class="fas fa-recycle"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($total_collected, 1) ?> kg</h3>
                    <p>Total Collected</p>
                </div>
            </div>
            <div class="stat-card card-payments">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-content">
                    <h3>UGX <?= number_format($total_payments, 0) ?></h3>
                    <p>Total Payments</p>
                </div>
            </div>
            <div class="stat-card card-users">
                <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($total_users) ?></h3>
                    <p>Registered Users</p>
                </div>
            </div>
            <div class="stat-card card-staff">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($active_staff) ?></h3>
                    <p>Active Staff</p>
                </div>
            </div>
            <div class="stat-card card-trucks-available">
                <div class="stat-icon"><i class="fas fa-truck-loading"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($available_trucks) ?></h3>
                    <p>Available Trucks</p>
                </div>
            </div>
            <div class="stat-card card-trucks-busy">
                <div class="stat-icon"><i class="fas fa-truck-moving"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($busy_trucks) ?></h3>
                    <p>Busy Trucks</p>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="content-grid">
            <div class="chart-card">
                <h2><i class="fas fa-chart-pie"></i> Waste Types Distribution</h2>
                <div class="chart-container">
                    <canvas id="wasteTypeChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h2><i class="fas fa-chart-doughnut"></i> Request Status</h2>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Requests -->
        <div class="table-card">
            <h2><i class="fas fa-clock"></i> Recent Waste Requests</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Waste Type</th>
                        <th>Quantity (kg)</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requests_res && $requests_res->num_rows > 0): ?>
                        <?php while ($row = $requests_res->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['request_id']) ?></td>
                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                <td><?= htmlspecialchars($row['waste_type']) ?></td>
                                <td><?= htmlspecialchars($row['quantity_kg']) ?></td>
                                <td><?= htmlspecialchars($row['address']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= str_replace(' ', '.', $row['status']) ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($row['request_date'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">No recent requests found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Payments -->
        <div class="table-card" style="margin-top: 1.5rem;">
            <h2><i class="fas fa-money-check-alt"></i> Recent Payments</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Amount (UGX)</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_payments && $recent_payments->num_rows > 0): ?>
                        <?php while ($row = $recent_payments->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['payment_id']) ?></td>
                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                <td>UGX <?= number_format($row['amount'], 0) ?></td>
                                <td><?= htmlspecialchars($row['payment_method']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $row['payment_status'] ?>">
                                        <?= htmlspecialchars($row['payment_status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($row['payment_date'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-data">No recent payments found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Clock Update
        function updateClock() {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            const date = now.toLocaleDateString(undefined, options);
            const time = now.toLocaleTimeString();
            document.getElementById('dashboardClock').innerHTML = `${date} | ${time}`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Chart.js configurations
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.color = '#636e72';

        // Waste Type Chart
        const wasteCtx = document.getElementById('wasteTypeChart').getContext('2d');
        new Chart(wasteCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode(array_column($waste_types, 'waste_type')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($waste_types, 'count')) ?>,
                    backgroundColor: [
                        '#4caf50', '#2196f3', '#ff9800', '#e91e63',
                        '#9c27b0', '#00bcd4', '#ffeb3b'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 10
                        }
                    }
                }
            }
        });

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($status_data, 'status')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($status_data, 'count')) ?>,
                    backgroundColor: [
                        '#ffc107', '#4caf50', '#2196f3',
                        '#9e9e9e', '#f44336', '#607d8b'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 10
                        }
                    }
                }
            }
        });
    </script>
    <script src="../assets/js/notifications.js"></script>
</body>

</html>
<?php $conn->close(); ?>