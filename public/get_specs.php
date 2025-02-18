<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();

try {
    if (isset($_GET['bank_id'])) {
        $stmt = $pdo->prepare("SELECT s.id as spec_id, s.name as spec_name 
                              FROM banks b 
                              JOIN specs s ON b.spec_id = s.id 
                              WHERE b.id = ?");
        $stmt->execute([$_GET['bank_id']]);
    } elseif (isset($_GET['biller_id'])) {
        $stmt = $pdo->prepare("SELECT s.id as spec_id, s.name as spec_name 
                              FROM billers b 
                              JOIN specs s ON b.spec_id = s.id 
                              WHERE b.id = ?");
        $stmt->execute([$_GET['biller_id']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'spec_id' => $result['spec_id'],
            'spec_name' => $result['spec_name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Spec not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}