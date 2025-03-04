<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Start transaction
        $pdo->beginTransaction();

        // Validate channels
        if (isset($_POST['channels'])) {
            $channels = array_filter($_POST['channels'], 'strlen');
            if (count($channels) !== count(array_unique($channels))) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Duplicate channels detected. Each channel can only be added once per connection.'
                ]);
                exit;
            }
        }

        // Process fees
        $fee_included = isset($_POST['fee_inclusion']) && $_POST['fee_inclusion'] === 'exclude' ? 0 : 1;
        $fee_bank = isset($_POST['fee_bank']) && is_numeric($_POST['fee_bank']) ? (float)$_POST['fee_bank'] : 0.00;
        $fee_biller = isset($_POST['fee_biller']) && is_numeric($_POST['fee_biller']) ? (float)$_POST['fee_biller'] : 0.00;
        $fee_rintis = isset($_POST['fee_rintis']) && is_numeric($_POST['fee_rintis']) ? (float)$_POST['fee_rintis'] : 0.00;
        
        // Calculate admin fee based on inclusion type
        if ($fee_included) {
            $admin_fee = 0.00;
        } else {
            $admin_fee = isset($_POST['admin_fee']) && is_numeric($_POST['admin_fee']) ? 
                (float)$_POST['admin_fee'] : 0.00;
        }

        // Validate total fees matches admin fee when excluded
        $total_fees = $fee_bank + $fee_biller + $fee_rintis;
        if ($fee_bank < 0 || $fee_biller < 0 || $fee_rintis < 0 || $admin_fee < 0) {
            throw new Exception("Fee amounts cannot be negative");
        }
        
        // When excluded, ensure admin fee is provided
        if (!$fee_included && $admin_fee <= 0) {
            throw new Exception("Admin fee is required when fees are excluded");
        }
        

        // Validate required fields
        $required_fields = ['bank_id', 'biller_id', 'bank_spec_id', 'biller_spec_id'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Insert prima_data
        $stmt = $pdo->prepare("INSERT INTO prima_data 
            (bank_id, biller_id, bank_spec_id, biller_spec_id, status, 
            fee_bank, fee_biller, fee_rintis, fee_included, admin_fee,
            created_by, updated_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['bank_id'],
            $_POST['biller_id'],
            $_POST['bank_spec_id'],
            $_POST['biller_spec_id'],
            $_POST['status'] ?? 'active',
            $fee_bank,
            $fee_biller,
            $fee_rintis,
            $fee_included,
            $admin_fee,
            $_SESSION['user_id'],
            $_SESSION['user_id']
        ]);
        
        $prima_data_id = $pdo->lastInsertId();
        
        // Insert channels if provided
        if (!empty($_POST['channels']) && !empty($_POST['channel_dates'])) {
            $stmt = $pdo->prepare("INSERT INTO connection_channels 
                (prima_data_id, channel_id, date_live, created_by, updated_by) 
                VALUES (?, ?, ?, ?, ?)");
            
            $used_channels = [];
            
            foreach ($_POST['channels'] as $key => $channel_id) {
                if (!empty($channel_id) && !empty($_POST['channel_dates'][$key])) {
                    if (in_array($channel_id, $used_channels)) {
                        throw new Exception('Each channel can only be added once per connection.');
                    }
                    
                    $stmt->execute([
                        $prima_data_id,
                        $channel_id,
                        $_POST['channel_dates'][$key],
                        $_SESSION['user_id'],
                        $_SESSION['user_id']
                    ]);
                    
                    $used_channels[] = $channel_id;
                }
            }
        }
        
        // Log activity
        log_activity($pdo, 'create', 'prima_data', $prima_data_id, 
            null, 
            [
                'bank_id' => $_POST['bank_id'],
                'biller_id' => $_POST['biller_id'],
                'status' => $_POST['status'] ?? 'active',
                'fee_bank' => $fee_bank,
                'fee_biller' => $fee_biller,
                'fee_rintis' => $fee_rintis,
                'fee_included' => $fee_included,
                'admin_fee' => $admin_fee
            ],
            'New connection created'
        );
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Connection added successfully']);
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);