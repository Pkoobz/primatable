<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$spec_name = trim($_POST['spec_name'] ?? '');

if (empty($spec_name)) {
    echo json_encode([
        'success' => false,
        'message' => 'Spec name is required'
    ]);
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
        
        // Set transaction isolation level
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Check if spec name already exists
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM specs WHERE name = ? FOR UPDATE");
        $check_stmt->execute([$spec_name]);
        if ($check_stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Spec name already exists'
            ]);
            exit;
        }
        
        // Insert new spec
        $stmt = $pdo->prepare("INSERT INTO specs (name, created_by) VALUES (?, ?)");
        $result = $stmt->execute([
            $spec_name,
            $_SESSION['user_id']
        ]);
        
        if ($result) {
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Spec added successfully'
            ]);
        } else {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Failed to add spec'
            ]);
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'success' => false,
            'message' => 'Database error occurred'
        ]);
    } finally {
        exit;
    }
}