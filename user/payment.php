<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
include('../db_connect.php');

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch user details
$user = $conn->query("SELECT full_name, email, phone FROM users WHERE user_id=$user_id")->fetch_assoc();

// Fetch unpaid/pending payment requests
$requests = $conn->query("
    SELECT wr.request_id, wr.waste_type, wr.quantity_kg, wr.address, wr.request_date
    FROM waste_requests wr
    LEFT JOIN payments p ON wr.request_id = p.request_id AND p.payment_status = 'Paid'
    WHERE wr.user_id = $user_id 
    AND wr.status IN ('Collected', 'Approved')
    AND p.payment_id IS NULL
    ORDER BY wr.request_date DESC
");

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = intval($_POST['request_id']);
    $amount = floatval($_POST['amount']);
    $method = $conn->real_escape_string($_POST['payment_method']);
    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
    $transaction = $conn->real_escape_string($_POST['transaction_id'] ?? '');

    if ($amount <= 0 || $request_id <= 0) {
        $error = 'Invalid amount or request selected.';
    } else {
        $stmt = $conn->prepare("INSERT INTO payments (user_id, request_id, amount, payment_method, payment_status, transaction_id) VALUES (?, ?, ?, ?, 'Pending', ?)");
        $stmt->bind_param("iidss", $user_id, $request_id, $amount, $method, $transaction);

        if ($stmt->execute()) {
            $success = 'Payment submitted successfully! Your transaction is being processed.';
        } else {
            $error = 'Payment failed. Please try again.';
        }
        $stmt->close();
    }
}

// Calculate pricing (example: UGX 1000 per kg)
$price_per_kg = 1000;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - Smart Waste Management</title>
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .payment-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.4);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .payment-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .payment-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            background: white;
            color: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .payment-header h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .payment-header p {
            opacity: 0.95;
        }

        .payment-body {
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

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h3 {
            color: #2d3436;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        .form-group select {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 3rem;
            border: 2px solid #ddd;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        /* Payment Method Selection */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .payment-method-card {
            position: relative;
        }

        .payment-method-card input[type="radio"] {
            display: none;
        }

        .payment-method-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.8rem;
            padding: 1.5rem;
            border: 2px solid #ddd;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            text-align: center;
        }

        .payment-method-label i {
            font-size: 2.5rem;
            color: #636e72;
            transition: all 0.3s;
        }

        .payment-method-label .method-name {
            font-weight: 600;
            color: #2d3436;
            font-size: 1rem;
        }

        .payment-method-label .method-desc {
            font-size: 0.8rem;
            color: #999;
        }

        .payment-method-card input[type="radio"]:checked + .payment-method-label {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .payment-method-card input[type="radio"]:checked + .payment-method-label i,
        .payment-method-card input[type="radio"]:checked + .payment-method-label .method-name,
        .payment-method-card input[type="radio"]:checked + .payment-method-label .method-desc {
            color: white;
        }

        .payment-method-label:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        /* Mobile Money Options */
        .mobile-money-options {
            display: none;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .mobile-money-options.active {
            display: grid;
        }

        .mm-option {
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .mm-option input[type="radio"] {
            display: none;
        }

        .mm-option.selected {
            border-color: #667eea;
            background: #f0f3ff;
        }

        .mm-logo {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .mtn-logo {
            background: #ffcb05;
            color: #000;
        }

        .airtel-logo {
            background: #e00;
            color: #fff;
        }

        /* Bank Options */
        .bank-select {
            display: none;
            margin-top: 1rem;
        }

        .bank-select.active {
            display: block;
        }

        /* Summary Box */
        .payment-summary {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #667eea;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            font-size: 0.95rem;
        }

        .summary-item.total {
            font-size: 1.3rem;
            font-weight: 700;
            color: #667eea;
            padding-top: 0.8rem;
            border-top: 2px solid #ddd;
        }

        .btn-submit {
            width: 100%;
            padding: 1.1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.6);
        }

        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .back-link:hover {
            gap: 1rem;
            color: #5568d3;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 1rem 0.5rem;
            }

            .payment-container {
                border-radius: 16px;
            }

            .payment-body {
                padding: 1.5rem;
            }

            .payment-methods {
                grid-template-columns: 1fr;
            }

            .mobile-money-options {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .payment-header {
                padding: 1.2rem;
            }

            .payment-icon {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }

            .payment-header h2 {
                font-size: 1.4rem;
            }

            .payment-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <div class="payment-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <h2>Make Payment</h2>
            <p>Secure payment for your waste collection service</p>
        </div>

        <div class="payment-body">
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

            <form method="post" id="paymentForm">
                <!-- Request Selection -->
                <div class="form-section">
                    <h3><i class="fas fa-file-invoice"></i> Select Request to Pay</h3>
                    <div class="form-group">
                        <label>Choose Request <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-list input-icon"></i>
                            <select name="request_id" id="requestSelect" required>
                                <option value="">-- Select Request --</option>
                                <?php while($r = $requests->fetch_assoc()): ?>
                                    <option value="<?= $r['request_id'] ?>" data-weight="<?= $r['quantity_kg'] ?>">
                                        Request #<?= $r['request_id'] ?> - <?= htmlspecialchars($r['waste_type']) ?> (<?= $r['quantity_kg'] ?>kg)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Payment Summary -->
                <div class="payment-summary" id="paymentSummary" style="display: none;">
                    <h4 style="margin-bottom: 1rem;">Payment Summary</h4>
                    <div class="summary-item">
                        <span>Quantity:</span>
                        <span id="summaryWeight">0 kg</span>
                    </div>
                    <div class="summary-item">
                        <span>Rate:</span>
                        <span>UGX <?= number_format($price_per_kg) ?> per kg</span>
                    </div>
                    <div class="summary-item total">
                        <span>Total Amount:</span>
                        <span id="summaryTotal">UGX 0</span>
                    </div>
                </div>

                <!-- Amount -->
                <div class="form-group">
                    <label>Amount (UGX) <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-money-bill-wave input-icon"></i>
                        <input type="number" name="amount" id="amountInput" placeholder="Enter amount" min="1000" required readonly>
                    </div>
                </div>

                <!-- Payment Method Selection -->
                <div class="form-section">
                    <h3><i class="fas fa-credit-card"></i> Select Payment Method</h3>
                    <div class="payment-methods">
                        <div class="payment-method-card">
                            <input type="radio" name="payment_method" id="mobile_money" value="Mobile Money" required>
                            <label for="mobile_money" class="payment-method-label">
                                <i class="fas fa-mobile-alt"></i>
                                <span class="method-name">Mobile Money</span>
                                <span class="method-desc">MTN & Airtel</span>
                            </label>
                        </div>

                        <div class="payment-method-card">
                            <input type="radio" name="payment_method" id="bank" value="Bank" required>
                            <label for="bank" class="payment-method-label">
                                <i class="fas fa-university"></i>
                                <span class="method-name">Bank Transfer</span>
                                <span class="method-desc">All Banks</span>
                            </label>
                        </div>

                        <div class="payment-method-card">
                            <input type="radio" name="payment_method" id="card" value="Card" required>
                            <label for="card" class="payment-method-label">
                                <i class="fas fa-credit-card"></i>
                                <span class="method-name">Debit/Credit Card</span>
                                <span class="method-desc">Visa, Mastercard</span>
                            </label>
                        </div>

                        <div class="payment-method-card">
                            <input type="radio" name="payment_method" id="cash" value="Cash" required>
                            <label for="cash" class="payment-method-label">
                                <i class="fas fa-money-bill"></i>
                                <span class="method-name">Cash</span>
                                <span class="method-desc">Pay on Collection</span>
                            </label>
                        </div>
                    </div>

                    <!-- Mobile Money Provider Selection -->
                    <div class="mobile-money-options" id="mobileMoneyOptions">
                        <div class="mm-option" data-provider="MTN">
                            <input type="radio" name="mm_provider" id="mtn" value="MTN">
                            <div class="mm-logo mtn-logo">MTN</div>
                            <div>
                                <div style="font-weight: 600;">MTN Mobile Money</div>
                                <div style="font-size: 0.85rem; color: #999;">*165#</div>
                            </div>
                        </div>
                        <div class="mm-option" data-provider="Airtel">
                            <input type="radio" name="mm_provider" id="airtel" value="Airtel">
                            <div class="mm-logo airtel-logo">Airtel</div>
                            <div>
                                <div style="font-weight: 600;">Airtel Money</div>
                                <div style="font-size: 0.85rem; color: #999;">*185#</div>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Selection -->
                    <div class="bank-select" id="bankSelect">
                        <div class="form-group">
                            <label>Select Bank</label>
                            <div class="input-wrapper">
                                <i class="fas fa-university input-icon"></i>
                                <select name="bank_name">
                                    <option value="">-- Select Bank --</option>
                                    <option value="Stanbic Bank">Stanbic Bank</option>
                                    <option value="Centenary Bank">Centenary Bank</option>
                                    <option value="DFCU Bank">DFCU Bank</option>
                                    <option value="Equity Bank">Equity Bank</option>
                                    <option value="Bank of Africa">Bank of Africa</option>
                                    <option value="Standard Chartered">Standard Chartered</option>
                                    <option value="Absa Bank">Absa Bank</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Phone Number (for Mobile Money) -->
                <div class="form-group" id="phoneGroup" style="display: none;">
                    <label>Phone Number <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone input-icon"></i>
                        <input type="tel" name="phone" placeholder="+256 700 000000" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                </div>

                <!-- Transaction ID / Reference -->
                <div class="form-group">
                    <label>Transaction ID / Reference</label>
                    <div class="input-wrapper">
                        <i class="fas fa-hashtag input-icon"></i>
                        <input type="text" name="transaction_id" placeholder="Enter reference number (optional)">
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-lock"></i>
                    <span>Proceed to Payment</span>
                </button>

                <a href="dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </form>
        </div>
    </div>

    <script>
        const requestSelect = document.getElementById('requestSelect');
        const amountInput = document.getElementById('amountInput');
        const paymentSummary = document.getElementById('paymentSummary');
        const summaryWeight = document.getElementById('summaryWeight');
        const summaryTotal = document.getElementById('summaryTotal');
        const pricePerKg = <?= $price_per_kg ?>;

        // Calculate amount based on request
        requestSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const weight = parseFloat(selectedOption.dataset.weight) || 0;
            const total = weight * pricePerKg;

            if (weight > 0) {
                amountInput.value = total;
                summaryWeight.textContent = weight + ' kg';
                summaryTotal.textContent = 'UGX ' + total.toLocaleString();
                paymentSummary.style.display = 'block';
            } else {
                amountInput.value = '';
                paymentSummary.style.display = 'none';
            }
        });

        // Payment method selection
        const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
        const mobileMoneyOptions = document.getElementById('mobileMoneyOptions');
        const bankSelect = document.getElementById('bankSelect');
        const phoneGroup = document.getElementById('phoneGroup');

        paymentMethods.forEach(method => {
            method.addEventListener('change', function() {
                // Hide all additional options
                mobileMoneyOptions.classList.remove('active');
                bankSelect.classList.remove('active');
                phoneGroup.style.display = 'none';

                // Show relevant options
                if (this.value === 'Mobile Money') {
                    mobileMoneyOptions.classList.add('active');
                    phoneGroup.style.display = 'block';
                } else if (this.value === 'Bank') {
                    bankSelect.classList.add('active');
                }
            });
        });

        // Mobile Money provider selection
        const mmOptions = document.querySelectorAll('.mm-option');
        mmOptions.forEach(option => {
            option.addEventListener('click', function() {
                mmOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        });

        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            if (!requestSelect.value) {
                e.preventDefault();
                alert('Please select a request to pay for');
                return false;
            }

            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!selectedMethod) {
                e.preventDefault();
                alert('Please select a payment method');
                return false;
            }

            if (selectedMethod.value === 'Mobile Money') {
                const mmProvider = document.querySelector('input[name="mm_provider"]:checked');
                if (!mmProvider) {
                    e.preventDefault();
                    alert('Please select mobile money provider (MTN or Airtel)');
                    return false;
                }
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
