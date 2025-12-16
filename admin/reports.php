<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
include('../db_connect.php');

// Fetch statistics
$totalRequests = $conn->query("SELECT COUNT(*) as count FROM waste_requests")->fetch_assoc()['count'];
$totalCollected = $conn->query("SELECT SUM(quantity_kg) as total FROM waste_requests WHERE status='Collected'")->fetch_assoc()['total'] ?? 0;
$totalUsers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='user'")->fetch_assoc()['count'];
$totalStaff = $conn->query("SELECT COUNT(*) as count FROM staff WHERE status='Active'")->fetch_assoc()['count'];

// Fetch data for charts
$wasteTypes = $conn->query("SELECT waste_type, COUNT(*) AS count FROM waste_requests GROUP BY waste_type");
$wasteTypeLabels = [];
$wasteTypeData = [];
while ($row = $wasteTypes->fetch_assoc()) {
    $wasteTypeLabels[] = $row['waste_type'];
    $wasteTypeData[] = $row['count'];
}

$statusData = $conn->query("SELECT status, COUNT(*) AS count FROM waste_requests GROUP BY status");
$statusLabels = [];
$statusCounts = [];
while ($row = $statusData->fetch_assoc()) {
    $statusLabels[] = $row['status'];
    $statusCounts[] = $row['count'];
}

// Monthly collections for the past 6 months
$monthlyData = $conn->query("
    SELECT DATE_FORMAT(request_date, '%b %Y') as month, COUNT(*) as count, SUM(quantity_kg) as total_kg
    FROM waste_requests 
    WHERE request_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(request_date), MONTH(request_date)
    ORDER BY request_date ASC
");
$monthLabels = [];
$monthlyCounts = [];
$monthlyKg = [];
while ($row = $monthlyData->fetch_assoc()) {
    $monthLabels[] = $row['month'];
    $monthlyCounts[] = $row['count'];
    $monthlyKg[] = $row['total_kg'] ?? 0;
}

// Top users
$topUsers = $conn->query("
    SELECT u.full_name, COUNT(wr.request_id) AS total_requests, SUM(wr.quantity_kg) AS total_collected
    FROM users u
    LEFT JOIN waste_requests wr ON u.user_id = wr.user_id
    WHERE u.role = 'user'
    GROUP BY u.user_id
    ORDER BY total_requests DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <header class="dashboard-header">
        <div class="header-left">
            <h1><i class="fas fa-chart-bar"></i> Reports Dashboard</h1>
            <div class="admin-info">Admin: <?= htmlspecialchars($_SESSION['name'] ?? 'Guest') ?></div>
        </div>
        <div class="dashboard-clock" id="dashboardClock"></div>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
    </header>

    <main>
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-requests"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($totalRequests) ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-collected"><i class="fas fa-trash-alt"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($totalCollected, 1) ?> kg</h3>
                    <p>Total Collected</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-users"><i class="fas fa-users"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($totalUsers) ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-staff"><i class="fas fa-user-hard-hat"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($totalStaff) ?></h3>
                    <p>Active Staff</p>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="charts-grid">
            <!-- Waste Types Pie Chart -->
            <div class="chart-card">
                <h2>Waste Types Distribution</h2>
                <div class="chart-container">
                    <canvas id="wasteTypesChart"></canvas>
                </div>
            </div>

            <!-- Request Status Doughnut Chart -->
            <div class="chart-card">
                <h2>Request Status</h2>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Monthly Requests Bar Chart -->
            <div class="chart-card full-width-chart">
                <h2>Monthly Collection Trends (Last 6 Months)</h2>
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <!-- Monthly Weight Line Chart -->
            <div class="chart-card full-width-chart">
                <h2>Monthly Collection Weight (kg)</h2>
                <div class="chart-container">
                    <canvas id="weightChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Users Table -->
        <div class="table-card">
            <h2><i class="fas fa-trophy"></i> Top 10 Users by Requests</h2>
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>User Name</th>
                        <th>Total Requests</th>
                        <th>Total Collected (kg)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rank = 1;
                    while ($row = $topUsers->fetch_assoc()):
                    ?>
                        <tr>
                            <td><?= $rank++ ?></td>
                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                            <td><?= number_format($row['total_requests']) ?></td>
                            <td><?= number_format($row['total_collected'] ?? 0, 2) ?></td>
                        </tr>
                    <?php endwhile; ?>
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

        // Waste Types Pie Chart
        const wasteTypesCtx = document.getElementById('wasteTypesChart').getContext('2d');
        new Chart(wasteTypesCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode($wasteTypeLabels) ?>,
                datasets: [{
                    data: <?= json_encode($wasteTypeData) ?>,
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
                            padding: 15
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.parsed || 0;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Status Doughnut Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($statusLabels) ?>,
                datasets: [{
                    data: <?= json_encode($statusCounts) ?>,
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
                            padding: 15
                        }
                    }
                }
            }
        });

        // Monthly Requests Bar Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthLabels) ?>,
                datasets: [{
                    label: 'Number of Requests',
                    data: <?= json_encode($monthlyCounts) ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Monthly Weight Line Chart
        const weightCtx = document.getElementById('weightChart').getContext('2d');
        new Chart(weightCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($monthLabels) ?>,
                datasets: [{
                    label: 'Weight Collected (kg)',
                    data: <?= json_encode($monthlyKg) ?>,
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    borderColor: 'rgba(76, 175, 80, 1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: 'rgba(76, 175, 80, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>

</html>
<?php $conn->close(); ?>