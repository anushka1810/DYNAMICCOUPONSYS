<?php
session_start();
require 'db_connection.php';

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$coupon = [];
$error = '';
$success = '';
$business_id = null;

// Get business owner ID
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $business_id = $user['id'];
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Get coupon ID from URL
$coupon_id = isset($_GET['coupon_id']) ? (int)$_GET['coupon_id'] : 0;

// Fetch coupon data
try {
    $stmt = $pdo->prepare("
        SELECT c.*, cust.name as customer_name
        FROM coupons c
        JOIN customers cust ON c.customer_id = cust.id
        WHERE c.id = :coupon_id AND c.business_id = :business_id
    ");
    $stmt->execute([':coupon_id' => $coupon_id, ':business_id' => $business_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        $error = "Coupon not found or you don't have permission to edit this coupon.";
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_coupon'])) {
    $code = trim($_POST['code']);
    $discount_value = (float)$_POST['discount_value'];
    $discount_type = $_POST['discount_type'];
    $valid_from = $_POST['valid_from'];
    $valid_until = $_POST['valid_until'];
    $max_uses = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : null;
    $minimum_order = !empty($_POST['minimum_order']) ? (float)$_POST['minimum_order'] : 0;
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validate inputs
    if (empty($code)) {
        $error = "Coupon code is required.";
    } elseif ($discount_value <= 0) {
        $error = "Discount value must be greater than 0.";
    } elseif ($discount_type === 'percentage' && $discount_value > 100) {
        $error = "Percentage discount cannot exceed 100%.";
    } elseif ($discount_type === 'fixed' && $minimum_order > 0 && $discount_value > $minimum_order) {
        $error = "Fixed amount discount cannot be greater than minimum order amount.";
    } elseif (strtotime($valid_from) > strtotime($valid_until)) {
        $error = "Valid until date must be after valid from date.";
    } else {
        try {
            // Update coupon in database
            $stmt = $pdo->prepare("
                UPDATE coupons 
                SET code = :code,
                    discount_value = :discount_value,
                    discount_type = :discount_type,
                    valid_from = :valid_from,
                    valid_until = :valid_until,
                    max_uses = :max_uses,
                    minimum_order = :minimum_order,
                    description = :description,
                    is_active = :is_active,
                    updated_at = NOW()
                WHERE id = :coupon_id AND business_id = :business_id
            ");
            
            $stmt->execute([
                ':code' => $code,
                ':discount_value' => $discount_value,
                ':discount_type' => $discount_type,
                ':valid_from' => $valid_from,
                ':valid_until' => $valid_until,
                ':max_uses' => $max_uses,
                ':minimum_order' => $minimum_order,
                ':description' => $description,
                ':is_active' => $is_active,
                ':coupon_id' => $coupon_id,
                ':business_id' => $business_id
            ]);
            
            $success = "Coupon updated successfully!";
            
            // Refresh coupon data
            $stmt = $pdo->prepare("
                SELECT c.*, cust.name as customer_name
                FROM coupons c
                JOIN customers cust ON c.customer_id = cust.id
                WHERE c.id = :coupon_id AND c.business_id = :business_id
            ");
            $stmt->execute([':coupon_id' => $coupon_id, ':business_id' => $business_id]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "This coupon code is already in use.";
            } else {
                $error = "Database error: " . $e->getMessage();
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
    <title>Edit Coupon - <?php echo htmlspecialchars($coupon['code'] ?? 'Coupon'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --success: #10b981;
            --success-light: #d1fae5;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            background: var(--gray-50);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            color: var(--gray-800);
            line-height: 1.6;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .form-card {
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin-bottom: 2rem;
        }
        
        .form-card h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .form-group {
            margin-bottom: 1.75rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.875rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 0.9375rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: white;
            color: var(--gray-800);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
            font-size: 0.9375rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9375rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .btn-back {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }
        
        .btn-back:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
            transform: translateY(-1px);
        }
        
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9375rem;
        }
        
        .error-message {
            background: var(--danger-light);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .success-message {
            background: var(--success-light);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .customer-info {
            background: var(--gray-50);
            padding: 1.25rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .customer-info i {
            color: var(--primary);
            font-size: 1.25rem;
        }
        
        .customer-info strong {
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .input-hint {
            font-size: 0.8125rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            
            .form-card {
                padding: 1.5rem;
            }
            
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Edit Coupon</h1>
            <a href="coupon_details.php?coupon_id=<?php echo $coupon_id; ?>" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Coupon
            </a>
        </div>

        <?php if($error): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if($coupon): ?>
            <div class="form-card">
                <h2>Edit Coupon: <?php echo htmlspecialchars($coupon['code']); ?></h2>
                
                <div class="customer-info">
                    <i class="fas fa-user"></i>
                    <div>
                        <strong>Customer:</strong> <?php echo htmlspecialchars($coupon['customer_name']); ?>
                    </div>
                </div>
                
                <form method="POST">
                    <div class="grid-2">
                        <div class="form-group">
                            <label for="code">Coupon Code</label>
                            <input type="text" id="code" name="code" class="form-control" 
                                   value="<?php echo htmlspecialchars($coupon['code']); ?>" required>
                            <p class="input-hint">Unique code customers will use at checkout</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="discount_type">Discount Type</label>
                            <select id="discount_type" name="discount_type" class="form-control" required>
                                <option value="percentage" <?php echo $coupon['discount_type'] === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                                <option value="fixed" <?php echo $coupon['discount_type'] === 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label for="discount_value">Discount Value</label>
                            <input type="number" id="discount_value" name="discount_value" class="form-control"
                                   step="<?php echo $coupon['discount_type'] === 'percentage' ? '0.01' : '0.01'; ?>" 
                                   min="0.01" 
                                   max="<?php echo $coupon['discount_type'] === 'percentage' ? '100' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($coupon['discount_value']); ?>" required>
                            <p class="input-hint">
                                <?php echo $coupon['discount_type'] === 'percentage' ? 
                                    'Enter percentage (e.g., 10 for 10%)' : 
                                    'Enter fixed amount (e.g., 5.00 for $5 off)'; ?>
                            </p>
                        </div>
                        
                        <div class="form-group">
                            <label for="minimum_order">Minimum Order Amount</label>
                            <input type="number" id="minimum_order" name="minimum_order" class="form-control" 
                                   step="0.01" min="0" 
                                   value="<?php echo htmlspecialchars($coupon['minimum_order'] ?? 0); ?>">
                            <p class="input-hint">Leave 0 for no minimum requirement</p>
                        </div>
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label for="valid_from">Valid From</label>
                            <input type="datetime-local" id="valid_from" name="valid_from" class="form-control"
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($coupon['valid_from'])); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="valid_until">Valid Until</label>
                            <input type="datetime-local" id="valid_until" name="valid_until" class="form-control"
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($coupon['valid_until'])); ?>" required>
                        </div>
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group">
                            <label for="max_uses">Maximum Uses</label>
                            <input type="number" id="max_uses" name="max_uses" class="form-control" min="1" 
                                   value="<?php echo $coupon['max_uses'] ? htmlspecialchars($coupon['max_uses']) : ''; ?>">
                            <p class="input-hint">Leave empty for unlimited uses</p>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo $coupon['is_active'] ? 'checked' : ''; ?>>
                                <label for="is_active">Active Coupon</label>
                            </div>
                            <p class="input-hint">Inactive coupons won't be available for use</p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($coupon['description']); ?></textarea>
                        <p class="input-hint">Optional description for internal reference</p>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_coupon" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Update discount value constraints when discount type changes
        document.getElementById('discount_type').addEventListener('change', function() {
            const discountValue = document.getElementById('discount_value');
            const hint = discountValue.parentElement.querySelector('.input-hint');
            
            if (this.value === 'percentage') {
                discountValue.step = '0.01';
                discountValue.max = '100';
                hint.textContent = 'Enter percentage (e.g., 10 for 10%)';
            } else {
                discountValue.step = '0.01';
                discountValue.removeAttribute('max');
                hint.textContent = 'Enter fixed amount (e.g., 5.00 for $5 off)';
            }
        });
        
        // Set minimum valid_until date based on valid_from date
        document.getElementById('valid_from').addEventListener('change', function() {
            const validUntil = document.getElementById('valid_until');
            validUntil.min = this.value;
            if (validUntil.value < this.value) {
                validUntil.value = this.value;
            }
        });

        // Validate minimum order is not greater than discount value for fixed amount
        document.getElementById('discount_value').addEventListener('change', function() {
            const discountType = document.getElementById('discount_type').value;
            const minimumOrder = parseFloat(document.getElementById('minimum_order').value) || 0;
            
            if (discountType === 'fixed' && minimumOrder > 0 && parseFloat(this.value) > minimumOrder) {
                alert('Fixed amount discount cannot be greater than minimum order amount');
                this.value = minimumOrder.toFixed(2);
            }
        });

        document.getElementById('minimum_order').addEventListener('change', function() {
            const discountType = document.getElementById('discount_type').value;
            const discountValue = parseFloat(document.getElementById('discount_value').value) || 0;
            
            if (discountType === 'fixed' && parseFloat(this.value) > 0 && discountValue > parseFloat(this.value)) {
                alert('Fixed amount discount cannot be greater than minimum order amount');
                document.getElementById('discount_value').value = parseFloat(this.value).toFixed(2);
            }
        });
    </script>
</body>
</html>