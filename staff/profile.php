<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}
include('../db_connect.php');

$user_id = $_SESSION['user_id'];

// Fetch staff profile
$staff = $conn->query("
    SELECT s.*, u.email, u.full_name, u.phone, u.address
    FROM staff s
    JOIN users u ON s.staff_id = u.user_id
    WHERE s.staff_id = $user_id
")->fetch_assoc();

// Handle profile update
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Update basic info
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE user_id = ?");
    $stmt->bind_param("sssi", $full_name, $phone, $address, $user_id);

    if ($stmt->execute()) {
        $success_msg = "Profile updated successfully!";

        // Update password if provided
        if (!empty($current_password) && !empty($new_password)) {
            // Verify current password
            $user = $conn->query("SELECT password FROM users WHERE user_id = $user_id")->fetch_assoc();
            if (password_verify($current_password, $user['password'])) {
                if ($new_password === $confirm_password) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $conn->query("UPDATE users SET password = '$hashed_password' WHERE user_id = $user_id");
                    $success_msg .= " Password updated successfully!";
                } else {
                    $error_msg = "New passwords do not match!";
                }
            } else {
                $error_msg = "Current password is incorrect!";
            }
        }
    } else {
        $error_msg = "Error updating profile!";
    }
}

// Refresh staff data after update
$staff = $conn->query("
    SELECT s.*, u.email, u.full_name, u.phone, u.address
    FROM staff s
    JOIN users u ON s.staff_id = u.user_id
    WHERE s.staff_id = $user_id
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Smart Waste Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
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
            <a href="history.php" class="nav-item">
                <i class="fas fa-history"></i>
                <span>History</span>
            </a>
            <a href="profile.php" class="nav-item active">
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
                <h1>My Profile</h1>
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
            <?php if ($success_msg): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <div class="card profile-card">
                <div class="card-header">
                    <h2><i class="fas fa-user-circle"></i> My Profile</h2>
                </div>
                <div class="card-body">
                    <form method="post" class="profile-form">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($staff['full_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" value="<?= htmlspecialchars($staff['email']) ?>" disabled>
                            <small>Email cannot be changed</small>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($staff['phone']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"><?= htmlspecialchars($staff['address']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="position">Position</label>
                            <input type="text" id="position" value="<?= htmlspecialchars($staff['position']) ?>" disabled>
                        </div>

                        <div class="form-section">
                            <h3>Change Password</h3>
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password">
                            </div>

                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password">
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <style>
        .profile-card {
            max-width: 800px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.95rem;
        }

        .form-group input:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }

        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: var(--text-light);
        }

        .form-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .form-section h3 {
            margin-bottom: 1.5rem;
            color: var(--text-dark);
        }

        .form-actions {
            margin-top: 2rem;
            text-align: right;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            transition: background 0.3s;
        }

        .btn-primary:hover {
            background: #174726;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</body>

</html>