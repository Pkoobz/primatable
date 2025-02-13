<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? '';

if ($id) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();

        // Get the data before deletion for logging
        $stmt = $pdo->prepare("SELECT * FROM prima_data WHERE id = ?");
        $stmt->execute([$id]);
        $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Perform the deletion
        $stmt = $pdo->prepare("DELETE FROM prima_data WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            // Log the deletion
            $log_notes = "Connection deleted by " . $_SESSION['username'];
            log_activity($pdo, 'delete', 'prima_data', $id, $old_data, null, $log_notes);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete connection']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
}