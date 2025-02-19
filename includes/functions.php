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

function verify_session($pdo, $user_id, $token) {
    $stmt = $pdo->prepare("SELECT * FROM user_sessions 
        WHERE user_id = ? 
        AND session_token = ? 
        AND expires_at > NOW()");
    $stmt->execute([$user_id, $token]);
    return $stmt->fetch() !== false;
}

function extend_session($pdo, $user_id, $token) {
    $new_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $stmt = $pdo->prepare("UPDATE user_sessions 
        SET expires_at = ? 
        WHERE user_id = ? 
        AND session_token = ?");
    return $stmt->execute([$new_expiry, $user_id, $token]);
}

function clean_expired_sessions($pdo) {
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW()");
    return $stmt->execute();
}