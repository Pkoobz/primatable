<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $bank_name = trim($_POST['bank_name'] ?? '');
        $bank_id = trim($_POST['bank_id'] ?? '');
        $spec_id = $_POST['bank_spec'] ?? '';

        // Check for existing combination
        $check_stmt = $pdo->prepare("SELECT * FROM banks WHERE bank_id = ? AND spec_id = ?");
        $check_stmt->execute([$bank_id, $spec_id]);
        
        if ($check_stmt->fetch()) {
            throw new Exception("This combination of bank ID and spec already exists");
        }

        // Check for similar banks (for warning)
        $similar_stmt = $pdo->prepare("SELECT bank_id, name, spec_id FROM banks WHERE bank_id = ?");
        $similar_stmt->execute([$bank_id]);
        $similar_banks = $similar_stmt->fetchAll(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO banks (bank_id, name, spec_id, created_by) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$bank_id, $bank_name, $spec_id, $_SESSION['user_id']]);

        if ($result) {
            $new_bank_id = $pdo->lastInsertId();
            
            // Log activity
            log_activity($pdo, 'create', 'banks', $new_bank_id, null, [
                'name' => $bank_name,
                'bank_id' => $bank_id,
                'spec_id' => $spec_id,
                'has_similar' => !empty($similar_banks)
            ]);

            $pdo->commit();

            // Prepare warning message
            $warning = null;
            if (!empty($similar_banks)) {
                $warning = "Note: This bank ID is also used with different specs:\n";
                foreach ($similar_banks as $similar) {
                    if ($similar['spec_id'] != $spec_id) {
                        $warning .= "- Bank: {$similar['name']}, Spec ID: {$similar['spec_id']}\n";
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Bank added successfully',
                'warning' => $warning
            ]);
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