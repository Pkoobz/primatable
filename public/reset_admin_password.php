<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

$database = new Database();
$pdo = $database->getConnection();

// New admin credentials
$username = 'admin';
$new_password = 'Admin@123'; // This will be your new admin password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

try {
    // Update admin password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ? AND role = 'admin'");
    $result = $stmt->execute([$hashed_password, $username]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo "Admin password updated successfully!<br>";
        echo "New login credentials:<br>";
        echo "Username: admin<br>";
        echo "Password: Admin@123";
    } else {
        echo "No admin user found to update.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}