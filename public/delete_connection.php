<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // First delete child records from connection_channels
    $stmt = $pdo->prepare("DELETE FROM connection_channels WHERE prima_data_id = ?");
    $stmt->execute([$id]);
    
    // Then delete the parent record from prima_data
    $stmt = $pdo->prepare("DELETE FROM prima_data WHERE id = ?");
    $stmt->execute([$id]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Connection deleted successfully']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting connection: ' . $e->getMessage()]);
}