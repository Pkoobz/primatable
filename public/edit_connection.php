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
    $bank_id = $_POST['bank_id'] ?? '';
    $biller_id = $_POST['biller_id'] ?? '';
    $bank_spec_id = $_POST['bank_spec_id'] ?? '';
    $biller_spec_id = $_POST['biller_spec_id'] ?? '';
    $date_live = $_POST['date_live'] ?? '';
    $status = $_POST['status'] ?? '';
    $notes = $_POST['notes'] ?? '';

    try {
        $old_data = $connection;

        $stmt = $pdo->prepare("UPDATE prima_data SET 
            bank_id = ?, 
            biller_id = ?, 
            bank_spec_id = ?,
            biller_spec_id = ?,
            date_live = ?,
            status = ?,
            notes = ?,
            updated_by = ?
            WHERE id = ?");

        $new_data = [
            'bank_id' => $bank_id,
            'biller_id' => $biller_id,
            'bank_spec_id' => $bank_spec_id,
            'biller_spec_id' => $biller_spec_id,
            'date_live' => $date_live,
            'status' => $status,
            'notes' => $notes,
            'updated_by' => $_SESSION['user_id']
        ];

        $result = $stmt->execute([
            $bank_id, 
            $biller_id, 
            $bank_spec_id, 
            $biller_spec_id,
            $date_live,
            $status,
            $notes,
            $_SESSION['user_id'],
            $id
        ]);

        if ($result) {
            // Log the update activity
            if (!empty(trim($notes))) {
                log_activity($pdo, 'update', 'prima_data', $id, $old_data, $new_data, $notes);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Changes saved successfully!'
            ]);
            exit;
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to save changes.'
            ]);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
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
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bank</label>
                        <select name="bank_id" 
                                id="bank_id"
                                onchange="fetchBankSpecs(this.value)"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" 
                                required>
                            <?php foreach ($banks as $bank): ?>
                                <option value="<?php echo $bank['id']; ?>" 
                                    <?php echo $bank['id'] == $connection['bank_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bank['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Biller</label>
                        <select name="biller_id" 
                                id="biller_id"
                                onchange="fetchBillerSpecs(this.value)"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" 
                                required>
                            <?php foreach ($billers as $biller): ?>
                                <option value="<?php echo $biller['id']; ?>"
                                    <?php echo $biller['id'] == $connection['biller_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($biller['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bank Spec</label>
                        <select name="bank_spec_id" id="bank_spec_id" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                            <?php foreach ($specs as $spec): ?>
                                <option value="<?php echo $spec['id']; ?>" 
                                    <?php echo $spec['id'] == $connection['bank_spec_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Biller Spec</label>
                        <select name="biller_spec_id" id="biller_spec_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                            <?php foreach ($specs as $spec): ?>
                                <option value="<?php echo $spec['id']; ?>"
                                    <?php echo $spec['id'] == $connection['biller_spec_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date Live</label>
                        <input type="date" name="date_live" value="<?php echo $connection['date_live']; ?>" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                            <option value="active" <?php echo $connection['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $connection['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
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
                        <a href="index.php" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Cancel</a>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
        function showNotification(message, type = 'success') {
            alert(message); // Simple alert for now, you can replace with a better UI notification
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

        let isSubmitting = false;

        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (isSubmitting) return;
            isSubmitting = true;
            
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
                    // Update last saved specs
                    const bankSpecSelect = document.getElementById('bank_spec_id');
                    const billerSpecSelect = document.getElementById('biller_spec_id');
                    sessionStorage.setItem('last_bank_spec', bankSpecSelect.value);
                    sessionStorage.setItem('last_biller_spec', billerSpecSelect.value);
                    
                    showNotification(data.message, 'success');
                    document.getElementById('notesInput').value = '';
                } else {
                    showNotification(data.message || 'Error saving changes', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving changes');
            })
            .finally(() => {
                isSubmitting = false;
                submitButton.disabled = false;
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const bankSelect = document.getElementById('bank_id');
            const billerSelect = document.getElementById('biller_id');
            const bankSpecSelect = document.getElementById('bank_spec_id');
            const billerSpecSelect = document.getElementById('biller_spec_id');
            
            // Store initial specs when page loads
            const currentBankSpec = bankSpecSelect.value;
            const currentBillerSpec = billerSpecSelect.value;
            
            // Store these as the "last saved" values
            sessionStorage.setItem('last_bank_spec', currentBankSpec);
            sessionStorage.setItem('last_biller_spec', currentBillerSpec);
            
            // Update specs when bank/biller changes
            bankSelect.addEventListener('change', function() {
                const lastSavedSpec = sessionStorage.getItem('last_bank_spec');
                if (bankSpecSelect.value === lastSavedSpec) {
                    // Only fetch new spec if we haven't manually changed it
                    fetchBankSpecs(this.value);
                } else {
                    // Ask user if they want to update
                    if (confirm('Would you like to update to the bank\'s default spec?')) {
                        fetchBankSpecs(this.value);
                    }
                }
            });
            
            billerSelect.addEventListener('change', function() {
                const lastSavedSpec = sessionStorage.getItem('last_biller_spec');
                if (billerSpecSelect.value === lastSavedSpec) {
                    // Only fetch new spec if we haven't manually changed it
                    fetchBillerSpecs(this.value);
                } else {
                    // Ask user if they want to update
                    if (confirm('Would you like to update to the biller\'s default spec?')) {
                        fetchBillerSpecs(this.value);
                    }
                }
            });
        });

        function fetchBankSpecs(bankId) {
            if (!bankId) return;
            
            const manualChange = sessionStorage.getItem('bank_spec_manual_change');
            if (manualChange === 'true') {
                // Ask user if they want to update to bank's default spec
                if (confirm('Would you like to update to the bank\'s default spec?')) {
                    sessionStorage.removeItem('bank_spec_manual_change');
                } else {
                    return;
                }
            }
            
            fetch(`get_specs.php?bank_id=${bankId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('bank_spec_id').value = data.spec_id;
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function fetchBillerSpecs(billerId) {
            if (!billerId) return;
            
            const manualChange = sessionStorage.getItem('biller_spec_manual_change');
            if (manualChange === 'true') {
                // Ask user if they want to update to biller's default spec
                if (confirm('Would you like to update to the biller\'s default spec?')) {
                    sessionStorage.removeItem('biller_spec_manual_change');
                } else {
                    return;
                }
            }
            
            fetch(`get_specs.php?biller_id=${billerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('biller_spec_id').value = data.spec_id;
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        document.getElementById('bank_spec_id').addEventListener('change', function() {
            sessionStorage.setItem('bank_spec_manual_change', 'true');
        });

        document.getElementById('biller_spec_id').addEventListener('change', function() {
            sessionStorage.setItem('biller_spec_manual_change', 'true');
        });
    </script>
</body>
</html>