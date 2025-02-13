<?php
require_once 'config.php';
require_once 'database.php';

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function redirect($path) {
    header("Location: " . APP_URL . "/" . $path);
    exit();
}

function get_user_role($user_id) {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['role'] : null;
}

function validate_password($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return strlen($password) >= 8 
        && preg_match('/[A-Z]/', $password) 
        && preg_match('/[a-z]/', $password) 
        && preg_match('/[0-9]/', $password);
}

function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function log_activity($pdo, $action_type, $table_name, $record_id, $old_data = null, $new_data = null, $notes = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action_type, table_name, record_id, old_data, new_data, notes) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        return $stmt->execute([
            $_SESSION['user_id'],
            $action_type,
            $table_name,
            $record_id,
            $old_data ? json_encode($old_data) : null,
            $new_data ? json_encode($new_data) : null,
            $notes
        ]);
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}