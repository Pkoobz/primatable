<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (!is_logged_in() || !is_admin()) {
    redirect('index.php');
}

$database = new Database();
$pdo = $database->getConnection();

// Get connection data
$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT pd.*, 
    b.name as bank_name,
    bl.name as biller_name,
    bs.name as bank_spec_name,
    bls.name as biller_spec_name
    FROM prima_data pd
    LEFT JOIN banks b ON pd.bank_id = b.id
    LEFT JOIN billers bl ON pd.biller_id = bl.id
    LEFT JOIN specs bs ON pd.bank_spec_id = bs.id
    LEFT JOIN specs bls ON pd.biller_spec_id = bls.id
    WHERE pd.id = ?");
$stmt->execute([$id]);
$connection = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$connection) {
    redirect('index.php');
}

// Get all banks, billers, and specs for dropdowns
$banks = $pdo->query("SELECT * FROM banks ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$billers = $pdo->query("SELECT * FROM billers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$specs = $pdo->query("SELECT * FROM specs ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $pdo->beginTransaction();
        
        // Get fee values
        $fee_included = isset($_POST['fee_inclusion']) && $_POST['fee_inclusion'] === 'exclude' ? 0 : 1;
        $fee_bank = isset($_POST['fee_bank']) && is_numeric($_POST['fee_bank']) ? (float)$_POST['fee_bank'] : 0.00;
        $fee_biller = isset($_POST['fee_biller']) && is_numeric($_POST['fee_biller']) ? (float)$_POST['fee_biller'] : 0.00;
        $fee_rintis = isset($_POST['fee_rintis']) && is_numeric($_POST['fee_rintis']) ? (float)$_POST['fee_rintis'] : 0.00;
        $admin_fee = isset($_POST['admin_fee']) && is_numeric($_POST['admin_fee']) ? (float)$_POST['admin_fee'] : 0.00;

        // If included, force admin fee to 0
        if ($fee_included) {
            $admin_fee = 0.00;
        }

        // Update the SQL query to include admin_fee
        $stmt = $pdo->prepare("
            UPDATE prima_data 
            SET status = ?, 
                fee_bank = ?,
                fee_biller = ?,
                fee_rintis = ?,
                fee_included = ?,
                admin_fee = ?,
                updated_by = ? 
            WHERE id = ?
        ");

        $stmt->execute([
            $_POST['status'],
            $fee_bank,
            $fee_biller,
            $fee_rintis,
            $fee_included,
            $admin_fee,
            $_SESSION['user_id'],
            $id
        ]);

        // Update channels
        $stmt = $pdo->prepare("DELETE FROM connection_channels WHERE prima_data_id = ?");
        $stmt->execute([$id]);

        if (isset($_POST['channels']) && isset($_POST['channel_dates'])) {
            $channels = $_POST['channels'];
            $dates = $_POST['channel_dates'];

            $stmt = $pdo->prepare("
                INSERT INTO connection_channels 
                (prima_data_id, channel_id, date_live, created_by, updated_by) 
                VALUES (?, ?, ?, ?, ?)
            ");

            for ($i = 0; $i < count($channels); $i++) {
                if (!empty($channels[$i]) && !empty($dates[$i])) {
                    $stmt->execute([
                        $id,
                        $channels[$i],
                        $dates[$i],
                        $_SESSION['user_id'],
                        $_SESSION['user_id']
                    ]);
                }
            }
        }

        // Log the update with fee information
        log_activity($pdo, 'update', 'prima_data', $id, 
            [
                'status' => $connection['status'],
                'fee_bank' => $connection['fee_bank'],
                'fee_biller' => $connection['fee_biller'],
                'fee_rintis' => $connection['fee_rintis'],
                'fee_included' => $connection['fee_included']
            ], 
            [
                'status' => $_POST['status'],
                'fee_bank' => $fee_bank,
                'fee_biller' => $fee_biller,
                'fee_rintis' => $fee_rintis,
                'fee_included' => $fee_included
            ], 
            $_POST['notes'] ?? null
        );

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Changes saved successfully']);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Connection - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold mb-6">Edit Connection</h2>
            
            <form id="editConnectionForm" method="POST" class="space-y-6">
                <!-- Display-only fields -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bank</label>
                        <div class="mt-1 p-2 bg-gray-50 rounded-md">
                            <?php echo htmlspecialchars($connection['bank_name']); ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Biller</label>
                        <div class="mt-1 p-2 bg-gray-50 rounded-md">
                            <?php echo htmlspecialchars($connection['biller_name']); ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bank Spec</label>
                        <div class="mt-1 p-2 bg-gray-50 rounded-md">
                            <?php echo htmlspecialchars($connection['bank_spec_name']); ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Biller Spec</label>
                        <div class="mt-1 p-2 bg-gray-50 rounded-md">
                            <?php echo htmlspecialchars($connection['biller_spec_name']); ?>
                        </div>
                    </div>
                </div>

                <!-- Editable fields -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                        <option value="active" <?php echo $connection['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $connection['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <!-- Channels Section -->
                <div class="mt-6">
                    <h4 class="text-lg font-medium text-gray-700 mb-4">Channels</h4>
                    <div id="channelContainer" class="space-y-4">
                        <?php
                        // Get existing channels
                        $stmt = $pdo->prepare("
                            SELECT cc.*, ch.name as channel_name 
                            FROM connection_channels cc
                            JOIN channels ch ON cc.channel_id = ch.id
                            WHERE cc.prima_data_id = ?
                            ORDER BY cc.date_live
                        ");
                        $stmt->execute([$id]);
                        $existing_channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Get all available channels
                        $stmt = $pdo->query("SELECT * FROM channels ORDER BY name");
                        $all_channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($existing_channels as $channel): ?>
                            <div class="channel-entry bg-gray-50 p-4 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <select name="channels[]" class="w-full rounded-md border-gray-300 shadow-sm mr-2">
                                            <?php foreach ($all_channels as $ch): ?>
                                                <option value="<?php echo $ch['id']; ?>" 
                                                    <?php echo $ch['id'] == $channel['channel_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($ch['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="flex-1 ml-2">
                                        <input type="date" name="channel_dates[]" 
                                            value="<?php echo $channel['date_live']; ?>"
                                            class="w-full rounded-md border-gray-300 shadow-sm" 
                                            required>
                                    </div>
                                    <button type="button" onclick="removeChannel(this)" 
                                            class="ml-2 text-red-600 hover:text-red-800">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="addChannel()" 
                            class="mt-4 inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Add Channel
                    </button>
                </div>

                <div class="mt-6 border-t pt-6">
                    <h4 class="text-lg font-medium text-gray-900 mb-4">Fee Information</h4>
                    
                    <!-- Fee Inclusion Radio Buttons -->
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Fee Status</label>
                        <div class="flex space-x-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="fee_inclusion" value="include" 
                                    <?php echo $connection['fee_included'] == 1 ? 'checked' : ''; ?>
                                    class="form-radio text-blue-600">
                                <span class="ml-2">Include</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="fee_inclusion" value="exclude"
                                    <?php echo $connection['fee_included'] == 0 ? 'checked' : ''; ?>
                                    class="form-radio text-blue-600">
                                <span class="ml-2">Exclude</span>
                            </label>
                        </div>
                    </div>

                    <!-- Fee Amount Fields -->
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="fee_bank">
                                Fee Bank
                            </label>
                            <input type="number" 
                                name="fee_bank" 
                                id="fee_bank" 
                                step="0.01" 
                                min="0" 
                                value="<?php echo number_format((float)$connection['fee_bank'], 2, '.', ''); ?>"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                oninput="calculateTotalFee()">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="fee_biller">
                                Fee Biller
                            </label>
                            <input type="number" 
                                name="fee_biller" 
                                id="fee_biller" 
                                step="0.01" 
                                min="0" 
                                value="<?php echo number_format((float)$connection['fee_biller'], 2, '.', ''); ?>"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                oninput="calculateTotalFee()">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="fee_rintis">
                                Fee Rintis
                            </label>
                            <input type="number" 
                                name="fee_rintis" 
                                id="fee_rintis" 
                                step="0.01" 
                                min="0" 
                                value="<?php echo number_format((float)$connection['fee_rintis'], 2, '.', ''); ?>"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                oninput="calculateTotalFee()">
                        </div>
                        <div id="adminFeeSection" class="mt-4" style="display: <?php echo $connection['fee_included'] == 0 ? 'block' : 'none'; ?>">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="admin_fee">
                                Admin Fee
                            </label>
                            <input type="number" 
                                name="admin_fee" 
                                id="admin_fee" 
                                step="0.01" 
                                min="0" 
                                value="<?php echo number_format((float)$connection['admin_fee'], 2, '.', ''); ?>"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                oninput="calculateTotalFee()">
                        </div>
                    </div>
                    
                    <!-- Total Fee Display -->
                    <div class="mt-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Total Fee</label>
                        <div id="total_fee" class="text-xl font-bold text-gray-800 bg-gray-100 p-2 rounded">
                            <?php 
                            $total = (float)$connection['fee_bank'] + 
                                    (float)$connection['fee_biller'] + 
                                    (float)$connection['fee_rintis'];
                            echo number_format($total, 2);
                            ?>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea name="notes" id="notesInput" rows="3" 
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                        placeholder="Add notes about this connection..."></textarea>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Previous Notes</label>
                    <div class="mt-1 p-4 bg-gray-50 rounded-md space-y-2 max-h-40 overflow-y-auto">
                    <?php
                        $notes_sql = "SELECT al.notes, al.created_at, u.username 
                                      FROM activity_logs al
                                      JOIN users u ON al.user_id = u.id
                                      WHERE al.record_id = ? 
                                      AND al.table_name = 'prima_data'
                                      AND al.notes IS NOT NULL
                                      AND al.notes != 'No notes added'
                                      ORDER BY al.created_at DESC";
                        $notes_stmt = $pdo->prepare($notes_sql);
                        $notes_stmt->execute([$id]);
                        $previous_notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($previous_notes)): 
                            foreach ($previous_notes as $note): ?>
                                <div class="text-sm border-l-4 border-blue-500 pl-3 mb-3">
                                    <p class="text-gray-700"><?php echo htmlspecialchars($note['notes']); ?></p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        By <?php echo htmlspecialchars($note['username']); ?> 
                                        on <?php echo date('Y-m-d H:i', strtotime($note['created_at'])); ?>
                                    </p>
                                </div>
                        <?php 
                            endforeach;
                        else: ?>
                            <p class="text-gray-500 text-sm italic">No previous notes</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex justify-between">
                    <button type="button" onclick="deleteConnection(<?php echo $id; ?>)" 
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                        Delete Connection
                    </button>
                    <div class="flex space-x-2">
                        <?php
                        $filter_params = [];
                        if (isset($_SERVER['HTTP_REFERER'])) {
                            $referer_parts = parse_url($_SERVER['HTTP_REFERER']);
                            if (isset($referer_parts['query'])) {
                                parse_str($referer_parts['query'], $filter_params);
                            }
                        }
                        $cancel_url = 'index.php';
                        if (!empty($filter_params)) {
                            $cancel_url .= '?' . http_build_query($filter_params);
                        }
                        ?>
                        <a href="<?php echo htmlspecialchars($cancel_url); ?>" 
                        class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                            Cancel
                        </a>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
        function showNotification(message, type = 'success') {
            alert(message); // Simple alert for now
        }

        function deleteConnection(id) {
            if (confirm('Are you sure you want to delete this connection? This action cannot be undone.')) {
                fetch('delete_connection.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'index.php';
                    } else {
                        alert(data.message || 'Error deleting connection');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the connection');
                });
            }
        }

        function addChannel() {
            const container = document.getElementById('channelContainer');
            const template = `
                <div class="channel-entry bg-gray-50 p-4 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <select name="channels[]" class="w-full rounded-md border-gray-300 shadow-sm mr-2">
                                <?php foreach ($all_channels as $ch): ?>
                                    <option value="<?php echo $ch['id']; ?>">
                                        <?php echo htmlspecialchars($ch['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-1 ml-2">
                            <input type="date" name="channel_dates[]" 
                                class="w-full rounded-md border-gray-300 shadow-sm" 
                                required>
                        </div>
                        <button type="button" onclick="removeChannel(this)" 
                                class="ml-2 text-red-600 hover:text-red-800">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', template);
        }

        function removeChannel(button) {
            const container = document.getElementById('channelContainer');
            if (container.children.length > 1) {
                button.closest('.channel-entry').remove();
            } else {
                alert('At least one channel is required');
            }
        }

        function calculateTotalFee() {
            const feeInclusion = document.querySelector('input[name="fee_inclusion"]:checked').value;
            const feeBank = parseFloat(document.getElementById('fee_bank').value) || 0;
            const feeBiller = parseFloat(document.getElementById('fee_biller').value) || 0;
            const feeRintis = parseFloat(document.getElementById('fee_rintis').value) || 0;
            const adminFeeInput = document.getElementById('admin_fee');
            
            // Calculate subtotal (sum of bank, biller, and rintis fees)
            const subTotal = feeBank + feeBiller + feeRintis;
            
            if (feeInclusion === 'exclude') {
                // Show admin fee section but don't auto-calculate it
                document.getElementById('adminFeeSection').style.display = 'block';
                // Get the manually entered admin fee
                const adminFee = parseFloat(adminFeeInput.value) || 0;
                // Total = subtotal + manually entered admin fee
                const totalFees = subTotal + adminFee;
                document.getElementById('total_fee').textContent = totalFees.toFixed(2);
            } else {
                // When included, hide admin fee section and set to 0
                document.getElementById('adminFeeSection').style.display = 'none';
                adminFeeInput.value = '0.00';
                document.getElementById('total_fee').textContent = subTotal.toFixed(2);
            }
        }

        ['fee_bank', 'fee_biller', 'fee_rintis', 'admin_fee'].forEach(id => {
            document.getElementById(id).addEventListener('input', calculateTotalFee);
        });

        document.querySelectorAll('input[name="fee_inclusion"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const adminFeeSection = document.getElementById('adminFeeSection');
                if (this.value === 'exclude') {
                    adminFeeSection.style.display = 'block';
                } else {
                    adminFeeSection.style.display = 'none';
                    document.getElementById('admin_fee').value = '0.00';
                }
                calculateTotalFee();
            });
        });

        // Initialize total fee calculation
        document.addEventListener('DOMContentLoaded', calculateTotalFee);

        // Form submission handler
        document.getElementById('editConnectionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    window.location.href = 'index.php';
                } else {
                    showNotification(data.message || 'Error saving changes', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving changes');
            })
            .finally(() => {
                submitButton.disabled = false;
            });
        });
    </script>
</body>
</html>