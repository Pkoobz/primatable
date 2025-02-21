<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert prima_data first
        $stmt = $pdo->prepare("INSERT INTO prima_data 
            (bank_id, biller_id, bank_spec_id, biller_spec_id, status, created_by, updated_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['bank_id'],
            $_POST['biller_id'],
            $_POST['bank_spec_id'],
            $_POST['biller_spec_id'],
            $_POST['status'] ?? 'active',
            $_SESSION['user_id'],
            $_SESSION['user_id']
        ]);
        
        $prima_data_id = $pdo->lastInsertId();
        
        // Insert channels
        if (isset($_POST['channels']) && isset($_POST['channel_dates'])) {
            $stmt = $pdo->prepare("INSERT INTO connection_channels 
                (prima_data_id, channel_id, date_live, created_by, updated_by) 
                VALUES (?, ?, ?, ?, ?)");
            
            foreach ($_POST['channels'] as $key => $channel_id) {
                if (!empty($channel_id) && !empty($_POST['channel_dates'][$key])) {
                    $stmt->execute([
                        $prima_data_id,
                        $channel_id,
                        $_POST['channel_dates'][$key],
                        $_SESSION['user_id'],
                        $_SESSION['user_id']
                    ]);
                }
            }
        }
        
        // Log the activity
        log_activity($pdo, 'create', 'prima_data', $prima_data_id, 
            null, 
            [
                'bank_id' => $_POST['bank_id'],
                'biller_id' => $_POST['biller_id'],
                'status' => $_POST['status'] ?? 'active'
            ],
            'New connection created'
        );
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Connection added successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);