<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php'); 
    exit();
}
include('../db_connect.php');
$user_id = $_SESSION['user_id'];

// Fetch user details
$user = $conn->query("SELECT full_name, email, phone FROM users WHERE user_id=$user_id")->fetch_assoc();

// Fetch user requests
$requests_res = $conn->query("
    SELECT request_id, waste_type, quantity_kg, status, request_date, address
    FROM waste_requests
    WHERE user_id=$user_id
    ORDER BY request_date DESC
    LIMIT 10
");

// Summary statistics
$total_requests = $conn->query("SELECT COUNT(*) AS c FROM waste_requests WHERE user_id=$user_id")->fetch_assoc()['c'];
$total_collected = $conn->query("SELECT IFNULL(SUM(quantity_kg),0) AS s FROM waste_requests WHERE user_id=$user_id AND status='Collected'")->fetch_assoc()['s'];
$pending_requests = $conn->query("SELECT COUNT(*) AS c FROM waste_requests WHERE user_id=$user_id AND status='Pending'")->fetch_assoc()['c'];
$approved_requests = $conn->query("SELECT COUNT(*) AS c FROM waste_requests WHERE user_id=$user_id AND status='Approved'")->fetch_assoc()['c'];

// Get payments
$total_paid = $conn->query("SELECT IFNULL(SUM(amount),0) AS s FROM payments WHERE user_id=$user_id AND payment_status='Paid'")->fetch_assoc()['s'];
$pending_payments = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE user_id=$user_id AND payment_status='Pending'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Smart Waste Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/user-dashboard.css">
    <link rel="stylesheet" href="../assets/css/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="user-header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-tachometer-alt"></i> My Dashboard</h1>
                <p class="welcome-text">Welcome back, <?= htmlspecialchars($user['full_name'] ?? 'User') ?>!</p>
            </div>
            <div class="dashboard-clock" id="dashboardClock"></div>
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">0</span>
            </div>
            <div class="user-avatar">
                <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
            </div>
        </div>
        
        <!-- Notification Dropdown -->
        <div class="notification-dropdown">
            <div class="notification-permission-banner">
                <span class="permission-text">
                    <i class="fas fa-info-circle"></i> Enable browser notifications for real-time updates
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
    </header>

    <!-- Navigation -->
    <nav class="user-nav">
        <div class="nav-container">
            <div class="nav-links" id="navLinks">
                <a href="dashboard.php" class="nav-btn active"><i class="fas fa-home"></i> Dashboard</a>
                <a href="request_form.php" class="nav-btn"><i class="fas fa-plus-circle"></i> New Request</a>
                <a href="view_requests.php" class="nav-btn"><i class="fas fa-list"></i> My Requests</a>
                <a href="payment.php" class="nav-btn"><i class="fas fa-credit-card"></i> Payments</a>
                <a href="feedback.php" class="nav-btn"><i class="fas fa-comment"></i> Feedback</a>
            </div>
            <button class="mobile-menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <a href="../logout.php" class="nav-btn logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card card-total">
                <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($total_requests) ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>
            <div class="stat-card card-collected">
                <div class="stat-icon"><i class="fas fa-recycle"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($total_collected, 1) ?> kg</h3>
                    <p>Waste Collected</p>
                </div>
            </div>
            <div class="stat-card card-pending">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($pending_requests) ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
            <div class="stat-card card-approved">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($approved_requests) ?></h3>
                    <p>Approved Requests</p>
                </div>
            </div>
            <div class="stat-card card-paid">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-content">
                    <h3>UGX <?= number_format($total_paid, 0) ?></h3>
                    <p>Total Paid</p>
                </div>
            </div>
            <div class="stat-card card-payment-pending">
                <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($pending_payments) ?></h3>
                    <p>Pending Payments</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="request_form.php" class="action-card green">
                <i class="fas fa-plus-circle"></i>
                <h3>New Request</h3>
                <p>Submit a new waste collection request</p>
            </a>
            <a href="view_requests.php" class="action-card">
                <i class="fas fa-list-alt"></i>
                <h3>View All Requests</h3>
                <p>Check status of all your requests</p>
            </a>
            <a href="payment.php" class="action-card orange">
                <i class="fas fa-wallet"></i>
                <h3>Make Payment</h3>
                <p>Pay for waste collection services</p>
            </a>
        </div>

        <!-- Recent Requests Table -->
        <div class="table-card">
            <h2><i class="fas fa-history"></i> Recent Requests</h2>
            <table>
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Waste Type</th>
                        <th>Quantity (kg)</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($requests_res->num_rows > 0): ?>
                        <?php while($row = $requests_res->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($row['request_id']) ?></strong></td>
                                <td><?= htmlspecialchars($row['waste_type']) ?></td>
                                <td><?= htmlspecialchars($row['quantity_kg']) ?> kg</td>
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
                            <td colspan="6" class="no-data">
                                <i class="fas fa-inbox" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem; display: block;"></i>
                                No requests found. <a href="request_form.php">Create your first request!</a>
                            </td>
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
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const date = now.toLocaleDateString(undefined, options);
            const time = now.toLocaleTimeString();
            document.getElementById('dashboardClock').innerHTML = `${date} | ${time}`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Mobile Menu Toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const navLinks = document.getElementById('navLinks');

        mobileMenuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('show');
        });
    </script>
    <script src="../assets/js/notifications.js"></script>
</body>
</html>
<?php $conn->close(); ?>
