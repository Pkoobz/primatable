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
    $biller_name = trim($_POST['biller_name'] ?? '');
    $biller_id = trim($_POST['biller_id'] ?? ''); // Add manual biller_id input
    $spec_id = trim($_POST['biller_spec'] ?? '');
    
    // Validate inputs
    $errors = [];
    if (empty($biller_name)) {
        $errors[] = 'Biller name is required';
    }
    if (empty($spec_id)) {
        $errors[] = 'Spec selection is required';
    }
    if (empty($biller_id)) {
        $errors[] = 'Biller ID is required';
    } elseif (!preg_match('/^\d{1,10}$/', $biller_id)) {
        $errors[] = 'Biller ID must be 1-10 digits only';
    } elseif (strlen($biller_id) > 10) {
        $errors[] = 'Biller ID cannot exceed 10 digits';
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }

    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Check if biller_id already exists
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM billers WHERE biller_id = ?");
        $check_stmt->execute([$biller_id]);
        if ($check_stmt->fetchColumn() > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Biller ID already exists'
            ]);
            exit;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert biller with manually entered biller_id
        $stmt = $pdo->prepare("INSERT INTO billers (biller_id, name, spec_id, created_by) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([
            $biller_id,
            $biller_name,
            $spec_id,
            $_SESSION['user_id']  
        ]);
        
        if ($result) {
            // Log the activity
            log_activity($pdo, 'create', 'billers', $biller_id, 
                null, 
                ['name' => $biller_name, 'spec_id' => $spec_id],
                'New biller created'
            );
            
            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => 'Biller added successfully',
                'biller_id' => $biller_id
            ]);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to add biller']);
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