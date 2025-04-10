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

            case 'spec':
                // Check if spec is used in banks
                $check_banks = $pdo->prepare("SELECT COUNT(*) FROM banks WHERE spec_id = ?");
                $check_banks->execute([$id]);
                $bank_dependencies = $check_banks->fetchColumn();
                
                // Check if spec is used in billers
                $check_billers = $pdo->prepare("SELECT COUNT(*) FROM billers WHERE spec_id = ?");
                $check_billers->execute([$id]);
                $biller_dependencies = $check_billers->fetchColumn();
                
                // Check if spec is used in prima_data
                $check_prima = $pdo->prepare("SELECT COUNT(*) FROM prima_data WHERE bank_spec_id = ? OR biller_spec_id = ?");
                $check_prima->execute([$id, $id]);
                $prima_dependencies = $check_prima->fetchColumn();
                
                $dependencies = $bank_dependencies + $biller_dependencies + $prima_dependencies;
                $table = 'specs';
                
                // If dependencies exist, provide detailed information
                if ($dependencies > 0) {
                    $details = [];
                    if ($bank_dependencies > 0) {
                        $details[] = $bank_dependencies . ' bank' . ($bank_dependencies > 1 ? 's' : '');
                    }
                    if ($biller_dependencies > 0) {
                        $details[] = $biller_dependencies . ' biller' . ($biller_dependencies > 1 ? 's' : '');
                    }
                    if ($prima_dependencies > 0) {
                        $details[] = $prima_dependencies . ' connection' . ($prima_dependencies > 1 ? 's' : '');
                    }
                    
                    throw new Exception('Cannot delete spec: It is being used by ' . implode(', ', $details));
                }
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