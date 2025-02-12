<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $spec_name = trim($_POST['spec_name'] ?? '');
    
    if (empty($spec_name)) {
        echo json_encode(['success' => false, 'message' => 'Spec name is required']);
        exit;
    }

    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("INSERT INTO prima_data (spec, created_by, updated_by) VALUES (?, ?, ?)");
        $result = $stmt->execute([$spec_name, $_SESSION['user_id'], $_SESSION['user_id']]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Spec added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add spec']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}