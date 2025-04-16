<?php
session_start();
require 'db_connection.php';

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_id = $_POST['business_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? null);

    try {
        // Insert new customer
        $stmt = $pdo->prepare("INSERT INTO customers (business_id, name, email, phone) VALUES (:business_id, :name, :email, :phone)");
        $stmt->execute([
            ':business_id' => $business_id,
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone
        ]);
        
        // Redirect back with success message
        header("Location: add_customer.php?success=1");
        exit();
    } catch (PDOException $e) {
        // Handle duplicate email error
        if ($e->getCode() == 23000) {
            $error = "A customer with this email already exists.";
        } else {
            $error = "Database error: " . $e->getMessage();
        }
        
        // Redirect back with error
        header("Location: add_customer.php?error=" . urlencode($error));
        exit();
    }
} else {
    // If not a POST request, redirect to dashboard
    header("Location: dashboard.php");
    exit();
}