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
    $spec_id = trim($_POST['bank_spec'] ?? '');
    
    // Validate inputs
    $errors = [];
    if (empty($bank_name)) {
        $errors[] = 'Bank name is required';
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
        
        $bank_id = sprintf('%09d', mt_rand(1, 999999999));
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert bank with spec_id
        $stmt = $pdo->prepare("INSERT INTO banks (bank_id, name, spec_id, created_by) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([
            $bank_id,               // First parameter is bank_id
            $_POST['bank_name'],    // Second is name
            $_POST['bank_spec'],    // Third is spec_id
            $_SESSION['user_id']    // Fourth is created_by
        ]);
        
        if ($result) {
            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => 'Bank added successfully',
                'bank_id' => $bank_id
            ]);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to add bank']);
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