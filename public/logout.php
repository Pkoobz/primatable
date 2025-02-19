<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
include '../includes/nav.php';

$database = new Database();
$pdo = $database->getConnection();

if (isset($_SESSION['user_id'])) {
    // Remove user session from database
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_token = ?");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['session_token']]);
    
    // Log the logout action
    log_activity($pdo, 'logout', 'users', $_SESSION['user_id'], 
        ['username' => $_SESSION['username']], 
        null, 
        'User logged out successfully'
    );
}
session_destroy();
header("Location: login.php");
exit();
?>