<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_name = trim($_POST['bank_name'] ?? '');
    
    if (empty($bank_name)) {
        echo json_encode(['success' => false, 'message' => 'Bank name is required']);
        exit;
    }

    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("INSERT INTO prima_data (bank, created_by, updated_by) VALUES (?, ?, ?)");
        $result = $stmt->execute([$bank_name, $_SESSION['user_id'], $_SESSION['user_id']]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Bank added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add bank']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}