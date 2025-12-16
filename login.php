<?php
include('db_connect.php');
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $conn->real_escape_string($_POST['login_email']);
  $password = $_POST['login_password'];
  $role = $conn->real_escape_string($_POST['role']);

  $stmt = $conn->prepare('SELECT user_id, full_name, password FROM users WHERE email = ? AND role = ? LIMIT 1');
  $stmt->bind_param('ss', $email, $role);
  $stmt->execute();
  $stmt->bind_result($user_id, $full_name, $hash);

  if ($stmt->fetch() && password_verify($password, $hash)) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['role'] = $role;
    $_SESSION['name'] = $full_name;

    if ($role === 'admin') {
      header('Location: admin/dashboard.php');
    } elseif ($role === 'staff') {
      header('Location: staff/dashboard.php');
    } else {
      header('Location: user/dashboard.php');
    }
    exit();
  } else {
    $error = 'Invalid email, password, or role';
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Smart Waste Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="login-page">
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
  <header class="login-nav">
    <div class="logo">
      <i class="fas fa-recycle"></i>
      <span>SmartWaste</span>
    </div>
    <nav>
      <ul>
        <li><a href="index.html"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="about.html"><i class="fas fa-info-circle"></i> About</a></li>
        <li><a href="contact.html"><i class="fas fa-envelope"></i> Contact</a></li>
        <li><a href="register.php" class="register-link"><i class="fas fa-user-plus"></i> Register</a></li>
      </ul>
    </nav>
    <div class="menu-toggle" id="mobile-menu">
      <span></span><span></span><span></span>
    </div>
  </header>

  <!-- Login Container -->
  <div class="login-container">
    <div class="login-box">
      <div class="login-header">
        <div class="login-icon">
          <i class="fas fa-user-shield"></i>
        </div>
        <h2>Welcome Back!</h2>
        <p>Please login to continue</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error animate-slide-in">
          <i class="fas fa-exclamation-circle"></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($_GET['registered'])): ?>
        <div class="alert alert-success animate-slide-in">
          <i class="fas fa-check-circle"></i>
          <span>Registration successful! Please login to continue.</span>
        </div>
      <?php endif; ?>

      <form method="post" class="login-form">
        <!-- Role Selection -->
        <div class="form-group">
          <label><i class="fas fa-user-tag"></i> Select Your Role</label>
          <div class="role-selector">
            <input type="radio" name="role" id="role-user" value="user" required>
            <label for="role-user" class="role-card">
              <div class="role-icon">
                <i class="fas fa-user"></i>
              </div>
              <span>User</span>
              <small>Request Services</small>
            </label>

            <input type="radio" name="role" id="role-admin" value="admin" required>
            <label for="role-admin" class="role-card">
              <div class="role-icon">
                <i class="fas fa-user-shield"></i>
              </div>
              <span>Admin</span>
              <small>Manage System</small>
            </label>

            <input type="radio" name="role" id="role-staff" value="staff" required>
            <label for="role-staff" class="role-card">
              <div class="role-icon">
                <i class="fas fa-user-tie"></i>
              </div>
              <span>Staff</span>
              <small>Handle Tasks</small>
            </label>
          </div>
        </div>

        <!-- Email -->
        <div class="form-group">
          <label for="login_email"><i class="fas fa-envelope"></i> Email Address</label>
          <div class="input-wrapper">
            <i class="fas fa-envelope input-icon"></i>
            <input type="email" name="login_email" id="login_email" placeholder="Enter your email" required autocomplete="email">
          </div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label for="login_password"><i class="fas fa-lock"></i> Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="login_password" id="login_password" placeholder="Enter your password" required autocomplete="current-password">
            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
          </div>
        </div>

        <!-- Remember Me & Forgot Password -->
        <div class="form-options">
          <label class="checkbox-label">
            <input type="checkbox" name="remember">
            <span>Remember me</span>
          </label>
          <a href="#" class="forgot-link">Forgot Password?</a>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn-login">
          <span>Login Now</span>
          <i class="fas fa-arrow-right"></i>
        </button>

        <!-- ...existing code... -->

        <!-- Register Link -->
        <div class="form-footer">
          <p>Don't have an account? <a href="register.php">Create Account</a></p>
        </div>
      </form>
    </div>

    <!-- Info Panel -->
    <div class="info-panel">
      <div class="info-content">
        <div class="info-illustration">
          <i class="fas fa-leaf"></i>
          <i class="fas fa-recycle"></i>
          <i class="fas fa-trash-alt"></i>
        </div>
        <h3>Smart Waste Management</h3>
        <p>Join us in making waste management smarter, cleaner, and more efficient for everyone.</p>
        <div class="features-list">
          <div class="feature-item">
            <div class="feature-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="feature-text">
              <h4>Easy Request Management</h4>
              <p>Submit and track waste collection requests effortlessly</p>
            </div>
          </div>
          <div class="feature-item">
            <div class="feature-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="feature-text">
              <h4>Real-time Tracking</h4>
              <p>Monitor your waste collection status in real-time</p>
            </div>
          </div>
          <div class="feature-item">
            <div class="feature-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="feature-text">
              <h4>Secure & Reliable</h4>
              <p>Your data is protected with advanced security</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="login-footer">
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
    const passwordInput = document.getElementById('login_password');

    if (togglePassword && passwordInput) {
      togglePassword.addEventListener('click', () => {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        togglePassword.classList.toggle('fa-eye');
        togglePassword.classList.toggle('fa-eye-slash');
      });
    }

    // Add animation on scroll
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate-in');
        }
      });
    });

    document.querySelectorAll('.login-box, .info-panel').forEach((el) => {
      observer.observe(el);
    });
  </script>
</body>

</html>