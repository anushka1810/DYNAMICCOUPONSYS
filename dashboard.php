<?php
session_start();
require 'db_connection.php';

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Delete customer handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    $customer_id = $_POST['customer_id'];
    try {
        // Verify customer belongs to current business before deletion
        $stmt = $pdo->prepare("
            DELETE customers 
            FROM customers
            JOIN users ON customers.business_id = users.id
            WHERE customers.id = :customer_id 
            AND users.username = :username
        ");
        $stmt->execute([
            ':customer_id' => $customer_id,
            ':username' => $_SESSION['username']
        ]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Customer deleted successfully";
        } else {
            $_SESSION['error'] = "Customer not found or access denied";
        }
        header("Location: dashboard.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting customer: " . $e->getMessage();
        header("Location: dashboard.php");
        exit();
    }
}

// Get business owner details and their customers
$businessData = [];
$customers = [];
$activeCouponsCount = 0;
$redeemedCouponsCount = 0;

try {
    // Get business owner info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([':username' => $_SESSION['username']]);
    $businessData = $stmt->fetch(PDO::FETCH_ASSOC);
    $business_id = $businessData['id'];

    // Get customers associated with this business owner
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM customers c
        WHERE c.business_id = :business_id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([':business_id' => $business_id]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count active coupons
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM coupons 
        WHERE business_id = :business_id 
        AND is_active = TRUE 
        AND valid_until >= NOW()
    ");
    $stmt->execute([':business_id' => $business_id]);
    $activeCouponsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Count coupons redeemed this month
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM coupon_redemptions cr
        JOIN coupons c ON cr.coupon_id = c.id
        WHERE c.business_id = :business_id
        AND YEAR(cr.redeemed_at) = YEAR(CURRENT_DATE)
        AND MONTH(cr.redeemed_at) = MONTH(CURRENT_DATE)
    ");
    $stmt->execute([':business_id' => $business_id]);
    $redeemedCouponsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Preload coupon counts for each customer
    $customerCouponCounts = [];
    if (!empty($customers)) {
        $customerIds = array_column($customers, 'id');
        $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
        
        $stmt = $pdo->prepare("
            SELECT customer_id, COUNT(*) as count 
            FROM coupons 
            WHERE customer_id IN ($placeholders)
            GROUP BY customer_id
        ");
        $stmt->execute($customerIds);
        $customerCouponCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
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
    <title>Business Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-400: #94a3b8;
            --gray-600: #4b5563;
            --gray-800: #1f2937;
            --success: #10b981;
            --danger: #ef4444;
            --danger-dark: #dc2626;
        }

        body {
            background: #c9aeee;
            background: radial-gradient(circle, rgba(201, 174, 238, 1) 0%, rgb(5, 2, 114) 100%);
            /* background: #f8fafc; */
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
            line-height: 1.5;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .welcome-message h1 {
            margin: 0;
            color: white;
            font-size: 2rem;
            font-weight: 600;
        }

        .welcome-message p {
            margin: 0.5rem 0 0;
            color: white;
            font-size: 0.875rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: 1px solid var(--primary-dark);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            border: 1px solid var(--danger-dark);
        }

        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-1px);
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
            transition: transform 0.15s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card h3 {
            margin: 0 0 0.5rem;
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
            color: var(--gray-800);
        }

        .stat-card p {
            margin: 0;
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .customers-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
            padding: 1.5rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            margin: 0;
            color: var(--gray-800);
            font-size: 1.25rem;
            font-weight: 600;
        }

        .customers-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .customers-table th,
        .customers-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }

        .customers-table th {
            color: var(--gray-600);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            background: var(--gray-100);
        }

        .customer-row:hover {
            background: var(--gray-50);
        }

        .customer-name {
            font-weight: 500;
            color: var(--gray-800);
        }

        .customer-email {
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .actions-container {
            display: flex;
            gap: 0.75rem;
        }

        .view-coupons {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: all 0.15s ease;
        }

        .view-coupons:hover {
            background: var(--primary-light);
            text-decoration: none;
        }

        .delete-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .delete-btn:hover {
            background: var(--danger-dark);
        }

        .message {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .success-message {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--gray-200);
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }

            .stats-cards {
                grid-template-columns: 1fr;
            }

            .customers-table {
                display: block;
                overflow-x: auto;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            

        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <div class="welcome-message">
                <h1>Welcome, <?php echo htmlspecialchars($businessData['username'] ?? 'Business Owner'); ?></h1>
                <p>Manage your customers and coupons</p>
            </div>
            <a href="logout.php" class="btn btn-primary">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="stats-cards">
            <div class="stat-card">
                <h3>Total Customers</h3>
                <div class="value"><?php echo count($customers); ?></div>
                <p>All time customers</p>
            </div>
            <div class="stat-card">
                <h3>Active Coupons</h3>
                <div class="value"><?php echo $activeCouponsCount; ?></div>
                <p>Currently active</p>
            </div>
            <div class="stat-card">
                <h3>Coupons Redeemed</h3>
                <div class="value"><?php echo $redeemedCouponsCount; ?></div>
                <p>This month</p>
            </div>
        </div>

        <div class="customers-section">
            <div class="section-header">
                <h2>Your Customers</h2>
                <a href="add_customer.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Customer
                </a>
            </div>

            <?php if(!empty($customers)): ?>
                <table class="customers-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th>Coupons</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($customers as $customer): ?>
                            <tr class="customer-row">
                                <td>
                                    <div class="customer-name"><?php echo htmlspecialchars($customer['name']); ?></div>
                                    <div class="customer-email">ID: <?php echo htmlspecialchars($customer['id']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></td>
                                <td><?php echo $customerCouponCounts[$customer['id']] ?? 0; ?></td>
                                <td>
                                    <div class="actions-container">
                                        <a href="customer_coupons.php?customer_id=<?php echo $customer['id']; ?>" class="view-coupons">
                                            <i class="fas fa-ticket-alt"></i> View
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this customer?');">
                                            <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                            <button type="submit" name="delete_customer" class="delete-btn">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Customers Found</h3>
                    <p>You haven't added any customers yet. Get started by adding your first customer.</p>
                    <a href="add_customer.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Customer
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add confirmation for delete actions
        document.querySelectorAll('form[onsubmit]').forEach(form => {
            form.onsubmit = () => confirm('Are you sure you want to delete this customer?');
        });
    </script>
</body>
</html>