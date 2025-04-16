<?php
session_start();
require 'db_connection.php';

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get customer ID from URL
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

// Initialize variables
$customer = [];
$coupons = [];
$business_id = null;
$error = '';
$success = '';
$stats = [
    'total' => 0,
    'active' => 0,
    'redeemed' => 0,
    'expired' => 0
];

// Handle coupon redemption
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redeem_coupon'])) {
    $coupon_id = (int)$_POST['coupon_id'];
    $redemption_notes = trim($_POST['redemption_notes'] ?? '');
    $original_amount = (float)$_POST['original_amount'];
    
    try {
        // Get business owner ID from session (set during login)
        if (!isset($_SESSION['business_id'])) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute([':username' => $_SESSION['username']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['business_id'] = $user['id'];
        }
        $business_id = $_SESSION['business_id'];

        // Verify coupon belongs to this business and is redeemable
        $stmt = $pdo->prepare("
            SELECT c.* 
            FROM coupons c
            WHERE c.id = :coupon_id 
            AND c.business_id = :business_id
            AND c.is_active = TRUE
            AND c.valid_from <= NOW()
            AND c.valid_until >= NOW()
            AND (c.max_uses IS NULL OR c.times_used < c.max_uses)
            FOR UPDATE
        ");
        $stmt->execute([
            ':coupon_id' => $coupon_id,
            ':business_id' => $business_id
        ]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);


        if ($coupon) {
            // Validate minimum order amount
            if ($coupon['minimum_order'] > 0 && $original_amount < $coupon['minimum_order']) {
                $error = "Original amount must be greater than minimum order ($$coupon[minimum_order])";
            } else {
                // Calculate discounted amount
                $discounted_amount = 0;
                if ($coupon['discount_type'] === 'percentage') {
                    $discounted_amount = $original_amount * (1 - ($coupon['discount_value'] / 100));
                } else {
                    $discounted_amount = max(0, $original_amount - $coupon['discount_value']);
                }
                
                // Start transaction
                $pdo->beginTransaction();
                
                // Record redemption with discounted amount
                $stmt = $pdo->prepare("
                    INSERT INTO coupon_redemptions 
                    (coupon_id, customer_id, redeemed_at, ip_address, notes, original_amount, discounted_amount) 
                    VALUES (:coupon_id, :customer_id, NOW(), :ip_address, :notes, :original_amount, :discounted_amount)
                ");
                $stmt->execute([
                    ':coupon_id' => $coupon_id,
                    ':customer_id' => $customer_id,
                    ':ip_address' => $_SERVER['REMOTE_ADDR'],
                    ':notes' => $redemption_notes,
                    ':original_amount' => $original_amount,
                    ':discounted_amount' => $discounted_amount
                ]);

                // Update usage count
                $stmt = $pdo->prepare("
                    UPDATE coupons 
                    SET times_used = times_used + 1,
                        updated_at = NOW()
                    WHERE id = :coupon_id
                ");
                $stmt->execute([':coupon_id' => $coupon_id]);
                
                // Commit transaction
                $pdo->commit();
                
                $success = "Coupon '{$coupon['code']}' redeemed successfully!";
            }
        } else {
            $error = "Coupon cannot be redeemed (may be expired, inactive, or reached its usage limit)";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Database error: " . $e->getMessage();
    }
}

try {
    // Get business owner ID
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $business_id = $user['id'];
    $_SESSION['business_id'] = $business_id;

    // Verify customer belongs to this business owner
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :customer_id AND business_id = :business_id");
    $stmt->execute([':customer_id' => $customer_id, ':business_id' => $business_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        $error = "Customer not found or you don't have permission to view this customer.";
    } else {
        // Get all coupons for this customer with enhanced stats
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   COUNT(cr.id) as times_redeemed,
                   CASE 
                     WHEN c.valid_until < NOW() THEN 'Expired'
                     WHEN c.is_active = FALSE THEN 'Inactive'
                     WHEN c.valid_from > NOW() THEN 'Pending'
                     WHEN c.max_uses IS NOT NULL AND c.times_used >= c.max_uses THEN 'Limit Reached'
                     ELSE 'Redeemable'
                   END as status
            FROM coupons c
            LEFT JOIN coupon_redemptions cr ON c.id = cr.coupon_id
            WHERE c.customer_id = :customer_id
            GROUP BY c.id
            ORDER BY 
                CASE WHEN status = 'Redeemable' THEN 0 
                     WHEN status = 'Pending' THEN 1
                     WHEN status = 'Inactive' THEN 2
                     WHEN status = 'Limit Reached' THEN 3
                     ELSE 4 END,
                c.created_at DESC
        ");
        $stmt->execute([':customer_id' => $customer_id]);
        $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate stats
        $stats['total'] = count($coupons);
        foreach ($coupons as $coupon) {
            if ($coupon['status'] === 'Redeemable') $stats['active']++;
            if ($coupon['times_redeemed'] > 0) $stats['redeemed']++;
            if ($coupon['status'] === 'Expired') $stats['expired']++;
        }
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Coupons - <?php echo htmlspecialchars($customer['name'] ?? 'Customer'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
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
            --success-light: #34d399;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --purple: #8b5cf6;
        }
        
        body {
            background: #f8fafc;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .customer-info {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            background-image: url('https://images.unsplash.com/photo-1556740738-b6a63e27c4df?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=500&q=80');
            background-size: cover;
            background-position: center;
            background-blend-mode: overlay;
            background-color: rgba(255,255,255,0.9);
            position: relative;
            overflow: hidden;
        }
        
        .customer-info::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(99, 102, 241, 0.1) 100%);
            z-index: 0;
        }
        
        .customer-name {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0 0 0.75rem 0;
            position: relative;
            z-index: 1;
        }
        
        .customer-meta {
            display: flex;
            gap: 1.5rem;
            color: var(--gray-600);
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .customer-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.8);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid var(--primary);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card:nth-child(2) {
            border-left-color: var(--success);
        }
        
        .stat-card:nth-child(3) {
            border-left-color: var(--info);
        }
        
        .stat-card:nth-child(4) {
            border-left-color: var(--warning);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .coupons-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .coupons-table th, 
        .coupons-table td {
            padding: 1.25rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .coupons-table th {
            color: var(--gray-600);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            background: var(--gray-100);
        }
        
        .coupon-row:hover {
            background: rgba(79, 70, 229, 0.03);
        }
        
        .coupon-code {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 0.3rem;
        }
        
        .status-redeemable {
            background: #d1fae5;
            color: var(--success);
        }
        
        .status-pending {
            background: #bfdbfe;
            color: var(--info);
        }
        
        .status-inactive {
            background: #fef3c7;
            color: var(--warning);
        }
        
        .status-expired {
            background: #fee2e2;
            color: var(--danger);
        }
        
        .status-limit-reached {
            background: #ede9fe;
            color: var(--purple);
        }
        
        .discount-value {
            font-weight: 700;
            color: var(--success);
        }
        
        .actions-cell {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            border: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.2);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: var(--success-light);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.2);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.2);
        }
        
        .btn-info {
            background: var(--info);
            color: white;
        }
        
        .btn-info:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.2);
        }
        
        .btn-back {
            background: var(--gray-200);
            color: var(--gray-800);
        }
        
        .btn-back:hover {
            background: var(--gray-300);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        
        .message {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.95rem;
        }
        
        .error-message {
            background: #fee2e2;
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .success-message {
            background: #d1fae5;
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            color: var(--gray-600);
            margin: 2rem 0;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 1.5rem;
            opacity: 0.7;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            margin-bottom: 1.5rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal.show {
            opacity: 1;
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }
        
        .modal.show .modal-content {
            transform: translateY(0);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .modal-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-600);
            transition: color 0.2s ease;
        }
        
        .modal-close:hover {
            color: var(--danger);
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-800);
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .text-red-500 {
            color: var(--danger);
        }
        
        .text-sm {
            font-size: 0.85rem;
        }
        
        .mt-1 {
            margin-top: 0.25rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.85rem;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        
        .form-control[readonly] {
            background-color: var(--gray-100);
            cursor: not-allowed;
            color: var(--gray-600);
        }
        
        #discounted_amount {
            font-weight: 700;
            color: var(--success);
            font-size: 1.25rem;
            background: var(--gray-100);
            padding: 0.85rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .discount-preview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1rem;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: 8px;
            font-weight: 600;
        }
        
        .discount-preview .original {
            text-decoration: line-through;
            color: var(--gray-600);
        }
        
        .discount-preview .arrow {
            color: var(--gray-400);
            font-size: 1.2rem;
        }
        
        .discount-preview .final {
            color: var(--success);
            font-size: 1.1rem;
        }
        
        @media (max-width: 1024px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .coupons-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
        
        @media (max-width: 640px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .customer-meta {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <div>
                <a href="add_coupon.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Coupon
                </a>
            </div>
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

        <?php if($customer): ?>
            <div class="customer-info">
                <h1 class="customer-name"><?php echo htmlspecialchars($customer['name']); ?></h1>
                <div class="customer-meta">
                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($customer['email']); ?></span>
                    <?php if($customer['phone']): ?>
                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($customer['phone']); ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-calendar-alt"></i> Joined: <?php echo date('M j, Y', strtotime($customer['created_at'])); ?></span>
                    <span><i class="fas fa-id-card"></i> ID: <?php echo $customer_id; ?></span>
                </div>
            </div>
            
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Coupons</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['active']; ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['redeemed']; ?></div>
                    <div class="stat-label">Redeemed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['expired']; ?></div>
                    <div class="stat-label">Expired</div>
                </div>
            </div>
            
            <?php if(!empty($coupons)): ?>
                <table class="coupons-table">
                    <thead>
                        <tr>
                            <th>Coupon Code</th>
                            <th>Discount</th>
                            <th>Min. Order</th>
                            <th>Valid From</th>
                            <th>Valid Until</th>
                            <th>Status</th>
                            <th>Redemptions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($coupons as $coupon): ?>
                            <tr class="coupon-row">
                                <td class="coupon-code"><?php echo htmlspecialchars($coupon['code']); ?></td>
                                <td class="discount-value">
                                    <?php 
                                    echo $coupon['discount_type'] === 'percentage' 
                                        ? htmlspecialchars($coupon['discount_value']) . '%'
                                        : '$' . htmlspecialchars($coupon['discount_value']);
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    echo $coupon['minimum_order'] > 0 
                                        ? '$' . htmlspecialchars($coupon['minimum_order']) 
                                        : 'None';
                                    ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($coupon['valid_from'])); ?></td>
                                <td><?php echo date('M j, Y', strtotime($coupon['valid_until'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $coupon['status'])); ?>">
                                        <i class="fas 
                                            <?php 
                                            switch($coupon['status']) {
                                                case 'Redeemable': echo 'fa-check-circle'; break;
                                                case 'Pending': echo 'fa-clock'; break;
                                                case 'Inactive': echo 'fa-ban'; break;
                                                case 'Expired': echo 'fa-calendar-times'; break;
                                                case 'Limit Reached': echo 'fa-tag'; break;
                                            }
                                            ?>
                                        "></i>
                                        <?php echo $coupon['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $coupon['times_redeemed']; ?>
                                    <?php if($coupon['max_uses']): ?>
                                        / <?php echo $coupon['max_uses']; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="coupon_details.php?coupon_id=<?php echo $coupon['id']; ?>" class="btn btn-primary" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if($coupon['status'] === 'Redeemable'): ?>
                                        <button class="btn btn-success redeem-btn" 
                                                data-coupon-id="<?php echo $coupon['id']; ?>"
                                                data-coupon-code="<?php echo htmlspecialchars($coupon['code']); ?>"
                                                data-discount-type="<?php echo $coupon['discount_type']; ?>"
                                                data-discount-value="<?php echo $coupon['discount_value']; ?>"
                                                data-minimum-order="<?php echo $coupon['minimum_order']; ?>"
                                                title="Redeem Coupon">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-success" disabled title="Not redeemable">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="edit_coupon.php?coupon_id=<?php echo $coupon['id']; ?>" class="btn btn-info" title="Edit Coupon">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-ticket-alt"></i>
                    <h3>No Coupons Found</h3>
                    <p>This customer doesn't have any coupons yet. Create their first coupon to get started!</p>
                    <a href="add_coupon.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Coupon
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Redemption Modal -->
    <div class="modal" id="redemptionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Redeem Coupon</h2>
                <button class="modal-close">&times;</button>
            </div>
            <form method="POST" id="redemptionForm">
                <input type="hidden" name="coupon_id" id="modalCouponId">
                <input type="hidden" name="discount_type" id="modalDiscountType">
                <input type="hidden" name="discount_value" id="modalDiscountValue">
                <input type="hidden" name="minimum_order" id="modalMinimumOrder">
                
                <div class="form-group">
                    <label>Coupon Code</label>
                    <input type="text" id="modalCouponCode" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label for="original_amount">Original Amount ($)</label>
                    <input type="number" id="original_amount" name="original_amount" class="form-control" 
                           step="0.01" min="0.01" required oninput="calculateDiscount()">
                    <div id="amountError" class="text-red-500 text-sm mt-1"></div>
                </div>
                
                <div class="form-group">
                    <label>Discount Type</label>
                    <input type="text" id="displayDiscountType" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Discount Value</label>
                    <input type="text" id="displayDiscountValue" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Minimum Order</label>
                    <input type="text" id="displayMinimumOrder" class="form-control" readonly>
                </div>
                
                <div class="discount-preview">
                    <span class="original" id="originalAmountPreview">$0.00</span>
                    <span class="arrow"><i class="fas fa-arrow-right"></i></span>
                    <span class="final" id="discountedAmountPreview">$0.00</span>
                </div>
                
                <div class="form-group">
                    <label>You Save</label>
                    <input type="text" id="discounted_amount" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label for="redemption_notes">Redemption Notes (Optional)</label>
                    <textarea name="redemption_notes" id="redemption_notes" class="form-control" placeholder="Add any notes about this redemption..."></textarea>
                </div>
                
                <button type="submit" name="redeem_coupon" class="btn btn-success" style="width: 100%; padding: 1rem;">
                    <i class="fas fa-check-circle"></i> Confirm Redemption
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Redemption modal handling
            const modal = document.getElementById('redemptionModal');
            
            document.querySelectorAll('.redeem-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    // Set coupon data
                    document.getElementById('modalCouponId').value = this.dataset.couponId;
                    document.getElementById('modalCouponCode').value = this.dataset.couponCode;
                    document.getElementById('modalDiscountType').value = this.dataset.discountType;
                    document.getElementById('modalDiscountValue').value = this.dataset.discountValue;
                    document.getElementById('modalMinimumOrder').value = this.dataset.minimumOrder || 0;
                    
                    // Display discount info
                    document.getElementById('displayDiscountType').value = 
                        this.dataset.discountType === 'percentage' ? 'Percentage' : 'Fixed Amount';
                    document.getElementById('displayDiscountValue').value = 
                        this.dataset.discountType === 'percentage' 
                            ? this.dataset.discountValue + '%' 
                            : '$' + this.dataset.discountValue;
                    document.getElementById('displayMinimumOrder').value = 
                        this.dataset.minimumOrder > 0 
                            ? '$' + this.dataset.minimumOrder 
                            : 'None';
                    
                    // Reset amount fields
                    document.getElementById('original_amount').value = '';
                    document.getElementById('discounted_amount').value = '';
                    document.getElementById('amountError').textContent = '';
                    document.getElementById('originalAmountPreview').textContent = '$0.00';
                    document.getElementById('discountedAmountPreview').textContent = '$0.00';
                    
                    // Focus on amount input
                    document.getElementById('original_amount').focus();
                    
                    modal.classList.add('show');
                });
            });
            
            // Close modal handlers
            document.querySelector('.modal-close').addEventListener('click', function() {
                modal.classList.remove('show');
            });
            
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });

        function calculateDiscount() {
            const originalAmount = parseFloat(document.getElementById('original_amount').value);
            const discountType = document.getElementById('modalDiscountType').value;
            const discountValue = parseFloat(document.getElementById('modalDiscountValue').value);
            const minimumOrder = parseFloat(document.getElementById('modalMinimumOrder').value) || 0;
            
            // Reset error message
            document.getElementById('amountError').textContent = '';
            
            if (isNaN(originalAmount)) {
                document.getElementById('discounted_amount').value = '';
                document.getElementById('originalAmountPreview').textContent = '$0.00';
                document.getElementById('discountedAmountPreview').textContent = '$0.00';
                return;
            }
            
            // Update original amount preview
            document.getElementById('originalAmountPreview').textContent = '$' + originalAmount.toFixed(2);
            
            // Validate minimum order amount
            if (minimumOrder > 0 && originalAmount < minimumOrder) {
                document.getElementById('amountError').textContent = `Amount should be greater than minimum order ($${minimumOrder})`;
                document.getElementById('discounted_amount').value = '';
                document.getElementById('discountedAmountPreview').textContent = '$0.00';
                return;
            }
            
            let discountedAmount = originalAmount;
            let savings = 0;
            
            if (discountType === 'percentage') {
                // Calculate percentage discount
                savings = originalAmount * (discountValue / 100);
                discountedAmount = originalAmount - savings;
            } else {
                // Calculate fixed amount discount
                savings = discountValue;
                discountedAmount = originalAmount - discountValue;
                // Ensure amount doesn't go below 0
                if (discountedAmount < 0) {
                    savings = originalAmount;
                    discountedAmount = 0;
                }
            }
            
            // Display the savings amount with 2 decimal places
            document.getElementById('discounted_amount').value = '$' + savings.toFixed(2);
            document.getElementById('discountedAmountPreview').textContent = '$' + discountedAmount.toFixed(2);
        }
    </script>
</body>
</html>