<?php
session_start();
require 'db_connection.php';

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get business owner ID
$business_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $business_id = $user['id'];
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO customers (business_id, name, email, phone) VALUES (:business_id, :name, :email, :phone)");
        $stmt->execute([
            ':business_id' => $business_id,
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone
        ]);
        
        header("Location: add_customer.php?success=1");
        exit();
    } catch (PDOException $e) {
        $error = "Failed to add customer: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Customer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --gray-100: #f8fafc;
            --gray-200: #f1f5f9;
            --gray-300: #e2e8f0;
            --gray-400: #cbd5e1;
            --gray-500: #94a3b8;
            --gray-600: #64748b;
            --gray-700: #475569;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
        }
        
        body {
            background: var(--gray-100);
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            color: var(--gray-800);
        }
        
        .form-container {
            max-width: 600px;
            margin: 2rem auto;
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: var(--primary);
        }
        
        .header {
            margin-bottom: 2rem;
        }
        
        h1 {
            color: var(--gray-800);
            margin: 0 0 1.5rem;
            font-weight: 600;
            font-size: 1.75rem;
        }
        
        .form-group {
            margin-bottom: 1.75rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.75rem;
            color: var(--gray-700);
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"] {
            width: 100%;
            padding: 0.875rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: var(--gray-100);
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
            background: white;
        }
        
        .btn {
            padding: 0.875rem 1.75rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);
        }
        
        .btn-back {
            background: var(--gray-200);
            color: var(--gray-700);
            margin-right: 1rem;
        }
        
        .btn-back:hover {
            background: var(--gray-300);
            transform: translateY(-2px);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .message {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .error-message {
            background: #fee2e2;
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .success-message {
            background: #ecfdf5;
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .illustration {
            max-width: 200px;
            margin: 1.5rem auto;
            display: block;
            opacity: 0.8;
        }
        
        @media (max-width: 640px) {
            .form-container {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-back {
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="header">
            <a href="dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <img src="https://cdn-icons-png.flaticon.com/512/4400/4400628.png" alt="Add customer illustration" class="illustration">
        
        <h1>Add New Customer</h1>
        
        <?php if(isset($_GET['success'])): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i> Customer added successfully!
            </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form action="add_customer.php" method="POST">
            <input type="hidden" name="business_id" value="<?php echo $business_id; ?>">
            
            <div class="form-group">
                <label for="name">Customer Name</label>
                <input type="text" id="name" name="name" required placeholder="Enter customer's full name">
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="Enter customer's email">
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number (Optional)</label>
                <input type="tel" id="phone" name="phone" placeholder="Enter customer's phone number">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Customer
                </button>
            </div>
        </form>
    </div>
</body>
</html>