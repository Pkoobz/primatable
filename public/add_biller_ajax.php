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
        
        // Get and validate form data
        $biller_name = trim($_POST['biller_name'] ?? '');
        $biller_id = trim($_POST['biller_id'] ?? '');
        $spec_id = $_POST['biller_spec'] ?? '';
        
        // Validate inputs
        if (empty($biller_name)) {
            throw new Exception('Biller name is required');
        }
        if (empty($biller_id) || !preg_match('/^\d{1,10}$/', $biller_id)) {
            throw new Exception('Biller ID must be 1-10 digits');
        }
        if (empty($spec_id)) {
            throw new Exception('Spec selection is required');
        }

        // Check for existing combination
        $check_stmt = $pdo->prepare("SELECT * FROM billers WHERE biller_id = ? AND spec_id = ?");
        $check_stmt->execute([$biller_id, $spec_id]);
        
        if ($check_stmt->fetch()) {
            throw new Exception("This combination of biller ID and spec already exists");
        }

        // Check for similar billers (for warning)
        $similar_stmt = $pdo->prepare("SELECT biller_id, name, spec_id FROM billers WHERE biller_id = ?");
        $similar_stmt->execute([$biller_id]);
        $similar_billers = $similar_stmt->fetchAll(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        try {
            // Insert biller
            $stmt = $pdo->prepare("INSERT INTO billers (biller_id, name, spec_id, created_by) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([
                $biller_id,
                $biller_name,
                $spec_id,
                $_SESSION['user_id']
            ]);

            if ($result) {
                $new_biller_id = $pdo->lastInsertId();
                
                // Log activity
                log_activity($pdo, 'create', 'billers', $new_biller_id, null, [
                    'name' => $biller_name,
                    'biller_id' => $biller_id,
                    'spec_id' => $spec_id,
                    'has_similar' => !empty($similar_billers)
                ], 'New biller created' . (!empty($similar_billers) ? ' (with similar entries)' : ''));

                $pdo->commit();

                // Prepare warning message if similar billers exist
                $warning = null;
                if (!empty($similar_billers)) {
                    $warning = "Note: This biller ID is also used by:\n";
                    foreach ($similar_billers as $similar) {
                        if ($similar['spec_id'] != $spec_id) {
                            $warning .= "- {$similar['name']} (Spec ID: {$similar['spec_id']})\n";
                        }
                    }
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Biller added successfully',
                    'warning' => $warning
                ]);
            } else {
                throw new Exception('Failed to add biller');
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == '23000') {
                throw new Exception('This combination of biller ID and spec already exists');
            }
            throw $e;
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit;