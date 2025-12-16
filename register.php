<?php
include('db_connect.php');
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name = $conn->real_escape_string($_POST['full_name']);
  $email = $conn->real_escape_string($_POST['user_email']);
  $password = password_hash($_POST['user_password'], PASSWORD_DEFAULT);
  $phone = $conn->real_escape_string($_POST['phone']);
  $address = $conn->real_escape_string($_POST['address']);
  $role = 'user'; // Only allow user registration

  // Validate phone number (must not exceed 10 digits)
  $phone_digits = preg_replace('/[^0-9]/', '', $phone);
  if (strlen($phone_digits) > 10) {
    $error = 'Phone number cannot exceed 10 digits.';
  }
  // Check if email already exists
  elseif ($checkEmail = $conn->query("SELECT user_id FROM users WHERE email = '$email' LIMIT 1") and $checkEmail->num_rows > 0) {
    $error = 'This email is already registered.';
  } elseif ($role === 'admin') {
    $checkAdmin = $conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
    if ($checkAdmin && $checkAdmin->num_rows > 0) {
      $error = 'An admin account already exists.';
    } else {
      $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, address, role) VALUES (?,?,?,?,?,?)");
      $stmt->bind_param('ssssss', $full_name, $email, $password, $phone, $address, $role);
      if ($stmt->execute()) {
        header('Location: login.php?registered=1');
        exit();
      } else {
        $error = $stmt->error;
      }
    }
  } else {
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, phone, address, role) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param('ssssss', $full_name, $email, $password, $phone, $address, $role);
    if ($stmt->execute()) {
      header('Location: login.php?registered=1');
      exit();
    } else {
      $error = $stmt->error;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - Smart Waste Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="register-page">
  <!-- Animated Background Elements -->
  <div class="animated-bg">
    <div class="floating-shapes">
      <div class="shape shape-1"></div>
      <div class="shape shape-2"></div>
      <div class="shape shape-3"></div>
      <div class="shape shape-4"></div>
      <div class="shape shape-5"></div>
    </div>
  </div>

  <!-- Navigation -->
  <header class="register-nav">
    <div class="logo">
      <i class="fas fa-recycle"></i>
      <span>SmartWaste</span>
    </div>
    <nav>
      <ul>
        <li><a href="index.html"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="about.html"><i class="fas fa-info-circle"></i> About</a></li>
        <li><a href="contact.html"><i class="fas fa-envelope"></i> Contact</a></li>
        <li><a href="login.php" class="login-link"><i class="fas fa-sign-in-alt"></i> Login</a></li>
      </ul>
    </nav>
    <div class="menu-toggle" id="mobile-menu">
      <span></span><span></span><span></span>
    </div>
  </header>

  <!-- Register Container -->
  <div class="register-container">
    <div class="register-box">
      <div class="register-header">
        <div class="register-icon">
          <i class="fas fa-user-plus"></i>
        </div>
        <h2>Create Your Account</h2>
        <p>Join us to manage waste smarter</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error animate-slide-in">
          <i class="fas fa-exclamation-circle"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <form method="post" class="register-form">
        <div class="form-row">
          <!-- Full Name -->
          <div class="form-group">
            <label for="full_name"><i class="fas fa-user"></i> Full Name</label>
            <div class="input-wrapper">
              <i class="fas fa-user input-icon"></i>
              <input type="text" name="full_name" id="full_name" placeholder="Enter your full name" required>
            </div>
          </div>

          <!-- Email -->
          <div class="form-group">
            <label for="user_email"><i class="fas fa-envelope"></i> Email Address</label>
            <div class="input-wrapper">
              <i class="fas fa-envelope input-icon"></i>
              <input type="email" name="user_email" id="user_email" placeholder="Enter your email" required>
            </div>
          </div>
        </div>

        <div class="form-row">
          <!-- Password -->
          <div class="form-group">
            <label for="user_password"><i class="fas fa-lock"></i> Password</label>
            <div class="input-wrapper">
              <i class="fas fa-lock input-icon"></i>
              <input type="password" name="user_password" id="user_password" placeholder="Create password" required>
              <i class="fas fa-eye toggle-password" id="togglePassword"></i>
            </div>
          </div>

          <!-- Phone -->
          <div class="form-group">
            <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
            <div class="input-wrapper">
              <i class="fas fa-phone input-icon"></i>
              <input type="tel" name="phone" id="phone" placeholder="0700000000" maxlength="10" pattern="[0-9]{10}" title="Please enter exactly 10 digits">
            </div>
            <small style="color: #666; font-size: 0.85em; margin-top: 4px; display: block;">Enter 10 digits only (e.g., 0752123456)</small>
          </div>
        </div>

        <!-- Address -->
        <div class="form-group">
          <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
          <div class="input-wrapper">
            <i class="fas fa-map-marker-alt input-icon"></i>
            <textarea name="address" id="address" rows="3" placeholder="Enter your address"></textarea>
          </div>
        </div>

        <!-- Hidden Role - Always User -->
        <input type="hidden" name="role" value="user">

        <!-- Terms & Conditions -->
        <div class="checkbox-group">
          <label class="checkbox-label">
            <input type="checkbox" required>
            <span>I agree to the <a href="#">Terms & Conditions</a> and <a href="#">Privacy Policy</a></span>
          </label>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn-register">
          <span>Create Account</span>
          <i class="fas fa-arrow-right"></i>
        </button>

        <!-- Login Link -->
        <div class="form-footer">
          <p>Already have an account? <a href="login.php">Login Here</a></p>
        </div>
      </form>
    </div>

    <!-- Info Panel -->
    <div class="info-panel">
      <div class="info-content">
        <div class="info-illustration">
          <i class="fas fa-recycle"></i>
          <i class="fas fa-earth-africa"></i>
          <i class="fas fa-seedling"></i>
        </div>
        <h3>Why Join Us?</h3>
        <p>Be part of the solution to create a cleaner, greener environment for future generations.</p>
        <div class="features-list">
          <div class="feature-item">
            <div class="feature-icon">
              <i class="fas fa-bolt"></i>
            </div>
            <div class="feature-text">
              <h4>Quick & Easy</h4>
              <p>Register in less than a minute and start managing waste efficiently</p>
            </div>
          </div>
          <div class="feature-item">
            <div class="feature-icon">
              <i class="fas fa-shield-alt"></i>
            </div>
            <div class="feature-text">
              <h4>Secure Platform</h4>
              <p>Your personal information is protected with top-tier security</p>
            </div>
          </div>
          <div class="feature-item">
            <div class="feature-icon">
              <i class="fas fa-headset"></i>
            </div>
            <div class="feature-text">
              <h4>24/7 Support</h4>
              <p>Our team is always here to help you with any questions</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="register-footer">
    <p>&copy; 2025 Smart Waste Management System | Designed with <i class="fas fa-heart"></i> by Apollo</p>
  </footer>

  <script>
    // Mobile Menu Toggle
    const menuToggle = document.getElementById('mobile-menu');
    const navLinks = document.querySelector('nav ul');

    menuToggle.addEventListener('click', () => {
      navLinks.classList.toggle('show');
      menuToggle.classList.toggle('active');
    });

    // Password Toggle
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('user_password');

    if (togglePassword && passwordInput) {
      togglePassword.addEventListener('click', () => {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        togglePassword.classList.toggle('fa-eye');
        togglePassword.classList.toggle('fa-eye-slash');
      });
    }

    // Phone number validation
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
      phoneInput.addEventListener('input', function(e) {
        // Remove any non-digit characters
        let value = e.target.value.replace(/[^0-9]/g, '');
        
        // Limit to 10 digits
        if (value.length > 10) {
          value = value.substring(0, 10);
        }
        
        e.target.value = value;
      });
    }

    // Form validation
    const form = document.querySelector('.register-form');
    form.addEventListener('submit', (e) => {
      const password = document.getElementById('user_password').value;
      const phone = document.getElementById('phone').value;
      const phoneDigits = phone.replace(/[^0-9]/g, '');
      
      if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long');
        return;
      }
      
      if (phoneDigits.length !== 10) {
        e.preventDefault();
        alert('Phone number must be exactly 10 digits');
        return;
      }
    });
  </script>
</body>

</html>