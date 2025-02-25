<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_id = trim($_POST['bank_id'] ?? '');
    $spec_id = trim($_POST['bank_spec'] ?? '');
    
    // Validate inputs
    $errors = [];
    if (empty($bank_name)) {
        $errors[] = 'Bank name is required';
    }
    if (empty($spec_id)) {
        $errors[] = 'Spec selection is required';
    }
    if (empty($bank_id)) {
        $errors[] = 'Bank ID is required';
    } elseif (!preg_match('/^\d{1,10}$/', $bank_id)) {
        $errors[] = 'Bank ID must be 1-10 digits only';
    } elseif (strlen($bank_id) > 10) {
        $errors[] = 'Bank ID cannot exceed 10 digits';
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }

    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Set transaction isolation level
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if bank_id already exists - INSIDE transaction
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM banks WHERE bank_id = ? FOR UPDATE");
        $check_stmt->execute([$bank_id]);
        if ($check_stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => 'Bank ID already exists'
            ]);
            exit;
        }
        
        // Rest of your insertion code...
        $stmt = $pdo->prepare("INSERT INTO banks (bank_id, name, spec_id, created_by) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([
            $bank_id,
            $bank_name,
            $spec_id,
            $_SESSION['user_id']
        ]);
        
        if ($result) {
            // Log the activity
            log_activity($pdo, 'create', 'banks', $bank_id, 
                null, 
                ['name' => $bank_name, 'spec_id' => $spec_id],
                'New bank created'
            );
            
            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => 'Bank added successfully',
                'bank_id' => $bank_id
            ]);
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = 'Database error';
        if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
            $errorMessage = 'Invalid spec selection';
        }
        echo json_encode(['success' => false, 'message' => $errorMessage]);
    }
    exit;
}

// If not POST request
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit;