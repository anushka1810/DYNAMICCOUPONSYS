<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}
require 'db/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = $_POST['code'];
    $type = $_POST['discount_type'];
    $value = $_POST['discount_value'];
    $min_order = $_POST['min_order_value'];
    $usage_limit = $_POST['usage_limit'];
    $expiry = $_POST['expiry_date'];
    $status = $_POST['status'];

    // Prepare insert statement
    $stmt = $pdo->prepare("INSERT INTO coupons (code, discount_type, discount_value, min_order_value, usage_limit, expiry_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $stmt->execute([$code, $type, $value, $min_order, $usage_limit, $expiry, $status]);

    // Redirect back to dashboard
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Coupon | Admin Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f9fafb;
            color: var(--gray-800);
            line-height: 1.6;
        }
        
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--primary);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--gray-600);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
            outline: none;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 6px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            font-size: 16px;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: var(--gray-100);
            color: var(--gray-800);
            border: 1px solid var(--gray-300);
        }
        
        .btn-secondary:hover {
            background-color: var(--gray-200);
        }
        
        .actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group-append {
            position: absolute;
            right: 0;
            top: 0;
            height: 100%;
            display: flex;
            align-items: center;
            padding: 0 15px;
            background-color: var(--gray-100);
            border-top-right-radius: 6px;
            border-bottom-right-radius: 6px;
            border: 1px solid var(--gray-300);
            border-left: none;
            color: var(--gray-600);
        }
        
        .input-group .form-control {
            padding-right: 60px;
        }
        
        .help-text {
            font-size: 14px;
            color: var(--gray-600);
            margin-top: 5px;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb .separator {
            color: var(--gray-600);
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span class="separator">/</span>
            <span>Create Coupon</span>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-ticket-alt"></i> Create New Coupon</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="couponForm">
                    <div class="form-group">
                        <label for="code">Coupon Code</label>
                        <div class="input-group">
                            <input type="text" id="code" name="code" class="form-control" placeholder="Enter coupon code" required>
                            <button type="button" class="input-group-append" id="generateCode">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="help-text">Enter a unique code or click the icon to generate one automatically</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="discount_type">Discount Type</label>
                            <select id="discount_type" name="discount_type" class="form-control">
                                <option value="percentage">Percentage Discount (%)</option>
                                <option value="fixed">Fixed Amount Discount ($)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="discount_value">Discount Value</label>
                            <div class="input-group">
                                <input type="number" id="discount_value" name="discount_value" class="form-control" placeholder="Enter value" min="0" step="0.01" required>
                                <div class="input-group-append" id="valueSymbol">%</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="min_order_value">Minimum Order Value ($)</label>
                            <input type="number" id="min_order_value" name="min_order_value" class="form-control" placeholder="Minimum purchase amount" min="0" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="usage_limit">Usage Limit</label>
                            <input type="number" id="usage_limit" name="usage_limit" class="form-control" placeholder="Maximum number of uses" min="1" required>
                            <div class="help-text">How many times this coupon can be used</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="expiry_date">Expiry Date</label>
                            <input type="date" id="expiry_date" name="expiry_date" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="actions">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Coupon
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date for expiry to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('expiry_date').min = today;
            document.getElementById('expiry_date').value = today;
            
            // Generate random coupon code
            document.getElementById('generateCode').addEventListener('click', function() {
                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                let code = '';
                for (let i = 0; i < 8; i++) {
                    code += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                document.getElementById('code').value = code;
            });
            
            // Change symbol based on discount type
            document.getElementById('discount_type').addEventListener('change', function() {
                const symbol = this.value === 'percentage' ? '%' : '$';
                document.getElementById('valueSymbol').textContent = symbol;
            });
            
            // Form validation
            document.getElementById('couponForm').addEventListener('submit', function(e) {
                const discountType = document.getElementById('discount_type').value;
                const discountValue = parseFloat(document.getElementById('discount_value').value);
                
                if (discountType === 'percentage' && discountValue > 100) {
                    e.preventDefault();
                    alert('Percentage discount cannot be greater than 100%');
                    return false;
                }
                
                return true;
            });
        });
    </script>
</body>
</html>