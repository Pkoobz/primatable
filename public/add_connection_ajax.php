<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_id = trim($_POST['bank_id'] ?? '');
    $biller_id = trim($_POST['biller_id'] ?? '');
    $bank_spec_id = trim($_POST['bank_spec_id'] ?? '');
    $biller_spec_id = trim($_POST['biller_spec_id'] ?? '');
    $date_live = trim($_POST['date_live'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    
    if (empty($bank_id) || empty($biller_id) || empty($bank_spec_id) || empty($biller_spec_id) || empty($date_live)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("INSERT INTO prima_data (
            bank_id, 
            biller_id, 
            bank_spec_id,
            biller_spec_id,
            date_live, 
            status, 
            created_by, 
            updated_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        $result = $stmt->execute([
            $bank_id,
            $biller_id,
            $bank_spec_id,
            $biller_spec_id,
            $date_live,
            $status,
            $_SESSION['user_id'],
            $_SESSION['user_id']
        ]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Connection added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add connection']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}