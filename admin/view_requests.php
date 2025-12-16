<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../login.php');
  exit();
}

include('../db_connect.php');

$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$query = "SELECT wr.request_id, u.full_name, u.phone, u.email, wr.waste_type, wr.quantity_kg, wr.address, wr.status, wr.request_date 
          FROM waste_requests wr 
          JOIN users u ON wr.user_id = u.user_id";

$conditions = [];
if ($filter !== 'all') {
  $conditions[] = "wr.status = '" . $conn->real_escape_string($filter) . "'";
}
if (!empty($search)) {
  $conditions[] = "(u.full_name LIKE '%" . $conn->real_escape_string($search) . "%' OR wr.waste_type LIKE '%" . $conn->real_escape_string($search) . "%')";
}

if (!empty($conditions)) {
  $query .= " WHERE " . implode(" AND ", $conditions);
}
$query .= " ORDER BY wr.request_date DESC";

$res = $conn->query($query);
$stats = $conn->query("SELECT status, COUNT(*) as count FROM waste_requests GROUP BY status");
$statusCounts = [];
while ($stat = $stats->fetch_assoc()) {
  $statusCounts[$stat['status']] = $stat['count'];
}
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <title>View Requests</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/view-requests.css">
</head>

<body>
  <header class="dashboard-header">
    <div>
      <h1>Waste Requests</h1>
      <div class="admin-info">Admin: <?= htmlspecialchars($_SESSION['name'] ?? 'Guest') ?></div>
    </div>
    <div class="dashboard-clock" id="dashboardClock"></div>
    <a href="dashboard.php" class="btn">Back to Dashboard</a>
  </header>

  <main class="center">
    <div class="stats-grid">
      <div class="stat-card stat-pending">
        <h3><?= $statusCounts['Pending'] ?? 0 ?></h3>
        <p>Pending</p>
      </div>
      <div class="stat-card stat-approved">
        <h3><?= $statusCounts['Approved'] ?? 0 ?></h3>
        <p>Approved</p>
      </div>
      <div class="stat-card stat-progress">
        <h3><?= $statusCounts['In Progress'] ?? 0 ?></h3>
        <p>In Progress</p>
      </div>
      <div class="stat-card stat-collected">
        <h3><?= $statusCounts['Collected'] ?? 0 ?></h3>
        <p>Collected</p>
      </div>
      <div class="stat-card stat-rejected">
        <h3><?= $statusCounts['Rejected'] ?? 0 ?></h3>
        <p>Rejected</p>
      </div>
    </div>

    <div class="filters">
      <form method="get" style="display: flex; gap: 1rem; flex-wrap: wrap; width: 100%;">
        <select name="filter">
          <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Status</option>
          <option value="Pending" <?= $filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
          <option value="Approved" <?= $filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
          <option value="In Progress" <?= $filter === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
          <option value="Collected" <?= $filter === 'Collected' ? 'selected' : '' ?>>Collected</option>
          <option value="Rejected" <?= $filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
          <option value="Canceled" <?= $filter === 'Canceled' ? 'selected' : '' ?>>Canceled</option>
        </select>
        <input type="text" name="search" placeholder="Search by user or waste type..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Filter</button>
        <a href="view_requests.php" style="padding: 0.5rem 1rem; background: #6c757d; color: white; border-radius: 4px; text-decoration: none;">Reset</a>
      </form>
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>User Contact</th>
          <th>Waste Type</th>
          <th>Quantity</th>
          <th>Location & Navigation</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($res->num_rows > 0): ?>
          <?php while ($row = $res->fetch_assoc()): 
            $encoded_address = urlencode($row['address']);
            $google_maps_url = "https://www.google.com/maps/search/?api=1&query=" . $encoded_address;
            $waze_url = "https://www.waze.com/ul?q=" . $encoded_address;
          ?>
            <tr>
              <td><strong>#<?= htmlspecialchars($row['request_id']) ?></strong></td>
              <td>
                <div style="line-height: 1.6;">
                  <strong><i class="fas fa-user"></i> <?= htmlspecialchars($row['full_name']) ?></strong><br>
                  <a href="tel:<?= htmlspecialchars($row['phone']) ?>" style="color: #11998e; text-decoration: none; font-size: 0.9em;">
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($row['phone']) ?>
                  </a><br>
                  <a href="mailto:<?= htmlspecialchars($row['email']) ?>" style="color: #666; text-decoration: none; font-size: 0.85em;">
                    <i class="fas fa-envelope"></i> <?= htmlspecialchars($row['email']) ?>
                  </a>
                </div>
              </td>
              <td><?= htmlspecialchars($row['waste_type']) ?></td>
              <td><strong><?= number_format($row['quantity_kg'], 1) ?> kg</strong></td>
              <td style="min-width: 280px;">
                <div style="margin-bottom: 8px;">
                  <i class="fas fa-map-marker-alt" style="color: #e74c3c;"></i>
                  <strong><?= htmlspecialchars($row['address']) ?></strong>
                </div>
                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                  <a href="<?= $google_maps_url ?>" target="_blank" 
                     style="display: inline-flex; align-items: center; gap: 4px; padding: 5px 10px; background: #4285f4; color: white; border-radius: 4px; text-decoration: none; font-size: 0.85em;">
                    <i class="fas fa-map"></i> Google Maps
                  </a>
                  <a href="<?= $waze_url ?>" target="_blank"
                     style="display: inline-flex; align-items: center; gap: 4px; padding: 5px 10px; background: #33ccff; color: white; border-radius: 4px; text-decoration: none; font-size: 0.85em;">
                    <i class="fas fa-route"></i> Waze
                  </a>
                  <a href="tel:<?= htmlspecialchars($row['phone']) ?>"
                     style="display: inline-flex; align-items: center; gap: 4px; padding: 5px 10px; background: #27ae60; color: white; border-radius: 4px; text-decoration: none; font-size: 0.85em;">
                    <i class="fas fa-phone-alt"></i> Call
                  </a>
                </div>
              </td>
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
            <td colspan="7" class="no-data">No requests found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </main>

  <script>
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
      document.getElementById('dashboardClock').innerHTML = `${date} ${time}`;
    }
    setInterval(updateClock, 1000);
    updateClock();
  </script>
</body>

</html>
<?php $conn->close(); ?>