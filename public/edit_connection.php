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
        
        // Update status
        $stmt = $pdo->prepare("UPDATE prima_data SET status = ?, updated_by = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_SESSION['user_id'], $id]);

        // Update channels
        $stmt = $pdo->prepare("DELETE FROM connection_channels WHERE prima_data_id = ?");
        $stmt->execute([$id]);

        $channels = $_POST['channels'];
        $dates = $_POST['channel_dates'];

        $stmt = $pdo->prepare("INSERT INTO connection_channels 
            (prima_data_id, channel_id, date_live, created_by, updated_by) 
            VALUES (?, ?, ?, ?, ?)");

        for ($i = 0; $i < count($channels); $i++) {
            $stmt->execute([
                $id,
                $channels[$i],
                $dates[$i],
                $_SESSION['user_id'],
                $_SESSION['user_id']
            ]);
        }

        // Log the update
        log_activity($pdo, 'update', 'prima_data', $id, 
            ['status' => $connection['status']], 
            ['status' => $_POST['status']], 
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