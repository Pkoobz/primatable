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
    $spec_id = trim($_POST['biller_spec'] ?? '');
    
    // Validate inputs
    $errors = [];
    if (empty($biller_name)) {
        $errors[] = 'Biller name is required';
    }
    if (empty($spec_id)) {
        $errors[] = 'Spec selection is required';
    }

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }

    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $biller_id = sprintf('%09d', mt_rand(1, 999999999));
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert biller with spec_id
        $stmt = $pdo->prepare("INSERT INTO billers (biller_id, name, spec_id, created_by) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([
            $biller_id,               // Use generated biller_id instead of lastInsertId
            $_POST['biller_name'],    // Second is name
            $_POST['biller_spec'],    // Third is spec_id
            $_SESSION['user_id']  
        ]);
        
        if ($result) {
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