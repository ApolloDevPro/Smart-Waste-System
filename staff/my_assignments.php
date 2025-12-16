<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}
include('../db_connect.php');

$user_id = $_SESSION['user_id'];

// Fetch all assignments for the staff member with user contact details
$assignments = $conn->query("
    SELECT a.assignment_id, a.assigned_date, wr.request_id, wr.waste_type, 
           wr.quantity_kg, wr.address, wr.status AS request_status, 
           a.status AS assignment_status,
           u.full_name, u.phone, u.email
    FROM assignments a
    JOIN waste_requests wr ON a.request_id = wr.request_id
    JOIN users u ON wr.user_id = u.user_id
    WHERE a.staff_id = $user_id
    ORDER BY a.assigned_date DESC
");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments - Smart Waste Management</title>
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
            <a href="my_assignments.php" class="nav-item active">
                <i class="fas fa-tasks"></i>
                <span>My Assignments</span>
            </a>
            <a href="locations.php" class="nav-item">
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
                <h1>My Assignments History</h1>
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
            <div class="card assignments-card">
                <div class="card-header">
                    <h2><i class="fas fa-tasks"></i> My Assignments History</h2>
                </div>
                <div class="card-body">
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
                                    <th>Customer Info</th>
                                    <th data-sortable data-sort="wasteType">Waste Type <i class="fas fa-sort"></i></th>
                                    <th data-sortable data-sort="quantity">Quantity <i class="fas fa-sort"></i></th>
                                    <th>Location & Navigation</th>
                                    <th data-sortable data-sort="status">Status <i class="fas fa-sort"></i></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($assignment = $assignments->fetch_assoc()): 
                                    $encoded_address = urlencode($assignment['address']);
                                    $google_maps_url = "https://www.google.com/maps/search/?api=1&query=" . $encoded_address;
                                    $waze_url = "https://www.waze.com/ul?q=" . $encoded_address;
                                ?>
                                    <tr>
                                        <td data-label="ID">
                                            <strong>#<?= $assignment['request_id'] ?></strong>
                                            <br>
                                            <small style="color: #666;"><?= date('M d, Y', strtotime($assignment['assigned_date'])) ?></small>
                                        </td>
                                        <td data-label="Customer">
                                            <div style="line-height: 1.6;">
                                                <strong><i class="fas fa-user"></i> <?= htmlspecialchars($assignment['full_name']) ?></strong><br>
                                                <a href="tel:<?= htmlspecialchars($assignment['phone']) ?>" style="color: #11998e; text-decoration: none;">
                                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($assignment['phone']) ?>
                                                </a><br>
                                                <a href="mailto:<?= htmlspecialchars($assignment['email']) ?>" style="color: #666; text-decoration: none; font-size: 0.85em;">
                                                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($assignment['email']) ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td data-label="Waste Type">
                                            <span class="waste-type">
                                                <i class="fas fa-recycle"></i>
                                                <?= htmlspecialchars($assignment['waste_type']) ?>
                                            </span>
                                        </td>
                                        <td data-label="Quantity"><strong><?= number_format($assignment['quantity_kg'], 2) ?> kg</strong></td>
                                        <td data-label="Location" style="min-width: 250px;">
                                            <div class="location-info">
                                                <div class="address-cell" style="margin-bottom: 8px;">
                                                    <i class="fas fa-map-marker-alt" style="color: #e74c3c;"></i>
                                                    <strong><?= htmlspecialchars($assignment['address']) ?></strong>
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
                                                    <button onclick="copyAddress('<?= htmlspecialchars($assignment['address'], ENT_QUOTES) ?>')" 
                                                            style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: #95a5a6; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 0.85em;">
                                                        <i class="fas fa-copy"></i> Copy
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-label="Status">
                                            <span class="status-badge status-<?= strtolower($assignment['assignment_status']) ?>">
                                                <?= htmlspecialchars($assignment['assignment_status']) ?>
                                            </span>
                                        </td>
                                        <td data-label="Actions">
                                            <a href="tel:<?= htmlspecialchars($assignment['phone']) ?>" 
                                               style="display: inline-block; padding: 8px 15px; background: #27ae60; color: white; border-radius: 5px; text-decoration: none; margin-bottom: 5px;">
                                                <i class="fas fa-phone-alt"></i> Call Customer
                                            </a>
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
    <script>
        // Copy address to clipboard
        function copyAddress(address) {
            navigator.clipboard.writeText(address).then(function() {
                // Show success message
                const btn = event.target.closest('button');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                btn.style.background = '#27ae60';
                
                setTimeout(function() {
                    btn.innerHTML = originalHTML;
                    btn.style.background = '#95a5a6';
                }, 2000);
            }).catch(function(err) {
                alert('Failed to copy address: ' + err);
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

        // Sidebar toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }
    </script>
</body>

</html>