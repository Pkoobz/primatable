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
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $type = $_POST['type'] ?? '';
        $id = $_POST['id'] ?? '';
        
        if (empty($type) || empty($id)) {
            throw new Exception('Missing required parameters');
        }
        
        $pdo->beginTransaction();
        
        // Check for dependencies before deletion
        switch ($type) {
            case 'bank':
                $check = $pdo->prepare("SELECT COUNT(*) FROM prima_data WHERE bank_id = ?");
                $table = 'banks';
                $check->execute([$id]);
                $dependencies = $check->fetchColumn();
                break;
                
            case 'biller':
                $check = $pdo->prepare("SELECT COUNT(*) FROM prima_data WHERE biller_id = ?");
                $table = 'billers';
                $check->execute([$id]);
                $dependencies = $check->fetchColumn();
                break;
                
            case 'channel':
                $check = $pdo->prepare("SELECT COUNT(*) FROM connection_channels WHERE channel_id = ?");
                $table = 'channels';
                $check->execute([$id]);
                $dependencies = $check->fetchColumn();
                break;
                
            default:
                throw new Exception('Invalid item type');
        }
        
        if ($dependencies > 0) {
            throw new Exception('Cannot delete item: It is being used in active connections');
        }
        
        // Perform deletion
        $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => ucfirst($type) . ' deleted successfully'
            ]);
        } else {
            throw new Exception('Failed to delete item');
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
?>
<script>
function toggleDeleteModal(type) {
    closeModals();
    const modalId = `delete${type}Modal`;
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function deleteItem(type, id) {
    if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
        fetch('delete_item.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `type=${type}&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while deleting', 'error');
        });
    }
}
</script>