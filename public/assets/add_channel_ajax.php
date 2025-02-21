<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $channel_name = trim($_POST['channel_name'] ?? '');
    $channel_description = trim($_POST['channel_description'] ?? '');
    
    if (empty($channel_name)) {
        echo json_encode(['success' => false, 'message' => 'Channel name is required']);
        exit;
    }

    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("INSERT INTO channels (name, description, created_by, updated_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$channel_name, $channel_description, $_SESSION['user_id'], $_SESSION['user_id']]);
        
        // Log the activity
        log_activity($pdo, 'create', 'channels', $pdo->lastInsertId(), 
            null, 
            ['name' => $channel_name, 'description' => $channel_description],
            'New channel created'
        );
        
        echo json_encode(['success' => true, 'message' => 'Channel added successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding channel: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);