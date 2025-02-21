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
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert prima_data first
        $stmt = $pdo->prepare("INSERT INTO prima_data (bank_id, biller_id, bank_spec_id, biller_spec_id, status, created_by, updated_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['bank_id'],
            $_POST['biller_id'],
            $_POST['bank_spec_id'],
            $_POST['biller_spec_id'],
            $_POST['status'],
            $_SESSION['user_id'],
            $_SESSION['user_id']
        ]);
        
        $prima_data_id = $pdo->lastInsertId();
        
        // Insert channels
        $channels = $_POST['channels'];
        $channel_dates = $_POST['channel_dates'];
        
        $stmt = $pdo->prepare("INSERT INTO connection_channels 
                              (prima_data_id, channel_id, date_live, created_by, updated_by) 
                              VALUES (?, ?, ?, ?, ?)");
        
        for ($i = 0; $i < count($channels); $i++) {
            if (!empty($channels[$i])) {
                $stmt->execute([
                    $prima_data_id,
                    $channels[$i],
                    $channel_dates[$i],
                    $_SESSION['user_id'],
                    $_SESSION['user_id']
                ]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Connection added successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}