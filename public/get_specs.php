<?php
require_once '../includes/config.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

$database = new Database();
$pdo = $database->getConnection();

try {
    if (isset($_GET['bank_id'])) {
        $stmt = $pdo->prepare("SELECT specs.* FROM specs 
                              JOIN banks ON banks.spec_id = specs.id 
                              WHERE banks.id = ?");
        $stmt->execute([$_GET['bank_id']]);
        $spec = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'spec_id' => $spec['id'],
            'spec_name' => $spec['name']
        ]);
    }
    elseif (isset($_GET['biller_id'])) {
        $stmt = $pdo->prepare("SELECT specs.* FROM specs 
                              JOIN billers ON billers.spec_id = specs.id 
                              WHERE billers.id = ?");
        $stmt->execute([$_GET['biller_id']]);
        $spec = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'spec_id' => $spec['id'],
            'spec_name' => $spec['name']
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}