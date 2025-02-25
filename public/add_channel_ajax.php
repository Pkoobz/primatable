<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

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
        
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM channels WHERE LOWER(name) = LOWER(?)");
        $check_stmt->execute([strtolower($channel_name)]);
        $count = $check_stmt->fetchColumn();
        
        if ($count > 0) {
            echo json_encode([
                'success' => false, 
                'message' => "Channel name '$channel_name' is already in use"
            ]);
            exit;
        }
        
        // If name is unique, proceed with insert
        $stmt = $pdo->prepare("INSERT INTO channels (name, description, created_by, updated_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$channel_name, $channel_description, $_SESSION['user_id'], $_SESSION['user_id']]);

        // Log the activity
        log_activity($pdo, 'create', 'channels', $pdo->lastInsertId(), 
            null, 
            ['name' => $channel_name, 'description' => $channel_description],
            'New channel created'
        );
        
        echo json_encode([
            'success' => true, 
            'message' => "Channel '$channel_name' added successfully"
        ]);
        
    } catch (Exception $e) {
        error_log("Channel creation error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Error adding channel: ' . $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);