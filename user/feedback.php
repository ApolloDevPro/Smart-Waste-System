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
$user = $conn->query("SELECT full_name, email FROM users WHERE user_id=$user_id")->fetch_assoc();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = $conn->real_escape_string($_POST['subject']);
    $message = $conn->real_escape_string($_POST['message']);
    $type = $conn->real_escape_string($_POST['feedback_type']);

    if (empty($subject) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $conn->prepare('INSERT INTO feedback (user_id, subject, message, feedback_type) VALUES (?,?,?,?)');
        $stmt->bind_param('isss', $user_id, $subject, $message, $type);
        
        if ($stmt->execute()) {
            $feedback_id = $conn->insert_id;
            $success = 'Thank you! Your feedback has been submitted successfully.';
            
            // Send notification to user
            $notif = new Notification($conn);
            $notif->create(
                $user_id,
                "Feedback Submitted",
                "Your feedback has been submitted successfully. We will review it shortly.",
                'feedback',
                $feedback_id
            );
            
            // Notify all admins about new feedback
            $notif->createForRole(
                'admin',
                "New Feedback from User",
                "{$user['full_name']} submitted feedback: $subject ($type)",
                'feedback',
                $feedback_id,
                '../admin/dashboard.php'
            );
        } else {
            $error = 'Failed to submit feedback. Please try again.';
        }
        $stmt->close();
    }
}

// Fetch user's previous feedback
$previous_feedback = $conn->query("
    SELECT feedback_id, subject, message, feedback_type, status, date_submitted
    FROM feedback
    WHERE user_id = $user_id
    ORDER BY date_submitted DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Smart Waste Management</title>
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
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .feedback-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .feedback-form-section,
        .previous-feedback-section {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .section-icon {
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
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .section-header h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .section-header p {
            opacity: 0.95;
            font-size: 0.95rem;
        }

        .section-body {
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

        /* Feedback Type Selection */
        .feedback-types {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .feedback-type-card {
            position: relative;
        }

        .feedback-type-card input[type="radio"] {
            display: none;
        }

        .feedback-type-label {
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
        }

        .feedback-type-label i {
            font-size: 2.2rem;
            color: #636e72;
            transition: all 0.3s;
        }

        .feedback-type-label span {
            font-weight: 600;
            color: #2d3436;
            font-size: 0.95rem;
        }

        .feedback-type-card input[type="radio"]:checked + .feedback-type-label {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .feedback-type-card input[type="radio"]:checked + .feedback-type-label i,
        .feedback-type-card input[type="radio"]:checked + .feedback-type-label span {
            color: white;
        }

        .feedback-type-label:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
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
            top: 1rem;
            color: #636e72;
            font-size: 1.1rem;
        }

        .form-group input,
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
            min-height: 150px;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .char-counter {
            text-align: right;
            font-size: 0.85rem;
            color: #999;
            margin-top: 0.3rem;
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

        /* Previous Feedback */
        .previous-feedback-section {
            max-height: 800px;
            display: flex;
            flex-direction: column;
        }

        .feedback-list {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
        }

        .feedback-list::-webkit-scrollbar {
            width: 8px;
        }

        .feedback-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .feedback-list::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }

        .feedback-item {
            background: #f8f9fa;
            padding: 1.2rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border-left: 4px solid;
        }

        .feedback-item.type-Complaint { border-left-color: #e74c3c; }
        .feedback-item.type-Suggestion { border-left-color: #3498db; }
        .feedback-item.type-Inquiry { border-left-color: #f39c12; }
        .feedback-item.type-Other { border-left-color: #95a5a6; }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.8rem;
            gap: 1rem;
        }

        .feedback-subject {
            font-weight: 700;
            color: #2d3436;
            font-size: 1.05rem;
        }

        .feedback-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-New { background: #fff3cd; color: #856404; }
        .status-Reviewed { background: #d1ecf1; color: #0c5460; }
        .status-Resolved { background: #d4edda; color: #155724; }

        .feedback-message {
            color: #636e72;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 0.8rem;
        }

        .feedback-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #999;
            gap: 1rem;
        }

        .feedback-type-badge {
            padding: 0.3rem 0.7rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .type-Complaint { background: #ffebee; color: #e74c3c; }
        .type-Suggestion { background: #e3f2fd; color: #3498db; }
        .type-Inquiry { background: #fff3e0; color: #f39c12; }
        .type-Other { background: #f5f5f5; color: #95a5a6; }

        .no-feedback {
            text-align: center;
            padding: 3rem 2rem;
            color: #999;
        }

        .no-feedback i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .feedback-container {
                grid-template-columns: 1fr;
            }

            .previous-feedback-section {
                max-height: 500px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem 0.5rem;
            }

            .section-body {
                padding: 1.5rem;
            }

            .feedback-types {
                grid-template-columns: 1fr;
            }

            .feedback-list {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .section-header {
                padding: 1.2rem;
            }

            .section-icon {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }

            .section-header h2 {
                font-size: 1.4rem;
            }

            .section-body {
                padding: 1rem;
            }

            .feedback-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .feedback-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="feedback-container">
        <!-- Feedback Form Section -->
        <div class="feedback-form-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <h2>Send Feedback</h2>
                <p>We value your opinion and suggestions</p>
            </div>

            <div class="section-body">
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

                <form method="post">
                    <!-- Feedback Type Selection -->
                    <div class="form-group">
                        <label>Feedback Type <span class="required">*</span></label>
                        <div class="feedback-types">
                            <div class="feedback-type-card">
                                <input type="radio" name="feedback_type" id="complaint" value="Complaint" required>
                                <label for="complaint" class="feedback-type-label">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Complaint</span>
                                </label>
                            </div>

                            <div class="feedback-type-card">
                                <input type="radio" name="feedback_type" id="suggestion" value="Suggestion" required>
                                <label for="suggestion" class="feedback-type-label">
                                    <i class="fas fa-lightbulb"></i>
                                    <span>Suggestion</span>
                                </label>
                            </div>

                            <div class="feedback-type-card">
                                <input type="radio" name="feedback_type" id="inquiry" value="Inquiry" required>
                                <label for="inquiry" class="feedback-type-label">
                                    <i class="fas fa-question-circle"></i>
                                    <span>Inquiry</span>
                                </label>
                            </div>

                            <div class="feedback-type-card">
                                <input type="radio" name="feedback_type" id="other" value="Other" required>
                                <label for="other" class="feedback-type-label">
                                    <i class="fas fa-comment-dots"></i>
                                    <span>Other</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Subject -->
                    <div class="form-group">
                        <label for="subject">Subject <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-heading input-icon"></i>
                            <input type="text" name="subject" id="subject" placeholder="Brief subject of your feedback" required maxlength="100">
                        </div>
                        <div class="char-counter">
                            <span id="subjectCounter">0</span>/100
                        </div>
                    </div>

                    <!-- Message -->
                    <div class="form-group">
                        <label for="message">Message <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <i class="fas fa-comment-alt input-icon"></i>
                            <textarea name="message" id="message" placeholder="Share your thoughts, suggestions, or concerns with us..." required maxlength="500"></textarea>
                        </div>
                        <div class="char-counter">
                            <span id="messageCounter">0</span>/500
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i>
                        <span>Submit Feedback</span>
                    </button>

                    <a href="dashboard.php" class="back-link">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Dashboard</span>
                    </a>
                </form>
            </div>
        </div>

        <!-- Previous Feedback Section -->
        <div class="previous-feedback-section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h2>Your Feedback History</h2>
                <p>Track your submitted feedback</p>
            </div>

            <div class="feedback-list">
                <?php if ($previous_feedback->num_rows > 0): ?>
                    <?php while ($fb = $previous_feedback->fetch_assoc()): ?>
                        <div class="feedback-item type-<?= $fb['feedback_type'] ?>">
                            <div class="feedback-header">
                                <div class="feedback-subject">
                                    <?= htmlspecialchars($fb['subject']) ?>
                                </div>
                                <span class="feedback-badge status-<?= $fb['status'] ?>">
                                    <?= htmlspecialchars($fb['status']) ?>
                                </span>
                            </div>
                            <div class="feedback-message">
                                <?= htmlspecialchars($fb['message']) ?>
                            </div>
                            <div class="feedback-meta">
                                <span class="feedback-type-badge type-<?= $fb['feedback_type'] ?>">
                                    <?= htmlspecialchars($fb['feedback_type']) ?>
                                </span>
                                <span>
                                    <i class="fas fa-clock"></i>
                                    <?= date('M d, Y', strtotime($fb['date_submitted'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-feedback">
                        <i class="fas fa-inbox"></i>
                        <h3>No Feedback Yet</h3>
                        <p>Your submitted feedback will appear here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Character counters
        const subjectInput = document.getElementById('subject');
        const messageInput = document.getElementById('message');
        const subjectCounter = document.getElementById('subjectCounter');
        const messageCounter = document.getElementById('messageCounter');

        subjectInput.addEventListener('input', function() {
            subjectCounter.textContent = this.value.length;
        });

        messageInput.addEventListener('input', function() {
            messageCounter.textContent = this.value.length;
        });

        // Auto-resize textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
