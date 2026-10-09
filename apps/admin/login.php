<?php require_once('../config.php') ?>
<?php require_once('inc/header.php') ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - <?php echo $_settings->info('name') ?></title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      overflow-x: hidden;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Animated Background */
    .login-bg {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
      background-size: 400% 400%;
      animation: gradientShift 8s ease infinite;
      z-index: -2;
      overflow: hidden;
    }

    @keyframes gradientShift {
      0% {
        background-position: 0% 50%;
      }

      50% {
        background-position: 100% 50%;
      }

      100% {
        background-position: 0% 50%;
      }
    }

    /* Wave Animation Background */
    .wave {
      position: absolute;
      bottom: 0;
      left: 0;
      width: 200%;
      height: 150px;
      background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 100"><path d="M0 50 Q300 0 600 50 T1200 50 L1200 100 L0 100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
      background-repeat: repeat-x;
      animation: wave 10s linear infinite;
      z-index: -1;
    }

    @keyframes wave {
      0% {
        transform: translateX(0);
      }

      100% {
        transform: translateX(-50%);
      }
    }

    /* Main Container */
    .login-container {
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
      position: relative;
      z-index: 1;
    }

    /* Login Card */
    .login-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 3rem;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      width: 100%;
      max-width: 450px;
      animation: slideInUp 0.8s ease-out;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(40px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Header */
    .login-header {
      text-align: center;
      margin-bottom: 2.5rem;
      animation: fadeInDown 0.8s ease-out 0.2s both;
    }

    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .login-header h1 {
      color: #333;
      font-weight: 700;
      margin-bottom: 0.5rem;
      font-size: 1.8rem;
    }

    .login-header .version {
      color: #999;
      font-size: 0.9rem;
      font-weight: 500;
    }

    /* Form Group */
    .form-group {
      margin-bottom: 1.5rem;
      animation: fadeIn 0.8s ease-out both;
    }

    .form-group:nth-child(1) {
      animation-delay: 0.3s;
    }

    .form-group:nth-child(2) {
      animation-delay: 0.4s;
    }

    .form-group:nth-child(3) {
      animation-delay: 0.5s;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    /* Label */
    .form-label {
      color: #333;
      font-weight: 600;
      margin-bottom: 0.7rem;
      display: flex;
      align-items: center;
      font-size: 0.95rem;
    }

    .form-label i {
      color: #667eea;
      margin-right: 0.5rem;
      font-size: 1.1rem;
    }

    /* Input Field */
    .form-control {
      border: 2px solid #e0e0e0;
      border-radius: 12px;
      padding: 0.85rem 1rem;
      font-size: 1rem;
      transition: all 0.3s ease;
      background-color: #f8f9fa;
    }

    .form-control:focus {
      border-color: #667eea;
      background-color: #fff;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
      transform: translateY(-2px);
    }

    .form-control::placeholder {
      color: #aaa;
    }

    /* Password Toggle */
    .password-toggle {
      position: absolute;
      right: 1rem;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #667eea;
      font-size: 1.1rem;
      transition: all 0.3s ease;
      z-index: 10;
    }

    .password-toggle:hover {
      transform: translateY(-50%) scale(1.1);
      color: #764ba2;
    }

    /* Password Input Wrapper */
    .password-wrapper {
      position: relative;
    }

    /* Checkbox */
    .form-check {
      display: flex;
      align-items: center;
      margin-top: 1rem;
    }

    .form-check-input {
      width: 18px;
      height: 18px;
      margin-right: 0.5rem;
      cursor: pointer;
      border-color: #e0e0e0;
      transition: all 0.3s ease;
    }

    .form-check-input:checked {
      background-color: #667eea;
      border-color: #667eea;
    }

    .form-check-input:hover {
      border-color: #667eea;
    }

    .form-check-label {
      cursor: pointer;
      color: #666;
      font-size: 0.9rem;
    }

    /* Login Button */
    .btn-login {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      border-radius: 12px;
      padding: 0.95rem 1.5rem;
      font-weight: 600;
      color: white;
      width: 100%;
      margin-top: 1.5rem;
      transition: all 0.3s ease;
      font-size: 1rem;
      position: relative;
      overflow: hidden;
      animation: slideInUp 0.8s ease-out 0.6s both;
    }

    .btn-login:before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #764ba2 0%, #f093fb 100%);
      transition: left 0.5s ease;
      z-index: -1;
    }

    .btn-login:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
      color: white;
      text-decoration: none;
    }

    .btn-login:hover:before {
      left: 0;
    }

    .btn-login:disabled {
      opacity: 0.8;
      cursor: not-allowed;
    }

    /* Button Content */
    .btn-content {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .spinner-border-sm {
      width: 1rem;
      height: 1rem;
      border-width: 0.2em;
    }

    /* Footer */
    .login-footer {
      text-align: center;
      margin-top: 2rem;
      color: #999;
      font-size: 0.85rem;
      animation: fadeIn 0.8s ease-out 0.7s both;
    }

    /* Loading ripple animation */
    @keyframes ripple {
      0% {
        box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.7);
      }

      70% {
        box-shadow: 0 0 0 10px rgba(102, 126, 234, 0);
      }

      100% {
        box-shadow: 0 0 0 0 rgba(102, 126, 234, 0);
      }
    }

    .btn-login.loading {
      animation: ripple 1s infinite;
    }

    /* Mobile Responsive */
    @media (max-width: 576px) {
      .login-card {
        padding: 2rem;
        border-radius: 16px;
      }

      .login-header h1 {
        font-size: 1.5rem;
      }

      .form-control {
        padding: 0.75rem 0.9rem;
        font-size: 16px;
      }

      .btn-login {
        padding: 0.85rem 1.2rem;
        font-size: 0.95rem;
      }

      .wave {
        height: 100px;
      }
    }

    @media (max-width: 380px) {
      .login-card {
        padding: 1.5rem;
      }

      .login-header h1 {
        font-size: 1.3rem;
      }

      .form-label {
        font-size: 0.85rem;
      }

      .form-control {
        padding: 0.65rem 0.8rem;
        font-size: 16px;
      }
    }

    /* Tablet Responsive */
    @media (min-width: 768px) {
      .login-card {
        padding: 3.5rem;
      }
    }

    /* Dark Mode Support */
    @media (prefers-color-scheme: dark) {
      .login-card {
        background: rgba(30, 30, 30, 0.95);
        border-color: rgba(255, 255, 255, 0.1);
      }

      .login-header h1 {
        color: #f0f0f0;
      }

      .form-label {
        color: #e0e0e0;
      }

      .form-control {
        background-color: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.1);
        color: #f0f0f0;
      }

      .form-control:focus {
        background-color: rgba(255, 255, 255, 0.1);
        border-color: #667eea;
      }

      .form-check-label {
        color: #aaa;
      }
    }

    /* Floating label effect */
    .form-control:not(:placeholder-shown),
    .form-control:focus {
      font-size: 1rem;
    }
  </style>
</head>

<body id="kt_body">
  <!-- Animated Background -->
  <div class="login-bg">
    <div class="wave"></div>
  </div>

  <!-- Login Container -->
  <div class="login-container">
    <!-- Login Card -->
    <div class="login-card">

      <!-- Header -->
      <div class="login-header">
        <h1><i class="fas fa-lock-open" style="margin-right: 0.5rem;"></i><?php echo $_settings->info('name') ?></h1>
        <span class="version">Masuk ke Sistem Management</span>
      </div>

      <!-- Login Form -->
      <form id="login-frm" novalidate>

        <!-- Username Input -->
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-user"></i>Username
          </label>
          <input
            type="text"
            name="username"
            class="form-control"
            placeholder="Masukkan username Anda"
            autocomplete="off"
            required>
        </div>

        <!-- Password Input -->
        <div class="form-group">
          <label class="form-label">
            <i class="fas fa-lock"></i>Password
          </label>
          <div class="password-wrapper">
            <input
              type="password"
              id="passwordInput"
              name="password"
              class="form-control"
              placeholder="Masukkan password Anda"
              autocomplete="off"
              required>
            <span class="password-toggle" id="togglePassword">
              <i class="fas fa-eye"></i>
            </span>
          </div>
        </div>

        <!-- Remember Me -->
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe">
          <label class="form-check-label" for="rememberMe">
            Ingat saya di perangkat ini
          </label>
        </div>

        <!-- Login Button -->
        <button type="submit" class="btn btn-login">
          <span class="btn-content">
            <span class="btn-label">Login Sekarang</span>
            <i class="fas fa-arrow-right" style="font-size: 0.9rem;"></i>
          </span>
        </button>

      </form>

      <!-- Footer -->
      <div class="login-footer">
        <p><?php echo $_settings->info('name') ?> &copy; <?php echo date('Y') ?> | All Rights Reserved</p>
      </div>

    </div>
  </div>

  <!-- Scripts -->
  <script>
    // Password Toggle
    document.getElementById("togglePassword").addEventListener("click", function() {
      const passwordInput = document.getElementById("passwordInput");
      const icon = this.querySelector("i");

      if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      } else {
        passwordInput.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    });

    // Form Submission with AJAX
    document.getElementById("login-frm").addEventListener("submit", async function(e) {
      e.preventDefault();
      e.stopImmediatePropagation();

      const button = document.querySelector(".btn-login");
      const btnLabel = button.querySelector(".btn-label");
      const btnContent = button.querySelector(".btn-content");

      // Add loading state
      button.disabled = true;
      button.classList.add("loading");
      btnLabel.textContent = "Loading...";

      // Get form data
      let formData = new FormData(this);

      try {
        const response = await fetch("../classes/Login.php?f=login", {
          method: "POST",
          body: formData
        });

        const data = await response.json();

        // Reset button state
        button.disabled = false;
        button.classList.remove("loading");
        btnLabel.textContent = "Login Sekarang";

        if (data.status === "success") {
          // Success animation
          Swal.fire({
            icon: "success",
            title: "Login Berhasil!",
            text: data.msg,
            timer: 1500,
            showConfirmButton: false,
            background: "rgba(255, 255, 255, 0.95)",
            iconColor: "#667eea",
            timerProgressBar: true
          }).then(() => {
            // Redirect berdasarkan role: 1 Admin, 2 Kasir, 3 Salesman
            let redirectUrl = "./";
            if (data.level_id == 2) {
              // Kasir - masuk dashboard, menu dibatasi ke kasir
              redirectUrl = "./";
            } else if (data.level_id == 3) {
              // Sales - masuk fitur penjualan yang sudah ada
              redirectUrl = "./?page=penjualan";
            }
            // Admin (level_id 1) dan others - ke dashboard
            window.location.href = redirectUrl;
          });

        } else {
          // Error animation
          Swal.fire({
            icon: "error",
            title: "Login Gagal",
            text: data.msg || "Username atau password salah",
            confirmButtonColor: "#667eea",
            background: "rgba(255, 255, 255, 0.95)",
            confirmButtonText: "Coba Lagi"
          });

          // Shake animation on error
          document.querySelector(".login-card").style.animation = "none";
          setTimeout(() => {
            document.querySelector(".login-card").style.animation = "shake 0.5s";
          }, 10);
        }

      } catch (error) {
        console.error("Error:", error);

        // Reset button state
        button.disabled = false;
        button.classList.remove("loading");
        btnLabel.textContent = "Login Sekarang";

        Swal.fire({
          icon: "error",
          title: "Terjadi Error!",
          text: "Koneksi server gagal. Silakan coba lagi.",
          confirmButtonColor: "#667eea",
          background: "rgba(255, 255, 255, 0.95)",
          confirmButtonText: "OK"
        });
      }
    });

    // Shake animation for errors
    const style = document.createElement('style');
    style.textContent = `
      @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
        20%, 40%, 60%, 80% { transform: translateX(10px); }
      }
    `;
    document.head.appendChild(style);

    // Smooth scroll to top on page load
    window.addEventListener('load', () => {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
  </script>
</body>

</html>
