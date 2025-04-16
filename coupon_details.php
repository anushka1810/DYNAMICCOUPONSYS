<?php
session_start();
require 'db_connection.php';

// Check for success/error messages from delete operation
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get coupon ID from URL
$coupon_id = isset($_GET['coupon_id']) ? (int)$_GET['coupon_id'] : 0;
$coupon = [];
$customer = [];
$redemptions = [];
$business_id = null;
$error = '';

try {
    // Get business owner ID
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $business_id = $user['id'];

    // Get coupon details
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(cr.id) as times_redeemed,
               CASE 
                 WHEN c.valid_until < NOW() THEN 'Expired'
                 WHEN c.is_active = FALSE THEN 'Inactive'
                 ELSE 'Active'
               END as status
        FROM coupons c
        LEFT JOIN coupon_redemptions cr ON c.id = cr.coupon_id
        WHERE c.id = :coupon_id AND c.business_id = :business_id
    ");
    $stmt->execute([':coupon_id' => $coupon_id, ':business_id' => $business_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$coupon) {
        $error = "Coupon not found or you don't have permission to view this coupon.";
    } else {
        // Get redemption history for this coupon with amount details
        try {
            $stmt = $pdo->prepare("
                SELECT redeemed_at, ip_address, 
                       original_amount, discounted_amount
                FROM coupon_redemptions
                WHERE coupon_id = :coupon_id
                ORDER BY redeemed_at DESC
            ");
            $stmt->execute([':coupon_id' => $coupon_id]);
            $redemptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
        // Fix: Properly determine coupon status with timezone awareness
        $currentDateTime = new DateTime('now', new DateTimeZone('UTC'));
        $validUntil = new DateTime($coupon['valid_until'], new DateTimeZone('UTC'));
        
        $coupon['status'] = 'Active';
        if ($validUntil < $currentDateTime) {
            $coupon['status'] = 'Expired';
        } elseif (!$coupon['is_active']) {
            $coupon['status'] = 'Inactive';
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
    <title>Coupon Details - <?php echo htmlspecialchars($coupon['code'] ?? ''); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --success: #28a745;
            --success-light: #d4edda;
            --warning: #ffc107;
            --warning-light: #fff3cd;
            --danger: #dc3545;
            --danger-light: #f8d7da;
            --info: #17a2b8;
            --info-light: #d1ecf1;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --rounded: 0.375rem;
            --rounded-lg: 0.5rem;
            --rounded-xl: 0.75rem;
            --rounded-full: 9999px;
        }
        
        body {
            background: var(--gray-100);
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            color: var(--gray-800);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .header-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .coupon-card {
            background: white;
            padding: 2rem;
            border-radius: var(--rounded-xl);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .coupon-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0.5rem;
            height: 100%;
            background: var(--primary);
        }
        
        .coupon-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .coupon-code {
            font-family: 'Courier New', monospace;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-dark);
            background: rgba(79, 70, 229, 0.1);
            padding: 0.5rem 1rem;
            border-radius: var(--rounded);
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .coupon-code i {
            font-size: 1.25rem;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            border-radius: var(--rounded-full);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-active {
            background: var(--success-light);
            color: var(--success);
        }
        
        .status-inactive {
            background: var(--warning-light);
            color: var(--warning);
        }
        
        .status-expired {
            background: var(--danger-light);
            color: var(--danger);
        }
        
        .coupon-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-group {
            margin-bottom: 0.5rem;
        }
        
        .detail-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .detail-value {
            font-weight: 500;
            color: var(--gray-800);
            font-size: 1rem;
        }
        
        .discount-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--rounded);
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }
        
        .btn-back {
            background: var(--gray-200);
            color: var(--gray-800);
        }
        
        .btn-back:hover {
            background: var(--gray-300);
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 2rem 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        .redemptions-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--rounded-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .redemptions-table th, 
        .redemptions-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .redemptions-table th {
            color: var(--gray-600);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            background: var(--gray-100);
        }
        
        .redemptions-table tr:last-child td {
            border-bottom: none;
        }
        
        .redemptions-table tr:hover td {
            background: rgba(79, 70, 229, 0.03);
        }
        
        .message {
            padding: 1rem;
            border-radius: var(--rounded);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
        
        .info-message {
            background: var(--info-light);
            color: var(--info);
            border-left: 4px solid var(--info);
        }
        
        .customer-info {
            background: white;
            padding: 1.5rem;
            border-radius: var(--rounded-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .customer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .customer-details {
            flex: 1;
        }
        
        .customer-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 1.125rem;
        }
        
        .customer-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .customer-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .amount-cell {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: 500;
        }
        
        .amount-change {
            color: var(--success);
            font-weight: 600;
        }
        
        .coupon-image {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: var(--rounded);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background: white;
            border-radius: var(--rounded-lg);
            box-shadow: var(--shadow-sm);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: var(--gray-600);
            max-width: 400px;
            margin: 0 auto;
        }
        
        .stats-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--rounded-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .coupon-details {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-title">
                <i class="fas fa-ticket-alt"></i>
                <span>Coupon Details</span>
            </div>
            <div class="header-actions">
                <a href="<?php echo $coupon['customer_id'] ? 'customer_coupons.php?customer_id='.$coupon['customer_id'] : 'all_coupons.php'; ?>" 
                   class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <a href="edit_coupon.php?coupon_id=<?php echo $coupon_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="delete_coupon.php?coupon_id=<?php echo $coupon_id; ?>&redirect=<?php echo $coupon['customer_id'] ? 'customer_coupons.php?customer_id='.$coupon['customer_id'] : 'all_coupons.php'; ?>" 
                    class="btn btn-danger" 
                    onclick="return confirm('Are you sure you want to delete this coupon?');">
                        <i class="fas fa-trash"></i> Delete
                </a>
            </div>
        </div>

        <?php if($error): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if(isset($success)): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if($coupon): ?>
            <!-- Coupon Image Placeholder - You would replace this with actual image logic -->
            <img src="https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?ixlib=rb-1.2.1&auto=format&fit=crop&w=1200&q=80" 
                 alt="Coupon illustration" 
                 class="coupon-image">
            
            <div class="coupon-card">
                <div class="coupon-header">
                    <div class="coupon-code">
                        <i class="fas fa-tag"></i>
                        <?php echo htmlspecialchars($coupon['code']); ?>
                    </div>
                    <span class="status-badge status-<?php echo strtolower($coupon['status']); ?>">
                        <i class="fas fa-<?php echo $coupon['status'] === 'Active' ? 'check-circle' : ($coupon['status'] === 'Expired' ? 'exclamation-circle' : 'pause-circle'); ?>"></i>
                        <?php echo $coupon['status']; ?>
                    </span>
                </div>
                
                <div class="coupon-details">
                    <div class="detail-group">
                        <div class="detail-label">
                            <i class="fas fa-percentage"></i> Discount Value
                        </div>
                        <div class="detail-value discount-value">
                            <?php 
                            echo $coupon['discount_type'] === 'percentage' 
                                ? htmlspecialchars($coupon['discount_value']) . '%'
                                : '$' . htmlspecialchars($coupon['discount_value']);
                            ?>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">
                            <i class="far fa-calendar-check"></i> Valid From
                        </div>
                        <div class="detail-value">
                            <?php echo date('M j, Y g:i A', strtotime($coupon['valid_from'])); ?>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">
                            <i class="far fa-calendar-times"></i> Valid Until
                        </div>
                        <div class="detail-value">
                            <?php echo date('M j, Y g:i A', strtotime($coupon['valid_until'])); ?>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">
                            <i class="fas fa-redo"></i> Redemptions
                        </div>
                        <div class="detail-value">
                            <?php echo $coupon['times_redeemed']; ?>
                            <?php if($coupon['max_uses']): ?>
                                / <?php echo $coupon['max_uses']; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if($coupon['description']): ?>
                    <div class="detail-group">
                        <div class="detail-label">
                            <i class="far fa-file-alt"></i> Description
                        </div>
                        <div class="detail-value"><?php echo nl2br(htmlspecialchars($coupon['description'])); ?></div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Stats Section -->
            <div class="stats-card">
                <h3 class="section-title">
                    <i class="fas fa-chart-line"></i> Performance Overview
                </h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $coupon['times_redeemed']; ?></div>
                        <div class="stat-label">Total Redemptions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php 
                            if ($coupon['max_uses'] > 0) {
                                echo round(($coupon['times_redeemed'] / $coupon['max_uses']) * 100) . '%';
                            } else {
                                echo '∞';
                            }
                            ?>
                        </div>
                        <div class="stat-label">Usage Rate</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php 
                            $daysLeft = floor((strtotime($coupon['valid_until']) - time()) / (60 * 60 * 24));
                            echo $daysLeft > 0 ? $daysLeft . 'd' : 'Expired';
                            ?>
                        </div>
                        <div class="stat-label">Days Remaining</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php echo $coupon['status']; ?>
                        </div>
                        <div class="stat-label">Current Status</div>
                    </div>
                </div>
            </div>
            
            <?php if($coupon['customer_id'] && $customer): ?>
                <div class="customer-info">
                    <div class="customer-avatar">
                        <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                    </div>
                    <div class="customer-details">
                        <div class="customer-name">
                            <?php echo htmlspecialchars($customer['name']); ?>
                        </div>
                        <div class="customer-meta">
                            <div class="customer-meta-item">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars($customer['email']); ?>
                            </div>
                            <?php if($customer['phone']): ?>
                                <div class="customer-meta-item">
                                    <i class="fas fa-phone"></i>
                                    <?php echo htmlspecialchars($customer['phone']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <h3 class="section-title">
                <i class="fas fa-history"></i> Redemption History
            </h3>
            
            <?php if(!empty($redemptions)): ?>
                <table class="redemptions-table">
                    <thead>
                        <tr>
                            <th>Redeemed At</th>
                            <th>Original Amount</th>
                            <th>Discounted Amount</th>
                            <th>Savings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($redemptions as $redemption): ?>
                            <?php 
                            $original = (float)$redemption['original_amount'];
                            $discounted = (float)$redemption['discounted_amount'];
                            $savings = $original - $discounted;
                            $savings_percent = ($original > 0) ? round(($savings / $original) * 100, 2) : 0;
                            ?>
                            <tr>
                                <td>
                                    <i class="far fa-clock"></i> 
                                    <?php echo date('M j, Y g:i A', strtotime($redemption['redeemed_at'])); ?>
                                </td>
                                <td class="amount-cell">$<?php echo number_format($original, 2); ?></td>
                                <td class="amount-cell">$<?php echo number_format($discounted, 2); ?></td>
                                <td class="amount-cell amount-change">
                                    <i class="fas fa-arrow-down"></i> 
                                    $<?php echo number_format($savings, 2); ?> 
                                    (<?php echo $savings_percent; ?>%)
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>No Redemption History</h3>
                    <p>This coupon hasn't been redeemed yet. Check back after customers start using it.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>