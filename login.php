<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$database = "coupons_db"; 

$conn = new mysqli($host, $user, $password, $database);

// Handle login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Sanitize inputs to prevent SQL injection
    $username = $conn->real_escape_string(trim($username));

    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if ($password === $user['password']) {  // Note: In production, use password hashing
            $_SESSION['username'] = $username;
            $_SESSION['business_id'] = $user['id']; // After successful login
            header("Location: dashboard.php");
            exit();
        } else {
            $login_error = "Invalid password.";
        }
    } else {
        $login_error = "User not found.";
    }
}

// Handle registration
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $username = trim($_POST['new_username']);
    $email = trim($_POST['new_email']);
    $password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate passwords match
    if ($password !== $confirm_password) {
        $register_error = "Passwords do not match.";
    } else {
        // Sanitize inputs
        $username = $conn->real_escape_string($username);
        $email = $conn->real_escape_string($email);
        $password = $conn->real_escape_string($password);

        // Check if username or email already exists
        $check_sql = "SELECT * FROM users WHERE username = '$username' OR email = '$email'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            $register_error = "Username or email already exists.";
        } else {
            // Insert new user into database
            $insert_sql = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$password')";
            
            if ($conn->query($insert_sql) === TRUE) {
                $register_success = "Registration successful! Please login with your credentials.";
                // Clear form values
                unset($_POST['new_username']);
                unset($_POST['new_email']);
                unset($_POST['new_password']);
                unset($_POST['confirm_password']);
            } else {
                $register_error = "Error: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Coupon System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --primary-50: #eef2ff;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --success: #10b981;
            --success-50: #ecfdf5;
            --danger: #ef4444;
            --danger-50: #fef2f2;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .auth-container {
            width: 100%;
            max-width: 900px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
        }
        
        .auth-side {
            flex: 1;
            padding: 40px;
            position: relative;
        }
        
        .auth-side.login {
            background: #f9fafc;
        }
        
        .auth-side.brand {
            background: var(--primary);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 60px 40px;
        }
        
        .brand-logo {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .brand-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        
        .brand-description {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 30px;
        }
        
        .brand-features {
            text-align: left;
            width: 100%;
        }
        
        .brand-feature {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .brand-feature i {
            font-size: 18px;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-header h2 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .auth-header p {
            color: var(--gray-600);
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--gray-600);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
            outline: none;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 40px;
            color: var(--gray-600);
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-800);
            border: 1px solid var(--gray-300);
        }
        
        .btn-secondary:hover {
            background: var(--gray-200);
        }
        
        .message {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .error-message {
            background: var(--danger-50);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .success-message {
            background: var(--success-50);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .message i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .footer-links a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray-600);
            background: none;
            border: none;
            font-size: 16px;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: var(--gray-600);
        }
        
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid var(--gray-300);
        }
        
        .divider::before {
            margin-right: 10px;
        }
        
        .divider::after {
            margin-left: 10px;
        }
        
        .switch-auth {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
        }
        
        .switch-auth a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .switch-auth a:hover {
            text-decoration: underline;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .remember-me input {
            margin-right: 8px;
        }
        
        .password-strength {
            height: 5px;
            margin-top: 8px;
            border-radius: 3px;
            background: var(--gray-200);
            overflow: hidden;
        }
        
        .password-strength-meter {
            height: 100%;
            width: 0;
            transition: width 0.3s, background-color 0.3s;
        }
        
        .password-feedback {
            font-size: 12px;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .auth-container {
                flex-direction: column;
            }
            
            .auth-side {
                padding: 30px;
            }
            
            .auth-side.brand {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_GET['debug'])): ?>
        <div style="background: yellow; padding: 10px;">
            <h3>Debug Info</h3>
            <pre><?php
                echo "Session Status: ".session_status()."\n";
                echo "Session ID: ".session_id()."\n";
                print_r($_SESSION);
            ?></pre>
        </div>
    <?php endif;?>
    <div class="auth-container">
        <div class="auth-side login" id="loginForm">
            <div class="auth-header">
                <h2>Welcome Back</h2>
                <p>Sign in to access your admin dashboard</p>
            </div>
            
            <?php if (isset($login_error)): ?>
                <div class="message error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($login_error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($register_success)): ?>
                <div class="message success-message">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($register_success); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="post" id="loginFormElement">
                <input type="hidden" name="login" value="1">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <i class="fas fa-lock input-icon"></i>
                    <div class="password-container">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="remember-me">
                    <input type="checkbox" name="remember" id="remember">
                    <label for="remember">Remember me</label>
                </div>
                
                <button type="submit" class="btn btn-primary" id="loginButton">
                    <span>Sign In</span>
                    <i class="fas fa-spinner fa-spin" style="display: none;" id="loginSpinner"></i>
                </button>
                
                <div class="footer-links">
                    <a href="#" id="forgotPasswordLink">Forgot password?</a>
                </div>
            </form>
            
            <div class="switch-auth">
                <p>Don't have an account? <a href="#" id="showRegisterBtn">Create Account</a></p>
            </div>
        </div>
        
        <div class="auth-side register" id="registerForm" style="display: none;">
            <div class="auth-header">
                <h2>Create Account</h2>
                <p>Join our admin platform</p>
            </div>
            
            <?php if (isset($register_error)): ?>
                <div class="message error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($register_error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="post" id="registerFormElement">
                <input type="hidden" name="register" value="1">
                
                <div class="form-group">
                    <label for="new_username">Username</label>
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="new_username" name="new_username" class="form-control" placeholder="Choose a username" required value="<?php echo isset($_POST['new_username']) ? htmlspecialchars($_POST['new_username']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="new_email">Email</label>
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="new_email" name="new_email" class="form-control" placeholder="Enter your email" required value="<?php echo isset($_POST['new_email']) ? htmlspecialchars($_POST['new_email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="new_password">Password</label>
                    <i class="fas fa-lock input-icon"></i>
                    <div class="password-container">
                        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Create a password" required>
                        <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-meter" id="passwordStrengthMeter"></div>
                    </div>
                    <div class="password-feedback" id="passwordFeedback"></div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <i class="fas fa-lock input-icon"></i>
                    <div class="password-container">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
                        <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="registerButton">
                    <span>Create Account</span>
                    <i class="fas fa-spinner fa-spin" style="display: none;" id="registerSpinner"></i>
                </button>
            </form>
            
            <div class="switch-auth">
                <p>Already have an account? <a href="#" id="showLoginBtn">Sign In</a></p>
            </div>
        </div>
        
        <div class="auth-side brand">
            <div class="brand-logo">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <h1 class="brand-title">CouponSys</h1>
            <p class="brand-description">The complete coupon management solution for your business</p>
            
            <div class="brand-features">
                <div class="brand-feature">
                    <i class="fas fa-check-circle"></i>
                    <span>Create and manage discount coupons</span>
                </div>
                <div class="brand-feature">
                    <i class="fas fa-check-circle"></i>
                    <span>Track usage and redemption rates</span>
                </div>
                <div class="brand-feature">
                    <i class="fas fa-check-circle"></i>
                    <span>Generate detailed analytics reports</span>
                </div>
                <div class="brand-feature">
                    <i class="fas fa-check-circle"></i>
                    <span>Secure admin access controls</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle between login and register forms
            document.getElementById('showRegisterBtn').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('registerForm').style.display = 'block';
            });
            
            document.getElementById('showLoginBtn').addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('registerForm').style.display = 'none';
                document.getElementById('loginForm').style.display = 'block';
            });
            
            // Toggle password visibility
            document.querySelectorAll('.toggle-password').forEach(button => {
                button.addEventListener('click', function() {
                    const passwordField = this.previousElementSibling;
                    const icon = this.querySelector('i');
                    
                    if (passwordField.type === 'password') {
                        passwordField.type = 'text';
                        icon.classList.replace('fa-eye', 'fa-eye-slash');
                    } else {
                        passwordField.type = 'password';
                        icon.classList.replace('fa-eye-slash', 'fa-eye');
                    }
                });
            });
            
            // Password strength meter
            const passwordInput = document.getElementById('new_password');
            const strengthMeter = document.getElementById('passwordStrengthMeter');
            const feedback = document.getElementById('passwordFeedback');
            
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    let message = '';
                    
                    if (password.length >= 8) strength += 25;
                    if (password.match(/[a-z]+/)) strength += 25;
                    if (password.match(/[A-Z]+/)) strength += 25;
                    if (password.match(/[0-9]+/)) strength += 25;
                    
                    strengthMeter.style.width = strength + '%';
                    
                    if (strength <= 25) {
                        strengthMeter.style.backgroundColor = '#ef4444';
                        message = 'Weak password';
                    } else if (strength <= 50) {
                        strengthMeter.style.backgroundColor = '#f59e0b';
                        message = 'Moderate password';
                    } else if (strength <= 75) {
                        strengthMeter.style.backgroundColor = '#10b981';
                        message = 'Good password';
                    } else {
                        strengthMeter.style.backgroundColor = '#059669';
                        message = 'Strong password';
                    }
                    
                    feedback.textContent = message;
                });
            }
            
            // Form submission animations
            document.getElementById('loginFormElement').addEventListener('submit', function() {
                const button = document.getElementById('loginButton');
                const spinner = document.getElementById('loginSpinner');
                button.querySelector('span').style.display = 'none';
                spinner.style.display = 'inline-block';
            });
            
            document.getElementById('registerFormElement').addEventListener('submit', function(e) {
                // Validate passwords match
                const password = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    
                    const errorMessage = document.createElement('div');
                    errorMessage.className = 'message error-message';
                    errorMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Passwords do not match!</span>';
                    
                    // Remove any existing error messages
                    document.querySelectorAll('.error-message').forEach(el => el.remove());
                    
                    // Insert the error message at the top of the form
                    this.insertBefore(errorMessage, this.firstChild);
                    return;
                }
                
                const button = document.getElementById('registerButton');
                const spinner = document.getElementById('registerSpinner');
                button.querySelector('span').style.display = 'none';
                spinner.style.display = 'inline-block';
            });
            
            // Remember me functionality
            if (localStorage.getItem('rememberUsername')) {
                document.getElementById('username').value = localStorage.getItem('savedUsername') || '';
                document.getElementById('remember').checked = true;
            }
            
            document.getElementById('remember').addEventListener('change', function() {
                if (this.checked) {
                    const username = document.getElementById('username').value;
                    localStorage.setItem('rememberUsername', 'true');
                    localStorage.setItem('savedUsername', username);
                } else {
                    localStorage.removeItem('rememberUsername');
                    localStorage.removeItem('savedUsername');
                }
            });
            
            // Forgot password functionality
            document.getElementById('forgotPasswordLink').addEventListener('click', function(e) {
                e.preventDefault();
                alert('Password reset functionality would be implemented here.');
            });
            
            // Check URL for register parameter
            if (window.location.search.includes('register=1')) {
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('registerForm').style.display = 'block';
            }
        });
    </script>
</body>
</html>