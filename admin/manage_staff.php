<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
include('../db_connect.php');

$msg = '';
$msgType = '';

// Handle staff actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_staff'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $phone = $_POST['phone'];
        $position = $_POST['position'];
        $truck_id = $_POST['truck_id'] ?: NULL;
        $status = $_POST['status'];

        // Start transaction
        $conn->begin_transaction();
        try {
            // Check if user already exists
            $result = $conn->query("SELECT user_id FROM users WHERE email = '" . $conn->real_escape_string($email) . "' LIMIT 1");
            if ($result && $result->num_rows > 0) {
                // User exists, get user_id
                $row = $result->fetch_assoc();
                $user_id = $row['user_id'];
            } else {
                // Create new user account
                $stmt_user = $conn->prepare("INSERT INTO users (full_name, email, password, phone, role) VALUES (?, ?, ?, ?, 'staff')");
                if ($stmt_user) {
                    $stmt_user->bind_param("ssss", $name, $email, $password, $phone);
                    if ($stmt_user->execute()) {
                        $user_id = $stmt_user->insert_id;
                    } else {
                        throw new Exception($stmt_user->error);
                    }
                } else {
                    throw new Exception($conn->error);
                }
            }
            if ($user_id) {
                // Check if staff record already exists
                $result = $conn->query("SELECT staff_id FROM staff WHERE staff_id = $user_id LIMIT 1");
                if ($result && $result->num_rows > 0) {
                    throw new Exception("Staff record for this user already exists.");
                } else {
                    // Create staff record only if not exists
                    $stmt_staff = $conn->prepare("INSERT INTO staff (staff_id, name, phone, position, truck_id, status) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt_staff) {
                        $stmt_staff->bind_param("isssss", $user_id, $name, $phone, $position, $truck_id, $status);
                        if ($stmt_staff->execute()) {
                            $conn->commit();
                            $msg = "Staff member added successfully!";
                            $msgType = "success";
                        } else {
                            throw new Exception($stmt_staff->error);
                        }
                    } else {
                        throw new Exception($conn->error);
                    }
                }
            } else {
                throw new Exception("Could not determine user_id for staff creation.");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Error: " . $e->getMessage();
            $msgType = "error";
        }
    } elseif (isset($_POST['update_staff'])) {
        $staff_id = $_POST['staff_id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $position = $_POST['position'];
        $truck_id = $_POST['truck_id'] ?: NULL;
        $status = $_POST['status'];

        // Start transaction
        $conn->begin_transaction();

        try {
            // Update user information
            $stmt_update_user = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE user_id=?");
            if ($stmt_update_user) {
                $stmt_update_user->bind_param("sssi", $name, $email, $phone, $staff_id);
                $stmt_update_user->execute();
            } else {
                throw new Exception($conn->error);
            }

            // Update password if provided
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt_update_pass = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
                if ($stmt_update_pass) {
                    $stmt_update_pass->bind_param("si", $password, $staff_id);
                    $stmt_update_pass->execute();
                } else {
                    throw new Exception($conn->error);
                }
            }

            // Update staff information
            $stmt_update_staff = $conn->prepare("UPDATE staff SET name=?, phone=?, position=?, truck_id=?, status=? WHERE staff_id=?");
            if ($stmt_update_staff) {
                $stmt_update_staff->bind_param("sssisi", $name, $phone, $position, $truck_id, $status, $staff_id);
                $stmt_update_staff->execute();
            } else {
                throw new Exception($conn->error);
            }

            $conn->commit();
            $msg = "Staff member updated successfully!";
            $msgType = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "Error: " . $e->getMessage();
            $msgType = "error";
        }
    } elseif (isset($_POST['delete_staff'])) {
        $staff_id = $_POST['staff_id'];

        // Check if staff is assigned to any active assignments
        $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM assignments WHERE staff_id = ? AND status='Assigned'");
        if ($check) {
            $check->bind_param("i", $staff_id);
            $check->execute();
            $count = $check->get_result()->fetch_assoc()['cnt'];
        } else {
            $msg = "Error: " . $conn->error;
            $msgType = "error";
            $count = 1; // Prevent deletion if error
        }

        if ($count > 0) {
            $msg = "Cannot delete staff: They have active assignments.";
            $msgType = "error";
        } else {
            $stmt_delete = $conn->prepare("DELETE FROM staff WHERE staff_id = ?");
            if ($stmt_delete) {
                $stmt_delete->bind_param("i", $staff_id);
                if ($stmt_delete->execute()) {
                    $msg = "Staff member deleted successfully!";
                    $msgType = "success";
                } else {
                    $msg = "Error: " . $stmt_delete->error;
                    $msgType = "error";
                }
            } else {
                $msg = "Error: " . $conn->error;
                $msgType = "error";
            }
        }
    }
}

// Fetch all staff with truck information
$staff = $conn->query("
    SELECT s.*, t.truck_number 
    FROM staff s 
    LEFT JOIN trucks t ON s.truck_id = t.truck_id 
    ORDER BY s.name
");

// Fetch available trucks
$trucks = $conn->query("SELECT truck_id, truck_number, status FROM trucks ORDER BY truck_number");

// Fetch statistics
$total_staff = $conn->query("SELECT COUNT(*) AS c FROM staff")->fetch_assoc()['c'] ?? 0;
$active_staff = $conn->query("SELECT COUNT(*) AS c FROM staff WHERE status='Active'")->fetch_assoc()['c'] ?? 0;
$inactive_staff = $conn->query("SELECT COUNT(*) AS c FROM staff WHERE status='Inactive'")->fetch_assoc()['c'] ?? 0;
$assigned_staff = $conn->query("SELECT COUNT(DISTINCT staff_id) AS c FROM assignments WHERE status='Assigned'")->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/manage-staff.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <header class="dashboard-header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-users"></i> Manage Staff</h1>
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
                <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($total_staff) ?></h3>
                    <p>Total Staff</p>
                </div>
            </div>
            <div class="stat-card card-active">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($active_staff) ?></h3>
                    <p>Active Staff</p>
                </div>
            </div>
            <div class="stat-card card-inactive">
                <div class="stat-icon"><i class="fas fa-user-times"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($inactive_staff) ?></h3>
                    <p>Inactive Staff</p>
                </div>
            </div>
            <div class="stat-card card-assigned">
                <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($assigned_staff) ?></h3>
                    <p>On Assignment</p>
                </div>
            </div>
        </div>

        <!-- Add Staff Form -->
        <div class="form-card">
            <h2><i class="fas fa-user-plus"></i> Add New Staff Member</h2>
            <form method="post">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="name" id="name" placeholder="Enter full name" required>
                    </div>
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" id="email" placeholder="Enter email address" required>
                    </div>
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password</label>
                        <input type="password" name="password" id="password" placeholder="Enter password" required>
                    </div>
                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" name="phone" id="phone" placeholder="+256 700 000000">
                    </div>
                    <div class="form-group">
                        <label for="position"><i class="fas fa-briefcase"></i> Position</label>
                        <input type="text" name="position" id="position" placeholder="Waste Collector">
                    </div>
                    <div class="form-group">
                        <label for="truck_id"><i class="fas fa-truck"></i> Assign Truck (Optional)</label>
                        <select name="truck_id" id="truck_id">
                            <option value="">-- No Truck --</option>
                            <?php
                            $trucks->data_seek(0);
                            while ($t = $trucks->fetch_assoc()):
                            ?>
                                <option value="<?= $t['truck_id'] ?>">
                                    <?= htmlspecialchars($t['truck_number']) ?> (<?= $t['status'] ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status"><i class="fas fa-info-circle"></i> Status</label>
                        <select name="status" id="status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_staff" class="btn-primary">
                    <i class="fas fa-plus"></i> Add Staff Member
                </button>
            </form>
        </div>

        <!-- Staff List -->
        <div class="table-card">
            <h2><i class="fas fa-list"></i> Staff List</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Position</th>
                        <th>Assigned Truck</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($staff->num_rows > 0): ?>
                        <?php while ($s = $staff->fetch_assoc()): ?>
                            <tr>
                                <td><?= $s['staff_id'] ?></td>
                                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                                <td><?= htmlspecialchars($s['phone'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($s['position'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($s['truck_number'] ?? 'Not Assigned') ?></td>
                                <td>
                                    <span class="status-badge status-<?= $s['status'] ?>">
                                        <?= htmlspecialchars($s['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-edit" onclick="editStaff(<?= htmlspecialchars(json_encode($s)) ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this staff member?')">
                                            <input type="hidden" name="staff_id" value="<?= $s['staff_id'] ?>">
                                            <button type="submit" name="delete_staff" class="btn-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">No staff members found.</td>
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
                <h2><i class="fas fa-edit"></i> Edit Staff Member</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="post">
                <input type="hidden" name="staff_id" id="edit_staff_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_name">Full Name</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email</label>
                        <input type="email" name="email" id="edit_email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">New Password</label>
                        <input type="password" name="password" id="edit_password" placeholder="Leave blank to keep current password">
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">Phone Number</label>
                        <input type="tel" name="phone" id="edit_phone">
                    </div>
                    <div class="form-group">
                        <label for="edit_position">Position</label>
                        <input type="text" name="position" id="edit_position">
                    </div>
                    <div class="form-group">
                        <label for="edit_truck_id">Assign Truck</label>
                        <select name="truck_id" id="edit_truck_id">
                            <option value="">-- No Truck --</option>
                            <?php
                            $trucks->data_seek(0);
                            while ($t = $trucks->fetch_assoc()):
                            ?>
                                <option value="<?= $t['truck_id'] ?>">
                                    <?= htmlspecialchars($t['truck_number']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select name="status" id="edit_status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="update_staff" class="btn-primary">
                    <i class="fas fa-save"></i> Update Staff Member
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
        function editStaff(staff) {
            document.getElementById('edit_staff_id').value = staff.staff_id;
            document.getElementById('edit_name').value = staff.name;
            document.getElementById('edit_phone').value = staff.phone || '';
            document.getElementById('edit_position').value = staff.position || '';
            document.getElementById('edit_truck_id').value = staff.truck_id || '';
            document.getElementById('edit_status').value = staff.status;
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

        // Update edit form with staff information
        function editStaff(staff) {
            document.getElementById('edit_staff_id').value = staff.staff_id;
            document.getElementById('edit_name').value = staff.name;
            document.getElementById('edit_email').value = staff.email || '';
            document.getElementById('edit_phone').value = staff.phone || '';
            document.getElementById('edit_position').value = staff.position || '';
            document.getElementById('edit_truck_id').value = staff.truck_id || '';
            document.getElementById('edit_status').value = staff.status;
            document.getElementById('edit_password').value = ''; // Clear password field
            document.getElementById('editModal').style.display = 'block';
        }
    </script>
</body>

</html>
<?php
// Update staff query to include email
$staff = $conn->query("
    SELECT s.*, t.truck_number, u.email 
    FROM staff s 
    LEFT JOIN trucks t ON s.truck_id = t.truck_id 
    LEFT JOIN users u ON s.staff_id = u.user_id 
    ORDER BY s.name
");
$conn->close();
?>