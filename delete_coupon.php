<?php
session_start();
require 'db_connection.php';

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get coupon ID from URL
$coupon_id = isset($_GET['coupon_id']) ? (int)$_GET['coupon_id'] : 0;
$redirect_url = isset($_GET['redirect']) ? $_GET['redirect'] : 'all_coupons.php';

try {
    // Get business owner ID
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $_SESSION['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $business_id = $user['id'];

    // Verify coupon belongs to this business
    $stmt = $pdo->prepare("SELECT id FROM coupons WHERE id = :coupon_id AND business_id = :business_id");
    $stmt->execute([':coupon_id' => $coupon_id, ':business_id' => $business_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($coupon) {
        // Delete the coupon
        $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = :coupon_id");
        $stmt->execute([':coupon_id' => $coupon_id]);
        
        $_SESSION['success'] = "Coupon deleted successfully!";
    } else {
        $_SESSION['error'] = "Coupon not found or you don't have permission to delete it.";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

// Redirect back to the appropriate page
header("Location: $redirect_url");
exit();
?>