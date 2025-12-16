<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

include('../db_connect.php');
require_once('../includes/Notification.php');
$user_id = $_SESSION['user_id'];

// Fetch user details
$user = $conn->query("SELECT full_name, email, phone, address FROM users WHERE user_id=$user_id")->fetch_assoc();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $waste_type = $conn->real_escape_string($_POST['waste_type']);
    $quantity = floatval($_POST['quantity']);
    $description = $conn->real_escape_string($_POST['description']);
    $address = $conn->real_escape_string($_POST['address']);
    $collection_date = $conn->real_escape_string($_POST['collection_date']);

    if ($quantity <= 0) {
        $error = 'Please enter a valid quantity.';
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO waste_requests (user_id, waste_type, quantity_kg, description, address, collection_date) VALUES (?,?,?,?,?,?)'
        );
        $stmt->bind_param('isdsss', $user_id, $waste_type, $quantity, $description, $address, $collection_date);

        if ($stmt->execute()) {
            $request_id = $conn->insert_id;
            $success = 'Request submitted successfully! We will process it soon.';
            
            // Send notification to user
            $notif = new Notification($conn);
            $notif->create(
                $user_id,
                "Request Submitted",
                "Your waste collection request (#$request_id) for $waste_type has been submitted successfully.",
                'request',
                $request_id,
                'view_requests.php'
            );
            
            // Notify all admins
            $notif->createForRole(
                'admin',
                "New Waste Collection Request",
                "User {$user['full_name']} submitted a new request for $waste_type ($quantity kg).",
                'request',
                $request_id,
                '../admin/view_requests.php'
            );
            
            // Send email to user
            $notif->sendEmail(
                $user_id,
                "Waste Collection Request Submitted",
                "Your waste collection request has been submitted successfully. Request ID: #$request_id\n\nWaste Type: $waste_type\nQuantity: $quantity kg\nPreferred Collection Date: $collection_date\n\nWe will process your request shortly."
            );
        } else {
            $error = 'Error submitting request. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Waste Pickup - Smart Waste Management</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .request-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.4);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .request-header {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .request-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            background: white;
            color: #11998e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            animation: bounce 2s infinite;
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .request-header h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .request-header p {
            opacity: 0.95;
            font-size: 1rem;
        }

        .request-body {
            padding: 2.5rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 500;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #a8d5ba;
        }

        .alert-error {
            background: linear-gradient(135deg, #ffe5e5 0%, #ffcccc 100%);
            color: #c00;
            border: 2px solid #ff9999;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group-full {
            grid-column: 1 / -1;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            color: #2d3436;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .required {
            color: #e74c3c;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #636e72;
            font-size: 1.1rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 3rem;
            border: 2px solid #ddd;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #11998e;
            box-shadow: 0 0 0 4px rgba(17, 153, 142, 0.1);
        }

        .waste-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
        }

        .waste-type-card {
            position: relative;
        }

        .waste-type-card input[type="radio"] {
            display: none;
        }

        .waste-type-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1.2rem;
            border: 2px solid #ddd;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            background: white;
        }

        .waste-type-label i {
            font-size: 2.5rem;
            color: #636e72;
            transition: all 0.3s;
        }

        .waste-type-label span {
            font-weight: 600;
            color: #2d3436;
            font-size: 0.9rem;
        }

        .waste-type-card input[type="radio"]:checked+.waste-type-label {
            border-color: #11998e;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            box-shadow: 0 4px 15px rgba(17, 153, 142, 0.4);
        }

        .waste-type-card input[type="radio"]:checked+.waste-type-label i,
        .waste-type-card input[type="radio"]:checked+.waste-type-label span {
            color: white;
        }

        .waste-type-label:hover {
            border-color: #11998e;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 1.1rem;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            box-shadow: 0 6px 20px rgba(17, 153, 142, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 30px rgba(17, 153, 142, 0.6);
        }

        .btn-submit:active {
            transform: translateY(0) scale(0.98);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            color: #11998e;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .back-link:hover {
            gap: 1rem;
            color: #0d7a6e;
        }

        .info-box {
            background: #e8f5e9;
            padding: 1.2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #27ae60;
        }

        .info-box h4 {
            color: #27ae60;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-box ul {
            margin-left: 1.5rem;
            color: #2d3436;
        }

        .info-box li {
            margin-bottom: 0.3rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 1rem 0.5rem;
            }

            .request-container {
                border-radius: 16px;
            }

            .request-header {
                padding: 1.5rem;
            }

            .request-icon {
                width: 70px;
                height: 70px;
                font-size: 2rem;
            }

            .request-header h2 {
                font-size: 1.6rem;
            }

            .request-body {
                padding: 1.5rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .waste-type-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.8rem;
            }

            .waste-type-label {
                padding: 1rem;
            }

            .waste-type-label i {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .request-header {
                padding: 1.2rem;
            }

            .request-icon {
                width: 60px;
                height: 60px;
                font-size: 1.8rem;
            }

            .request-header h2 {
                font-size: 1.4rem;
            }

            .request-body {
                padding: 1rem;
            }

            .waste-type-grid {
                grid-template-columns: 1fr;
            }

            .btn-submit {
                padding: 0.9rem;
                font-size: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="request-container">
        <div class="request-header">
            <div class="request-icon">
                <i class="fas fa-recycle"></i>
            </div>
            <h2>Request Waste Pickup</h2>
            <p>Fill in the details below to schedule your waste collection</p>
        </div>

        <div class="request-body">
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> Before You Request:</h4>
                <ul>
                    <li>Ensure waste is properly sorted and bagged</li>
                    <li>Provide accurate weight estimates</li>
                    <li>Be available at the pickup location during collection</li>
                    <li>Payment is required after collection</li>
                </ul>
            </div>

            <form method="post">
                <!-- Waste Type Selection -->
                <div class="form-group form-group-full">
                    <label>
                        <i class="fas fa-trash-alt"></i>
                        Waste Type <span class="required">*</span>
                    </label>
                    <div class="waste-type-grid">
                        <div class="waste-type-card">
                            <input type="radio" name="waste_type" id="organic" value="Organic" required>
                            <label for="organic" class="waste-type-label">
                                <i class="fas fa-leaf"></i>
                                <span>Organic</span>
                            </label>
                        </div>
                        <div class="waste-type-card">
                            <input type="radio" name="waste_type" id="plastic" value="Plastic" required>
                            <label for="plastic" class="waste-type-label">
                                <i class="fas fa-bottle-water"></i>
                                <span>Plastic</span>
                            </label>
                        </div>
                        <div class="waste-type-card">
                            <input type="radio" name="waste_type" id="paper" value="Paper" required>
                            <label for="paper" class="waste-type-label">
                                <i class="fas fa-newspaper"></i>
                                <span>Paper</span>
                            </label>
                        </div>
                        <div class="waste-type-card">
                            <input type="radio" name="waste_type" id="metal" value="Metal" required>
                            <label for="metal" class="waste-type-label">
                                <i class="fas fa-cog"></i>
                                <span>Metal</span>
                            </label>
                        </div>
                        <div class="waste-type-card">
                            <input type="radio" name="waste_type" id="glass" value="Glass" required>
                            <label for="glass" class="waste-type-label">
                                <i class="fas fa-wine-bottle"></i>
                                <span>Glass</span>
                            </label>
                        </div>
                        <div class="waste-type-card">
                            <input type="radio" name="waste_type" id="ewaste" value="E-waste" required>
                            <label for="ewaste" class="waste-type-label">
                                <i class="fas fa-mobile-alt"></i>
                                <span>E-waste</span>
                            </label>
                        </div>
                        <div class="waste-type-card">
                            <input type="radio" name="waste_type" id="other" value="Other" required>
                            <label for="other" class="waste-type-label">
                                <i class="fas fa-boxes"></i>
                                <span>Other</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <!-- Quantity -->
                    <div class="form-group">
                        <label>
                            <i class="fas fa-weight"></i>
                            Quantity (kg) <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-weight input-icon"></i>
                            <input type="number" name="quantity" step="0.01" min="0.01" placeholder="e.g., 10.5" required>
                        </div>
                    </div>

                    <!-- Preferred Collection Date -->
                    <div class="form-group">
                        <label>
                            <i class="fas fa-calendar"></i>
                            Preferred Collection Date
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-calendar input-icon"></i>
                            <input type="date" name="collection_date" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>

                <!-- Pickup Address -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-map-marker-alt"></i>
                        Pickup Address <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-map-marker-alt input-icon"></i>
                        <textarea name="address" placeholder="Enter complete address with landmarks" required><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label>
                        <i class="fas fa-comment-alt"></i>
                        Additional Description
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-comment-alt input-icon"></i>
                        <textarea name="description" placeholder="Any special instructions or details about the waste..."></textarea>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i>
                    <span>Submit Request</span>
                </button>

                <a href="dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </form>
        </div>
    </div>
</body>

</html>
<?php $conn->close(); ?>