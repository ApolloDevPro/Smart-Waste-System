<?php
session_start();

if (!isset($_SESSION['user_id'])) { 
    header('Location: ../login.php'); 
    exit(); 
}

include('../db_connect.php');
$user_id = $_SESSION['user_id'];

// Fetch user details
$user = $conn->query("SELECT full_name FROM users WHERE user_id=$user_id")->fetch_assoc();

// Fetch all waste requests with additional details
$requests = $conn->query("
    SELECT request_id, waste_type, quantity_kg, status, request_date, address, description, collection_date
    FROM waste_requests 
    WHERE user_id=$user_id 
    ORDER BY request_date DESC
");

// Get statistics
$total_requests = $conn->query("SELECT COUNT(*) AS c FROM waste_requests WHERE user_id=$user_id")->fetch_assoc()['c'];
$pending = $conn->query("SELECT COUNT(*) AS c FROM waste_requests WHERE user_id=$user_id AND status='Pending'")->fetch_assoc()['c'];
$approved = $conn->query("SELECT COUNT(*) AS c FROM waste_requests WHERE user_id=$user_id AND status='Approved'")->fetch_assoc()['c'];
$collected = $conn->query("SELECT COUNT(*) AS c FROM waste_requests WHERE user_id=$user_id AND status='Collected'")->fetch_assoc()['c'];
$in_progress = $conn->query("SELECT COUNT(*) AS c FROM waste_requests WHERE user_id=$user_id AND status='In Progress'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - Smart Waste Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        /* Header */
        .requests-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-left h1 {
            font-size: 2rem;
            margin-bottom: 0.3rem;
        }

        .header-left p {
            opacity: 0.95;
            font-size: 1rem;
        }

        .back-btn {
            background: white;
            color: #667eea;
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* Main Content */
        main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s;
            border-top: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }

        .stat-card h3 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .stat-card p {
            color: #636e72;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-total { border-top-color: #3498db; }
        .stat-total h3 { color: #3498db; }

        .stat-pending { border-top-color: #f39c12; }
        .stat-pending h3 { color: #f39c12; }

        .stat-approved { border-top-color: #9b59b6; }
        .stat-approved h3 { color: #9b59b6; }

        .stat-progress { border-top-color: #16a085; }
        .stat-progress h3 { color: #16a085; }

        .stat-collected { border-top-color: #27ae60; }
        .stat-collected h3 { color: #27ae60; }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-section input,
        .filter-section select {
            padding: 0.7rem 1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .filter-section input:focus,
        .filter-section select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box {
            flex: 1;
            min-width: 250px;
        }

        .filter-select {
            min-width: 150px;
        }

        /* Requests Grid/List */
        .requests-container {
            display: grid;
            gap: 1.5rem;
        }

        .request-card {
            background: white;
            padding: 1.8rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 1.5rem;
            align-items: center;
        }

        .request-card:hover {
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }

        .request-icon {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
        }

        .icon-organic { background: #e8f5e9; color: #27ae60; }
        .icon-plastic { background: #e3f2fd; color: #2196f3; }
        .icon-paper { background: #fff3e0; color: #ff9800; }
        .icon-metal { background: #fce4ec; color: #e91e63; }
        .icon-glass { background: #f3e5f5; color: #9c27b0; }
        .icon-ewaste { background: #e0f2f1; color: #009688; }
        .icon-other { background: #f5f5f5; color: #607d8b; }

        .request-details {
            flex: 1;
        }

        .request-header-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.8rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .request-id {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2d3436;
        }

        .request-type {
            font-size: 1.1rem;
            color: #636e72;
            font-weight: 500;
        }

        .request-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 0.8rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #636e72;
        }

        .info-item i {
            color: #667eea;
            width: 20px;
        }

        .request-description {
            color: #636e72;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid #eee;
        }

        .request-actions {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            align-items: flex-end;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .status-Pending { background: #fff3cd; color: #856404; }
        .status-Approved { background: #d4edda; color: #155724; }
        .status-In.Progress { background: #d1ecf1; color: #0c5460; }
        .status-Collected { background: #d6d8db; color: #383d41; }
        .status-Rejected { background: #f8d7da; color: #721c24; }
        .status-Canceled { background: #e2e3e5; color: #383d41; }

        .request-date {
            font-size: 0.85rem;
            color: #999;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .no-requests {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .no-requests i {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 1.5rem;
        }

        .no-requests h3 {
            font-size: 1.5rem;
            color: #636e72;
            margin-bottom: 1rem;
        }

        .no-requests p {
            color: #999;
            margin-bottom: 1.5rem;
        }

        .btn-new-request {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-new-request:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .request-card {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .request-actions {
                flex-direction: row;
                justify-content: space-between;
                width: 100%;
            }

            .request-icon {
                width: 60px;
                height: 60px;
                font-size: 1.7rem;
            }
        }

        @media (max-width: 768px) {
            .requests-header {
                padding: 1.5rem;
            }

            .header-left h1 {
                font-size: 1.6rem;
            }

            main {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box,
            .filter-select {
                width: 100%;
            }

            .request-info {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .requests-header {
                padding: 1rem;
            }

            .header-left h1 {
                font-size: 1.4rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 1.2rem;
            }

            .stat-card h3 {
                font-size: 2rem;
            }

            .request-card {
                padding: 1.2rem;
            }

            .request-id {
                font-size: 1.2rem;
            }

            .request-header-info {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="requests-header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-list-alt"></i> My Requests</h1>
                <p>View and track all your waste collection requests</p>
            </div>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card stat-total">
                <h3><?= number_format($total_requests) ?></h3>
                <p>Total Requests</p>
            </div>
            <div class="stat-card stat-pending">
                <h3><?= number_format($pending) ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-card stat-approved">
                <h3><?= number_format($approved) ?></h3>
                <p>Approved</p>
            </div>
            <div class="stat-card stat-progress">
                <h3><?= number_format($in_progress) ?></h3>
                <p>In Progress</p>
            </div>
            <div class="stat-card stat-collected">
                <h3><?= number_format($collected) ?></h3>
                <p>Collected</p>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <input type="text" id="searchInput" class="search-box" placeholder="Search by ID, type, or address...">
            <select id="filterStatus" class="filter-select">
                <option value="">All Status</option>
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
                <option value="In Progress">In Progress</option>
                <option value="Collected">Collected</option>
                <option value="Rejected">Rejected</option>
            </select>
            <select id="filterType" class="filter-select">
                <option value="">All Types</option>
                <option value="Organic">Organic</option>
                <option value="Plastic">Plastic</option>
                <option value="Paper">Paper</option>
                <option value="Metal">Metal</option>
                <option value="Glass">Glass</option>
                <option value="E-waste">E-waste</option>
                <option value="Other">Other</option>
            </select>
        </div>

        <!-- Requests List -->
        <div class="requests-container" id="requestsContainer">
            <?php if ($requests->num_rows > 0): ?>
                <?php while($req = $requests->fetch_assoc()): ?>
                    <div class="request-card" data-status="<?= $req['status'] ?>" data-type="<?= $req['waste_type'] ?>">
                        <div class="request-icon icon-<?= strtolower($req['waste_type']) ?>">
                            <?php
                            $icons = [
                                'Organic' => 'fa-leaf',
                                'Plastic' => 'fa-bottle-water',
                                'Paper' => 'fa-newspaper',
                                'Metal' => 'fa-cog',
                                'Glass' => 'fa-wine-bottle',
                                'E-waste' => 'fa-mobile-alt',
                                'Other' => 'fa-boxes'
                            ];
                            ?>
                            <i class="fas <?= $icons[$req['waste_type']] ?? 'fa-trash' ?>"></i>
                        </div>

                        <div class="request-details">
                            <div class="request-header-info">
                                <div>
                                    <div class="request-id">Request #<?= $req['request_id'] ?></div>
                                    <div class="request-type"><?= htmlspecialchars($req['waste_type']) ?></div>
                                </div>
                            </div>

                            <div class="request-info">
                                <div class="info-item">
                                    <i class="fas fa-weight"></i>
                                    <span><?= htmlspecialchars($req['quantity_kg']) ?> kg</span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?= htmlspecialchars(substr($req['address'], 0, 30)) ?><?= strlen($req['address']) > 30 ? '...' : '' ?></span>
                                </div>
                                <?php if ($req['collection_date']): ?>
                                    <div class="info-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= date('M d, Y', strtotime($req['collection_date'])) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($req['description']): ?>
                                <div class="request-description">
                                    <i class="fas fa-comment-alt"></i>
                                    <?= htmlspecialchars($req['description']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="request-actions">
                            <span class="status-badge status-<?= str_replace(' ', '.', $req['status']) ?>">
                                <?= htmlspecialchars($req['status']) ?>
                            </span>
                            <div class="request-date">
                                <i class="fas fa-clock"></i>
                                <?= date('M d, Y', strtotime($req['request_date'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-requests">
                    <i class="fas fa-inbox"></i>
                    <h3>No Requests Yet</h3>
                    <p>You haven't made any waste collection requests.</p>
                    <a href="request_form.php" class="btn-new-request">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create Your First Request</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Search and Filter functionality
        const searchInput = document.getElementById('searchInput');
        const filterStatus = document.getElementById('filterStatus');
        const filterType = document.getElementById('filterType');
        const requestCards = document.querySelectorAll('.request-card');

        function filterRequests() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusFilter = filterStatus.value;
            const typeFilter = filterType.value;

            requestCards.forEach(card => {
                const cardText = card.textContent.toLowerCase();
                const cardStatus = card.dataset.status;
                const cardType = card.dataset.type;

                const matchesSearch = cardText.includes(searchTerm);
                const matchesStatus = !statusFilter || cardStatus === statusFilter;
                const matchesType = !typeFilter || cardType === typeFilter;

                if (matchesSearch && matchesStatus && matchesType) {
                    card.style.display = 'grid';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterRequests);
        filterStatus.addEventListener('change', filterRequests);
        filterType.addEventListener('change', filterRequests);
    </script>
</body>
</html>
<?php $conn->close(); ?>
