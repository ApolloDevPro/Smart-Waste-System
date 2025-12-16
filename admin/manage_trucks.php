<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
include('../db_connect.php');

$msg = '';
$msgType = '';

// Handle truck actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_truck'])) {
        $truck_number = $_POST['truck_number'];
        $driver_name = $_POST['driver_name'];
        $capacity_kg = $_POST['capacity_kg'];
        $status = $_POST['status'];

        $stmt = $conn->prepare("INSERT INTO trucks (truck_number, driver_name, capacity_kg, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $truck_number, $driver_name, $capacity_kg, $status);

        if ($stmt->execute()) {
            $msg = "Truck added successfully!";
            $msgType = "success";
        } else {
            $msg = "Error: " . $stmt->error;
            $msgType = "error";
        }
    } elseif (isset($_POST['update_truck'])) {
        $truck_id = $_POST['truck_id'];
        $truck_number = $_POST['truck_number'];
        $driver_name = $_POST['driver_name'];
        $capacity_kg = $_POST['capacity_kg'];
        $status = $_POST['status'];

        $stmt = $conn->prepare("UPDATE trucks SET truck_number=?, driver_name=?, capacity_kg=?, status=? WHERE truck_id=?");
        $stmt->bind_param("ssdsi", $truck_number, $driver_name, $capacity_kg, $status, $truck_id);

        if ($stmt->execute()) {
            $msg = "Truck updated successfully!";
            $msgType = "success";
        } else {
            $msg = "Error: " . $stmt->error;
            $msgType = "error";
        }
    } elseif (isset($_POST['delete_truck'])) {
        $truck_id = $_POST['truck_id'];

        // Check if truck is used by any staff
        $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM staff WHERE truck_id = ?");
        $check->bind_param("i", $truck_id);
        $check->execute();
        $count = $check->get_result()->fetch_assoc()['cnt'];

        if ($count > 0) {
            $msg = "Cannot delete truck: It is currently assigned to staff members.";
            $msgType = "error";
        } else {
            $stmt = $conn->prepare("DELETE FROM trucks WHERE truck_id = ?");
            $stmt->bind_param("i", $truck_id);
            if ($stmt->execute()) {
                $msg = "Truck deleted successfully!";
                $msgType = "success";
            } else {
                $msg = "Error: " . $stmt->error;
                $msgType = "error";
            }
        }
    }
}

// Fetch all trucks with statistics
$trucks = $conn->query("SELECT * FROM trucks ORDER BY truck_number");
$total_trucks = $conn->query("SELECT COUNT(*) AS c FROM trucks")->fetch_assoc()['c'] ?? 0;
$available_trucks = $conn->query("SELECT COUNT(*) AS c FROM trucks WHERE status='Available'")->fetch_assoc()['c'] ?? 0;
$busy_trucks = $conn->query("SELECT COUNT(*) AS c FROM trucks WHERE status='Busy'")->fetch_assoc()['c'] ?? 0;
$maintenance_trucks = $conn->query("SELECT COUNT(*) AS c FROM trucks WHERE status='Maintenance'")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Trucks</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/manage-trucks.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <header class="dashboard-header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-truck"></i> Manage Trucks</h1>
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
                <div class="stat-icon"><i class="fas fa-truck"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($total_trucks) ?></h3>
                    <p>Total Trucks</p>
                </div>
            </div>
            <div class="stat-card card-available">
                <div class="stat-icon"><i class="fas fa-truck-loading"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($available_trucks) ?></h3>
                    <p>Available</p>
                </div>
            </div>
            <div class="stat-card card-busy">
                <div class="stat-icon"><i class="fas fa-truck-moving"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($busy_trucks) ?></h3>
                    <p>Busy</p>
                </div>
            </div>
            <div class="stat-card card-maintenance">
                <div class="stat-icon"><i class="fas fa-tools"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($maintenance_trucks) ?></h3>
                    <p>Maintenance</p>
                </div>
            </div>
        </div>

        <!-- Add Truck Form -->
        <div class="form-card">
            <h2><i class="fas fa-plus-circle"></i> Add New Truck</h2>
            <form method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="truck_number"><i class="fas fa-hashtag"></i> Truck Number</label>
                        <input type="text" name="truck_number" id="truck_number" placeholder="TR-101" required>
                    </div>
                    <div class="form-group">
                        <label for="driver_name"><i class="fas fa-user"></i> Driver Name</label>
                        <input type="text" name="driver_name" id="driver_name" placeholder="Joram">
                    </div>
                    <div class="form-group">
                        <label for="capacity_kg"><i class="fas fa-weight"></i> Capacity (kg)</label>
                        <input type="number" name="capacity_kg" id="capacity_kg" placeholder="5000" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="status"><i class="fas fa-info-circle"></i> Status</label>
                        <select name="status" id="status" required>
                            <option value="Available">Available</option>
                            <option value="Busy">Busy</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_truck" class="btn-primary">
                    <i class="fas fa-plus"></i> Add Truck
                </button>
            </form>
        </div>

        <!-- Truck List -->
        <div class="table-card">
            <h2><i class="fas fa-list"></i> Truck List</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Truck Number</th>
                        <th>Driver Name</th>
                        <th>Capacity (kg)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($trucks->num_rows > 0): ?>
                        <?php while ($t = $trucks->fetch_assoc()): ?>
                            <tr>
                                <td><?= $t['truck_id'] ?></td>
                                <td><strong><?= htmlspecialchars($t['truck_number']) ?></strong></td>
                                <td><?= htmlspecialchars($t['driver_name'] ?? 'N/A') ?></td>
                                <td><?= number_format($t['capacity_kg'], 0) ?> kg</td>
                                <td>
                                    <span class="status-badge status-<?= $t['status'] ?>">
                                        <?= htmlspecialchars($t['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-edit" onclick="editTruck(<?= htmlspecialchars(json_encode($t)) ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this truck?')">
                                            <input type="hidden" name="truck_id" value="<?= $t['truck_id'] ?>">
                                            <button type="submit" name="delete_truck" class="btn-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-data">No trucks found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Truck</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="post">
                <input type="hidden" name="truck_id" id="edit_truck_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_truck_number">Truck Number</label>
                        <input type="text" name="truck_number" id="edit_truck_number" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_driver_name">Driver Name</label>
                        <input type="text" name="driver_name" id="edit_driver_name">
                    </div>
                    <div class="form-group">
                        <label for="edit_capacity_kg">Capacity (kg)</label>
                        <input type="number" name="capacity_kg" id="edit_capacity_kg" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select name="status" id="edit_status" required>
                            <option value="Available">Available</option>
                            <option value="Busy">Busy</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="update_truck" class="btn-primary">
                    <i class="fas fa-save"></i> Update Truck
                </button>
            </form>
        </div>
    </div>

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

        // Edit Modal Functions
        function editTruck(truck) {
            document.getElementById('edit_truck_id').value = truck.truck_id;
            document.getElementById('edit_truck_number').value = truck.truck_number;
            document.getElementById('edit_driver_name').value = truck.driver_name || '';
            document.getElementById('edit_capacity_kg').value = truck.capacity_kg;
            document.getElementById('edit_status').value = truck.status;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>

</html>
<?php $conn->close(); ?>