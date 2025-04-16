<?php
session_start();
require 'db_connection.php';

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get customer ID from URL if provided
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$customer = [];
$business_id = null;

try {
    // Get business owner ID
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $business_id = $user['id'];

    // If customer_id provided, verify it belongs to this business
    if ($customer_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :customer_id AND business_id = :business_id");
        $stmt->execute([':customer_id' => $customer_id, ':business_id' => $business_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            header("Location: dashboard.php");
            exit();
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code']));
    $discount_value = (float)$_POST['discount_value'];
    $discount_type = $_POST['discount_type'];
    $valid_from = $_POST['valid_from'];
    $valid_until = $_POST['valid_until'];
    $max_uses = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : null;
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $customer_id = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $minimum_order = !empty($_POST['minimum_order']) ? (float)$_POST['minimum_order'] : null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO coupons (
                business_id, 
                customer_id,
                code, 
                discount_value, 
                discount_type, 
                description,
                valid_from,
                valid_until,
                max_uses,
                is_active,
                minimum_order
            ) VALUES (
                :business_id,
                :customer_id,
                :code,
                :discount_value,
                :discount_type,
                :description,
                :valid_from,
                :valid_until,
                :max_uses,
                :is_active,
                :minimum_order
            )
        ");      
        $stmt->execute([
            ':business_id' => $business_id,
            ':customer_id' => $customer_id,
            ':code' => $code,
            ':discount_value' => $discount_value,
            ':discount_type' => $discount_type,
            ':description' => $description,
            ':valid_from' => $valid_from,
            ':valid_until' => $valid_until,
            ':max_uses' => $max_uses,
            ':is_active' => $is_active,
            ':minimum_order' => $minimum_order
        ]);

        // Redirect to customer's coupons page if specific customer
        if ($customer_id) {
            header("Location: customer_coupons.php?customer_id=$customer_id&success=1");
        } else {
            header("Location: all_coupons.php?success=1");
        }
        exit();

    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Coupon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --success: #10b981;
            --danger: #ef4444;
        }
        
        body {
            background: #708090;
            background: radial-gradient(circle, rgba(112, 128, 144, 1) 0%, rgba(99, 102, 241, 1) 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .form-container {
            max-width: 600px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        h1 {
            color: var(--gray-800);
            margin-top: 0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--gray-600);
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="number"],
        input[type="datetime-local"],
        select,
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
        }
        
        .btn-back {
            background: var(--gray-200);
            color: var(--gray-800);
            margin-right: 1rem;
        }
        
        .btn-back:hover {
            background: var(--gray-300);
        }
        
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
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
        
        .customer-select {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .customer-info {
            font-size: 0.9rem;
            color: var(--gray-600);
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="header">
            <a href="<?php echo $customer_id ? 'customer_coupons.php?customer_id='.$customer_id : 'dashboard.php'; ?>" 
               class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
        
        <h1>Add New Coupon</h1>
        
        <?php if(isset($_GET['success'])): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i> Coupon added successfully!
            </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form action="add_coupon.php<?php echo $customer_id ? '?customer_id='.$customer_id : ''; ?>" method="POST">
            <?php if($customer_id): ?>
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                <div class="form-group">
                    <label>Customer</label>
                    <div class="customer-info">
                        <?php echo htmlspecialchars($customer['name']); ?> (<?php echo htmlspecialchars($customer['email']); ?>)
                    </div>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label for="customer_id">Customer (Optional)</label>
                    <select id="customer_id" name="customer_id" class="form-control">
                        <option value="">-- Select Customer --</option>
                        <?php
                        $stmt = $pdo->prepare("SELECT id, name, email FROM customers WHERE business_id = :business_id ORDER BY name");
                        $stmt->execute([':business_id' => $business_id]);
                        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="code">Coupon Code</label>
                <input type="text" id="code" name="code" required 
                       placeholder="e.g. SUMMER20" 
                       value="<?php echo isset($_POST['code']) ? htmlspecialchars($_POST['code']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="discount_value">Discount Value</label>
                <input type="number" id="discount_value" name="discount_value" 
                       min="0.01" step="0.01" required
                       value="<?php echo isset($_POST['discount_value']) ? htmlspecialchars($_POST['discount_value']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="discount_type">Discount Type</label>
                <select id="discount_type" name="discount_type" required>
                    <option value="percentage" <?php echo (isset($_POST['discount_type']) && $_POST['discount_type'] === 'percentage') ? 'selected' : ''; ?>>Percentage (%)</option>
                    <option value="fixed" <?php echo (isset($_POST['discount_type']) && $_POST['discount_type'] === 'fixed') ? 'selected' : ''; ?>>Fixed Amount</option>
                </select>
            </div>

            <div class="form-group">
                <label for="minimum_order">Minimum Order Amount (required)</label>
                <input type="number" id="minimum_order" name="minimum_order" 
                    min="0" step="0.01"
                    placeholder="DON'T LEAVE BLANK"
                    value="<?php echo isset($_POST['minimum_order']) ? htmlspecialchars($_POST['minimum_order']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="valid_from">Valid From</label>
                <input type="datetime-local" id="valid_from" name="valid_from" required
                    value="<?php echo isset($_POST['valid_from']) ? htmlspecialchars($_POST['valid_from']) : date('Y-m-d\TH:i'); ?>">
            </div>
            
            <div class="form-group">
                <label for="valid_until">Valid Until</label>
                <input type="datetime-local" id="valid_until" name="valid_until" required
                       value="<?php echo isset($_POST['valid_until']) ? htmlspecialchars($_POST['valid_until']) : date('Y-m-d\TH:i', strtotime('+1 month')); ?>">
            </div>
            
            <div class="form-group">
                <label for="max_uses">Maximum Uses (Leave blank for unlimited)</label>
                <input type="number" id="max_uses" name="max_uses" min="1"
                       value="<?php echo isset($_POST['max_uses']) ? htmlspecialchars($_POST['max_uses']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="description">Description (Optional)</label>
                <textarea id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                <label for="is_active">Active (Can be used immediately)</label>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Coupon
                </button>
            </div>
        </form>
    </div>
    
    <script>
        // Generate a random coupon code if field is empty
        document.getElementById('code').addEventListener('focus', function() {
            if (this.value === '') {
                const randomCode = 'CODE' + Math.floor(1000 + Math.random() * 9000);
                this.value = randomCode;
            }
        });
    </script>
</body>
</html>