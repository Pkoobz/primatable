<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$prima_data_id = isset($_GET['prima_data_id']) ? (int)$_GET['prima_data_id'] : 0;

if (!$prima_data_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $stmt = $pdo->prepare("
        SELECT ch.name, cc.date_live 
        FROM connection_channels cc 
        JOIN channels ch ON cc.channel_id = ch.id 
        WHERE cc.prima_data_id = ? 
        ORDER BY cc.date_live
    ");
    
    $stmt->execute([$prima_data_id]);
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'channels' => $channels
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching channel details'
    ]);
}