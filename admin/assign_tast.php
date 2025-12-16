<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

include('../db_connect.php');
require_once('../includes/Notification.php');

$msg = '';
$msgType = '';

// Handle assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    $request_id = intval($_POST['request_id']);
    $staff_id = intval($_POST['staff_id']);
    $truck_id = intval($_POST['truck_id']);
    $assigned_by = $_SESSION['user_id'];

    // Check if request is already assigned
    $check = $conn->prepare("SELECT assignment_id FROM assignments WHERE request_id = ?");
    $check->bind_param('i', $request_id);
    $check->execute();
    $checkRes = $check->get_result();

    if ($checkRes->num_rows > 0) {
        $msg = "This request has already been assigned!";
        $msgType = "error";
    } else {
        // Insert new assignment
        $stmt = $conn->prepare("INSERT INTO assignments (request_id, truck_id, staff_id, assigned_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiii', $request_id, $truck_id, $staff_id, $assigned_by);
        if ($stmt->execute()) {
            // Update truck status to "Busy"
            $stmt2 = $conn->prepare("UPDATE trucks SET status='Busy' WHERE truck_id = ?");
            $stmt2->bind_param('i', $truck_id);
            $stmt2->execute();

            // Update request status to "Approved"
            $stmt3 = $conn->prepare("UPDATE waste_requests SET status='Approved' WHERE request_id = ?");
            $stmt3->bind_param('i', $request_id);
            $stmt3->execute();

            // Get assignment details for notifications
            $assignment_details = $conn->query("
                SELECT wr.user_id, wr.waste_type, wr.quantity_kg, u.full_name as user_name,
                       s.name as staff_name, t.truck_number
                FROM waste_requests wr
                JOIN users u ON wr.user_id = u.user_id
                JOIN staff s ON s.staff_id = $staff_id
                JOIN trucks t ON t.truck_id = $truck_id
                WHERE wr.request_id = $request_id
            ")->fetch_assoc();
            
            if ($assignment_details) {
                $notif = new Notification($conn);
                
                // Notify user
                $notif->create(
                    $assignment_details['user_id'],
                    "Request Assigned",
                    "Your waste collection request (#$request_id) has been assigned to {$assignment_details['staff_name']} with truck {$assignment_details['truck_number']}.",
                    'assignment',
                    $request_id,
                    '../user/view_requests.php'
                );
                
                // Notify staff member
                $notif->create(
                    $staff_id,
                    "New Assignment",
                    "You have been assigned to collect {$assignment_details['waste_type']} ({$assignment_details['quantity_kg']} kg) for request #$request_id.",
                    'assignment',
                    $request_id,
                    '../staff/my_assignments.php'
                );
                
                // Send email to user
                $notif->sendEmail(
                    $assignment_details['user_id'],
                    "Waste Collection Assigned",
                    "Good news! Your waste collection request has been assigned.\n\nRequest ID: #$request_id\nAssigned Staff: {$assignment_details['staff_name']}\nTruck: {$assignment_details['truck_number']}\nWaste Type: {$assignment_details['waste_type']}\n\nOur team will contact you soon."
                );
            }

            $msg = "Assignment successful!";
            $msgType = "success";
        } else {
            $msg = "Error assigning request: " . $stmt->error;
            $msgType = "error";
        }
    }
}

// Handle assignment completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete'])) {
    $assignment_id = intval($_POST['assignment_id']);

    // Get truck_id from assignment
    $getTruck = $conn->prepare("SELECT truck_id, request_id FROM assignments WHERE assignment_id = ?");
    $getTruck->bind_param('i', $assignment_id);
    $getTruck->execute();
    $result = $getTruck->get_result()->fetch_assoc();

    if ($result) {
        // Update assignment status
        $stmt = $conn->prepare("UPDATE assignments SET status='Completed' WHERE assignment_id = ?");
        $stmt->bind_param('i', $assignment_id);
        $stmt->execute();

        // Update truck status to Available
        $stmt2 = $conn->prepare("UPDATE trucks SET status='Available' WHERE truck_id = ?");
        $stmt2->bind_param('i', $result['truck_id']);
        $stmt2->execute();

        // Update request status to Collected
        $stmt3 = $conn->prepare("UPDATE waste_requests SET status='Collected' WHERE request_id = ?");
        $stmt3->bind_param('i', $result['request_id']);
        $stmt3->execute();

        // Get request details for notification
        $request_details = $conn->query("
            SELECT wr.user_id, wr.waste_type, wr.quantity_kg
            FROM waste_requests wr
            WHERE wr.request_id = {$result['request_id']}
        ")->fetch_assoc();
        
        if ($request_details) {
            $notif = new Notification($conn);
            
            // Notify user
            $notif->create(
                $request_details['user_id'],
                "Waste Collected",
                "Your waste collection request (#{$result['request_id']}) has been completed successfully. Thank you for using our service!",
                'request',
                $result['request_id'],
                '../user/view_requests.php'
            );
            
            // Send email
            $notif->sendEmail(
                $request_details['user_id'],
                "Collection Completed",
                "Your waste has been collected successfully!\n\nRequest ID: #{$result['request_id']}\nWaste Type: {$request_details['waste_type']}\nQuantity: {$request_details['quantity_kg']} kg\n\nThank you for helping keep our environment clean!"
            );
        }

        $msg = "Assignment marked as completed!";
        $msgType = "success";
    }
}

// Fetch data for dropdowns
$pendingRequests = $conn->query("
    SELECT wr.request_id, wr.waste_type, wr.quantity_kg, wr.address, u.full_name 
    FROM waste_requests wr
    JOIN users u ON wr.user_id = u.user_id
    WHERE wr.status='Pending' 
    ORDER BY wr.request_date DESC
");
$availableStaff = $conn->query("SELECT staff_id, name, phone, position FROM staff WHERE status='Active' ORDER BY name");
$availableTrucks = $conn->query("SELECT truck_id, truck_number, capacity_kg FROM trucks WHERE status='Available' ORDER BY truck_number");

// Fetch all assignments with details
$assignments = $conn->query("
    SELECT a.assignment_id, wr.request_id, wr.waste_type, wr.quantity_kg, wr.address, 
           u.full_name AS requester_name, s.name AS staff_name, t.truck_number, 
           a.status AS assignment_status, wr.status AS request_status, a.assigned_date
    FROM assignments a
    JOIN waste_requests wr ON a.request_id = wr.request_id
    JOIN users u ON wr.user_id = u.user_id
    LEFT JOIN staff s ON a.staff_id = s.staff_id
    LEFT JOIN trucks t ON a.truck_id = t.truck_id
    ORDER BY a.assigned_date DESC
    LIMIT 20
");

// Fetch statistics
$total_assignments = $conn->query("SELECT COUNT(*) AS c FROM assignments")->fetch_assoc()['c'] ?? 0;
$pending_assignments = $conn->query("SELECT COUNT(*) AS c FROM assignments WHERE status='Assigned'")->fetch_assoc()['c'] ?? 0;
$completed_assignments = $conn->query("SELECT COUNT(*) AS c FROM assignments WHERE status='Completed'")->fetch_assoc()['c'] ?? 0;
$pending_requests = $conn->query("SELECT COUNT(*) AS c FROM waste_requests WHERE status='Pending'")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Collection Tasks</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/assign-tast.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <header class="dashboard-header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-tasks"></i> Assign Collection Tasks</h1>
                <div class="admin-info">Admin: <?= htmlspecialchars($_SESSION['name'] ?? 'Guest') ?></div>
            </div>
            <div class="dashboard-clock" id="dashboardClock"></div>
            <a href="dashboard.php" class="btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </header>

    <main>
        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?= $msgType ?>">
                <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card card-total">
                <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($total_assignments) ?></h3>
                    <p>Total Assignments</p>
                </div>
            </div>
            <div class="stat-card card-pending">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($pending_assignments) ?></h3>
                    <p>Pending Tasks</p>
                </div>
            </div>
            <div class="stat-card card-completed">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($completed_assignments) ?></h3>
                    <p>Completed Tasks</p>
                </div>
            </div>
            <div class="stat-card card-requests">
                <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($pending_requests) ?></h3>
                    <p>Unassigned Requests</p>
                </div>
            </div>
        </div>

        <!-- Assignment Form -->
        <div class="form-card">
            <h2><i class="fas fa-plus-circle"></i> Create New Assignment</h2>
            <form method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="request_id"><i class="fas fa-file-alt"></i> Select Request</label>
                        <select name="request_id" id="request_id" required>
                            <option value="">-- Select Request --</option>
                            <?php while ($req = $pendingRequests->fetch_assoc()): ?>
                                <option value="<?= $req['request_id'] ?>">
                                    #<?= $req['request_id'] ?> - <?= htmlspecialchars($req['full_name']) ?> |
                                    <?= htmlspecialchars($req['waste_type']) ?> (<?= htmlspecialchars($req['quantity_kg']) ?> kg) -
                                    <?= htmlspecialchars($req['address']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="staff_id"><i class="fas fa-user"></i> Assign Staff Member</label>
                        <select name="staff_id" id="staff_id" required>
                            <option value="">-- Select Staff --</option>
                            <?php while ($s = $availableStaff->fetch_assoc()): ?>
                                <option value="<?= $s['staff_id'] ?>">
                                    <?= htmlspecialchars($s['name']) ?> - <?= htmlspecialchars($s['position'] ?? 'Staff') ?>
                                    <?= $s['phone'] ? ' (' . htmlspecialchars($s['phone']) . ')' : '' ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="truck_id"><i class="fas fa-truck"></i> Assign Truck</label>
                        <select name="truck_id" id="truck_id" required>
                            <option value="">-- Select Truck --</option>
                            <?php while ($t = $availableTrucks->fetch_assoc()): ?>
                                <option value="<?= $t['truck_id'] ?>">
                                    <?= htmlspecialchars($t['truck_number']) ?>
                                    (Capacity: <?= number_format($t['capacity_kg']) ?> kg)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="assign" class="btn-primary">
                    <i class="fas fa-plus"></i> Create Assignment
                </button>
            </form>
        </div>

        <!-- Current Assignments -->
        <div class="table-card">
            <h2><i class="fas fa-list"></i> Recent Assignments</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Request</th>
                        <th>Requester</th>
                        <th>Waste Type</th>
                        <th>Quantity</th>
                        <th>Staff</th>
                        <th>Truck</th>
                        <th>Assignment Status</th>
                        <th>Request Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($assignments->num_rows > 0): ?>
                        <?php while ($a = $assignments->fetch_assoc()): ?>
                            <tr>
                                <td><?= $a['assignment_id'] ?></td>
                                <td><strong>#<?= $a['request_id'] ?></strong></td>
                                <td><?= htmlspecialchars($a['requester_name']) ?></td>
                                <td><?= htmlspecialchars($a['waste_type']) ?></td>
                                <td><?= htmlspecialchars($a['quantity_kg']) ?> kg</td>
                                <td><?= htmlspecialchars($a['staff_name']) ?></td>
                                <td><?= htmlspecialchars($a['truck_number']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $a['assignment_status'] ?>">
                                        <?= htmlspecialchars($a['assignment_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $a['request_status'] ?>">
                                        <?= htmlspecialchars($a['request_status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($a['assigned_date'])) ?></td>
                                <td>
                                    <?php if ($a['assignment_status'] === 'Assigned'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="assignment_id" value="<?= $a['assignment_id'] ?>">
                                            <button type="submit" name="complete" class="btn-complete"
                                                onclick="return confirm('Mark this assignment as completed?')">
                                                <i class="fas fa-check"></i> Complete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 0.85rem;">Completed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="no-data">No assignments found.</td>
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
    </script>
</body>

</html>
<?php $conn->close(); ?>