<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
include '../includes/nav.php';

if (!is_logged_in()) {
    redirect('./public/login.php');
}

$database = new Database();
$pdo = $database->getConnection();

$user_role = get_user_role($_SESSION['user_id']);
$is_admin = is_admin();

// Initialize variables first
$search = isset($_GET['search']) ? $_GET['search'] : '';
$bank_filter = isset($_GET['bank_filter']) ? $_GET['bank_filter'] : '';
$biller_filter = isset($_GET['biller_filter']) ? $_GET['biller_filter'] : '';
$spec_filter = isset($_GET['spec_filter']) ? $_GET['spec_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'bank';
$sort = isset($_GET['sort']) ? strtolower($_GET['sort']) : 'asc';
$params = [];

// Define sort columns
$sort_columns = [
    'bank' => 'b.name',
    'biller' => 'bl.name',
    'spec' => 'bs.name',
    'status' => 'pd.status'
];

// Get the sort column
$sort_column = $sort_columns[$sort_by] ?? 'b.name';

// Verify user exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

if (!$user) {
    session_destroy();
    redirect('./public/login.php');
}

// Get filter data
$sql_banks = "SELECT * FROM banks ORDER BY name " . ($sort_by == 'bank' ? $sort : 'ASC');
$stmt = $pdo->query($sql_banks);
$banks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql_billers = "SELECT * FROM billers ORDER BY name " . ($sort_by == 'biller' ? $sort : 'ASC');
$stmt = $pdo->query($sql_billers);
$billers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql_specs = "SELECT * FROM specs ORDER BY name " . ($sort_by == 'spec' ? $sort : 'ASC');
$stmt = $pdo->query($sql_specs);
$specs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Main query
$sql = "SELECT pd.*, 
        b.name as bank_name,
        b.bank_id as bank_id_number,
        bl.biller_id as biller_id_number,
        bl.name as biller_name,
        bs.name as bank_spec_name,
        bls.name as biller_spec_name,
        u.username as created_by_user,
        pd.status
        FROM prima_data pd
        LEFT JOIN banks b ON pd.bank_id = b.id
        LEFT JOIN billers bl ON pd.biller_id = bl.id
        LEFT JOIN specs bs ON pd.bank_spec_id = bs.id
        LEFT JOIN specs bls ON pd.biller_spec_id = bls.id
        LEFT JOIN users u ON pd.created_by = u.id
        WHERE 1=1";

// Before executing the main query, add this count query
$count_sql = "SELECT COUNT(*) as total 
              FROM prima_data pd
              LEFT JOIN banks b ON pd.bank_id = b.id
              LEFT JOIN billers bl ON pd.biller_id = bl.id
              LEFT JOIN specs bs ON pd.bank_spec_id = bs.id
              LEFT JOIN specs bls ON pd.biller_spec_id = bls.id
              LEFT JOIN users u ON pd.created_by = u.id
              WHERE 1=1";

// Apply filters
if ($search) {
    $filter = " AND (b.name LIKE :search OR bl.name LIKE :search OR bs.name LIKE :search OR bls.name LIKE :search)";
    $sql .= $filter;
    $count_sql .= $filter;
    $params[':search'] = "%$search%";
}

if ($bank_filter) {
    $filter = " AND b.id = :bank_id";
    $sql .= $filter;
    $count_sql .= $filter;
    $params[':bank_id'] = $bank_filter;
}

if ($biller_filter) {
    $filter = " AND bl.id = :biller_id";
    $sql .= $filter;
    $count_sql .= $filter;
    $params[':biller_id'] = $biller_filter;
}

if ($spec_filter) {
    $filter = " AND (pd.bank_spec_id = :spec_id OR pd.biller_spec_id = :spec_id)";
    $sql .= $filter;
    $count_sql .= $filter;
    $params[':spec_id'] = $spec_filter;
}

if ($status_filter) {
    $filter = " AND pd.status = :status";
    $sql .= $filter;
    $count_sql .= $filter;
    $params[':status'] = $status_filter;
}

// Apply sorting
$sql .= " ORDER BY " . $sort_column . " " . $sort;

// Execute main query
$stmt = $pdo->prepare($count_sql);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_items = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_items / $items_per_page);

// Then execute main query with pagination
$sql .= " LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);

// Bind all parameters including limit and offset
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Remove duplicate status filter
$statuses = [
    ['status' => 'active'],
    ['status' => 'inactive']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="assets/js/delete.js" defer></script>
</head>
<body class="bg-gray-100">
    <div id="notification" class="fixed top-4 right-4 z-50 hidden">
        <div class="bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg">
            <span id="notification-message"></span>
        </div>
    </div>
    <div class="container mx-auto px-4 py-8">
        <?php if ($is_admin): ?>
            <div class="mb-6 ml-12 mr-12">
                <!-- Add Operations -->
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Add Items</h3>
                <div class="flex flex-wrap gap-2 mb-4">
                    <button onclick="toggleModal('Bank')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2" data-modal="bank">
                        Add Bank
                    </button>
                    <button onclick="toggleModal('Biller')" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2" data-modal="biller">
                        Add Biller
                    </button>
                    <button onclick="toggleModal('Spec')" class="bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2" data-modal="spec">
                        Add Spec
                    </button>
                    <button onclick="toggleModal('Channel')" class="bg-pink-500 hover:bg-pink-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2" data-modal="channel">
                        Add Channel
                    </button>
                    <button onclick="toggleModal('Connection')" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded inline-flex items-center" data-modal="connection">
                        Add Connection
                    </button>
                    <button onclick="toggleModal('Lists')" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2 ml-2">
                        View Lists
                    </button>
                </div>
                
                <h3 class="text-lg font-semibold text-gray-700 mb-2">Delete Items</h3>
                <div class="flex flex-wrap gap-2">
                    <!-- After the View Lists button -->
                    <button onclick="toggleDeleteModal('Bank')" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2">
                        Delete Bank
                    </button>
                    <button onclick="toggleDeleteModal('Biller')" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2">
                        Delete Biller
                    </button>
                    <button onclick="toggleDeleteModal('Spec')" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2">
                        Delete Spec
                    </button>
                    <button onclick="toggleDeleteModal('Channel')" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2">
                        Delete Channel
                    </button>
                </div>
            </div>
            
                <!-- Bank Modal -->
                <div id="bankModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity modal-backdrop" onclick="closeModals()">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form id="bankForm" action="add_bank_ajax.php" method="POST" class="p-6">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Add New Bank</h3>
                                <!-- Add these required form fields -->
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="bank_name">
                                        Bank Name
                                    </label>
                                    <input type="text" 
                                        name="bank_name" 
                                        id="bank_name" 
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        required>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="bank_id">Bank ID</label>
                                    <input type="text" 
                                        name="bank_id" 
                                        id="bank_id" 
                                        pattern="[0-9-]+"
                                        maxlength="20"
                                        oninput="this.value = this.value.replace(/[^0-9-]/g, '')"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        required>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="bank_spec">Bank Spec</label>
                                    <select name="bank_spec" id="bank_spec" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                        <option value="">Select Spec</option>
                                        <?php foreach ($specs as $spec): ?>
                                        <option value="<?php echo $spec['id']; ?>">
                                            <?php echo htmlspecialchars($spec['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="flex justify-end">
                                    <button type="button" onclick="closeModals()" class="mr-2 px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- Biller Modal -->
                <div id="billerModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity modal-backdrop" onclick="closeModals()">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form id="billerForm" action="add_biller_ajax.php" method="POST" class="p-6">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Add New Biller</h3>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="biller_name">Biller Name</label>
                                    <input type="text" name="biller_name" id="biller_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="biller_id">Biller ID (max 10 digits)</label>
                                    <input type="text" 
                                        name="biller_id" 
                                        id="biller_id" 
                                        pattern="\d{1,10}"
                                        maxlength="10"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10)"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                        required>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="biller_spec">Biller Spec</label>
                                    <select name="biller_spec" id="biller_spec" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                        <option value="">Select Spec</option>
                                        <?php foreach ($specs as $spec): ?>
                                        <option value="<?php echo $spec['id']; ?>">
                                            <?php echo htmlspecialchars($spec['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="flex justify-end">
                                <button type="button" onclick="closeModals()" class="mr-2 px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-700">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Spec Modal -->
                <div id="specModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity modal-backdrop" onclick="closeModals()">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form id="specForm" action="add_spec_ajax.php" method="POST" class="p-6">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Add New Spec</h3>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="spec_name">Spec Name</label>
                                    <input type="text" name="spec_name" id="spec_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                <div class="flex justify-end">
                                <button type="button" onclick="closeModals()" class="mr-2 px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-700">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- Channel Modal -->
                <div id="channelModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity modal-backdrop" onclick="closeModals()">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form id="channelForm" action="add_channel_ajax.php" method="POST" class="p-6">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Add New Channel</h3>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="channel_name">Channel Name</label>
                                    <input type="text" name="channel_name" id="channel_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="channel_description">Description</label>
                                    <textarea name="channel_description" id="channel_description" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                                </div>
                                <div class="flex justify-end">
                                    <button type="button" onclick="closeModals()" class="mr-2 px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-pink-500 text-white rounded hover:bg-pink-700">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Connection Modal -->
                <div id="connectionModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity modal-backdrop" onclick="closeModals()">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form id="connectionForm" action="add_connection_ajax.php" method="POST" class="p-6">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="bank_id">Bank</label>
                                        <select name="bank_id" id="bank_id" 
                                                onchange="fetchBankSpecs(this.value)"
                                                class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                                                required>
                                            <option value="">Select Bank</option>
                                            <?php foreach ($banks as $bank): ?>
                                            <option value="<?php echo $bank['id']; ?>">
                                                <?php echo htmlspecialchars($bank['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2">Bank Spec</label>
                                        <input type="text" 
                                            id="bank_spec_display" 
                                            class="shadow border rounded w-full py-2 px-3 text-gray-700 bg-gray-100" 
                                            readonly>
                                        <input type="hidden" name="bank_spec_id" id="bank_spec_id">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="biller_id">Biller</label>
                                        <select name="biller_id" id="biller_id" 
                                                onchange="fetchBillerSpecs(this.value)"
                                                class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                                                required>
                                            <option value="">Select Biller</option>
                                            <?php foreach ($billers as $biller): ?>
                                            <option value="<?php echo $biller['id']; ?>">
                                                <?php echo htmlspecialchars($biller['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2">Biller Spec</label>
                                        <input type="text" 
                                            id="biller_spec_display" 
                                            class="shadow border rounded w-full py-2 px-3 text-gray-700 bg-gray-100" 
                                            readonly>
                                        <input type="hidden" name="biller_spec_id" id="biller_spec_id">
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <h4 class="text-lg font-medium text-gray-900 mb-4">Channels</h4>
                                    <div id="channelContainer" class="space-y-4">
                                        <div class="channel-entry bg-gray-50 p-4 rounded-lg">
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="flex-1">
                                                    <select name="channels[]" class="w-full rounded-md border-gray-300 shadow-sm mr-2">
                                                        <option value="">Select Channel</option>
                                                        <?php
                                                        $stmt = $pdo->query("SELECT id, name FROM channels ORDER BY name");
                                                        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                        foreach ($channels as $channel): ?>
                                                            <option value="<?php echo $channel['id']; ?>">
                                                                <?php echo htmlspecialchars($channel['name']); ?>
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
                                    </div>
                                    <button type="button" onclick="addChannel()" 
                                            class="mt-4 inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Add Channel
                                    </button>
                                </div>
                                <!-- fee information -->
                                <div class="mt-6 border-t pt-6">
                                    <h4 class="text-lg font-medium text-gray-900 mb-4">Fee Information</h4>
                                    
                                    <!-- Fee Inclusion Radio Buttons -->
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2">Fee Status</label>
                                        <div class="flex space-x-4">
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="fee_inclusion" value="include" checked 
                                                    class="form-radio text-blue-600">
                                                <span class="ml-2">Include</span>
                                            </label>
                                            <label class="inline-flex items-center">
                                                <input type="radio" name="fee_inclusion" value="exclude" 
                                                    class="form-radio text-blue-600">
                                                <span class="ml-2">Exclude</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Fee Amount Fields -->
                                    <div class="grid grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-gray-700 text-sm font-bold mb-2" for="fee_bank">Fee Bank</label>
                                            <input type="number" name="fee_bank" id="fee_bank" step="0.01" min="0" value="0.00"
                                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                oninput="calculateTotalFee()">
                                        </div>
                                        <div>
                                            <label class="block text-gray-700 text-sm font-bold mb-2" for="fee_biller">Fee Biller</label>
                                            <input type="number" name="fee_biller" id="fee_biller" step="0.01" min="0" value="0.00"
                                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                oninput="calculateTotalFee()">
                                        </div>
                                        <div>
                                            <label class="block text-gray-700 text-sm font-bold mb-2" for="fee_rintis">Fee Rintis</label>
                                            <input type="number" name="fee_rintis" id="fee_rintis" step="0.01" min="0" value="0.00"
                                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                                oninput="calculateTotalFee()">
                                        </div>
                                    </div>

                                    <!-- Admin Fee Section -->
                                    <div id="adminFeeSection" class="mt-4" style="display: none;">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="admin_fee">Admin Fee</label>
                                        <input type="number" name="admin_fee" id="admin_fee" step="0.01" min="0" value="0.00"
                                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                            oninput="calculateTotalFee()">
                                    </div>
                                    
                                    <!-- Total Fee Display -->
                                    <div class="mt-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2">Total Fee</label>
                                        <div id="total_fee" class="text-xl font-bold text-gray-800 bg-gray-100 p-2 rounded">
                                            0.00
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="status">Status</label>
                                    <select name="status" id="status" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="flex justify-end">
                                    <button type="button" onclick="closeModals()" class="mr-2 px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-700">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- view list -->
                <div id="listsModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity modal-backdrop" onclick="closeModals()">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="flex justify-between mb-4">
                                    <h3 class="text-lg font-medium leading-6 text-gray-900">Master Lists</h3>
                                    <button onclick="closeModals()" class="text-gray-400 hover:text-gray-500">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Banks List -->
                                    <div>
                                        <h4 class="font-medium text-gray-900 mb-2">Banks</h4>
                                        <div class="bg-gray-50 p-4 rounded-lg max-h-96 overflow-y-auto">
                                            <table class="min-w-full">
                                                <thead>
                                                    <tr>
                                                        <th class="text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                                        <th class="text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                                        <th class="text-left text-xs font-medium text-gray-500 uppercase">Spec</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200">
                                                    <?php foreach ($banks as $bank): ?>
                                                    <tr>
                                                        <td class="py-2"><?php echo htmlspecialchars($bank['name']); ?></td>
                                                        <td class="py-2 font-mono"><?php echo htmlspecialchars($bank['bank_id']); ?></td>
                                                        <td class="py-2"><?php 
                                                            $stmt = $pdo->prepare("SELECT name FROM specs WHERE id = ?");
                                                            $stmt->execute([$bank['spec_id']]);
                                                            $spec = $stmt->fetch(PDO::FETCH_ASSOC);
                                                            echo htmlspecialchars($spec['name'] ?? 'N/A');
                                                        ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <!-- Billers List -->
                                    <div>
                                        <h4 class="font-medium text-gray-900 mb-2">Billers</h4>
                                        <div class="bg-gray-50 p-4 rounded-lg max-h-96 overflow-y-auto">
                                            <table class="min-w-full">
                                                <thead>
                                                    <tr>
                                                        <th class="text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                                        <th class="text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                                        <th class="text-left text-xs font-medium text-gray-500 uppercase">Spec</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200">
                                                    <?php foreach ($billers as $biller): ?>
                                                    <tr>
                                                        <td class="py-2"><?php echo htmlspecialchars($biller['name']); ?></td>
                                                        <td class="py-2 font-mono"><?php echo htmlspecialchars($biller['biller_id']); ?></td>
                                                        <td class="py-2"><?php 
                                                            $stmt = $pdo->prepare("SELECT name FROM specs WHERE id = ?");
                                                            $stmt->execute([$biller['spec_id']]);
                                                            $spec = $stmt->fetch(PDO::FETCH_ASSOC);
                                                            echo htmlspecialchars($spec['name'] ?? 'N/A');
                                                        ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Delete Bank Modal -->
                <div id="deleteBankModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity modal-backdrop" onclick="closeModals()">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Delete Bank</h3>
                                <div class="max-h-60 overflow-y-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($banks as $bank): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($bank['name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <button onclick="deleteItem('bank', <?php echo $bank['id']; ?>)" 
                                                            class="text-red-600 hover:text-red-900">Delete</button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" onclick="closeModals()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Delete Biller Modal -->
                <div id="deleteBillerModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity modal-backdrop" onclick="closeModals()">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Delete Biller</h3>
                                <div class="max-h-60 overflow-y-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($billers as $biller): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($biller['name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <button onclick="deleteItem('biller', <?php echo $biller['id']; ?>)" 
                                                            class="text-red-600 hover:text-red-900">Delete</button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" onclick="closeModals()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Delete Spec -->
                <div id="deleteSpecModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity modal-backdrop" onclick="closeModals()">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Delete Spec</h3>
                                <div class="max-h-60 overflow-y-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($specs as $spec): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($spec['name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <button onclick="deleteItem('spec', <?php echo $spec['id']; ?>)" 
                                                            class="text-red-600 hover:text-red-900">Delete</button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" onclick="closeModals()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Delete Channel Modal -->
                <div id="deleteChannelModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity modal-backdrop" onclick="closeModals()">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Delete Channel</h3>
                                <div class="max-h-60 overflow-y-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php
                                            $stmt = $pdo->query("SELECT id, name FROM channels ORDER BY name");
                                            $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($channels as $channel): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($channel['name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <button onclick="deleteItem('channel', <?php echo $channel['id']; ?>)" 
                                                            class="text-red-600 hover:text-red-900">Delete</button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" onclick="closeModals()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
        <?php endif; ?>
            <!-- details -->
            <div id="detailsModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
                <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity modal-backdrop" onclick="closeDetailsModal()">
                        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                    </div>
                    <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white px-6 pt-5 pb-4">
                            <div class="flex items-start justify-between">
                                <h3 class="text-lg font-medium leading-6 text-gray-900" id="detailsTitle">
                                    Connection Details
                                </h3>
                                <div class="ml-3 flex h-7">
                                    <button type="button" 
                                        onclick="closeDetailsModal()"
                                            class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none">
                                        <span class="sr-only">Close</span>
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div id="detailsContent" class="text-sm text-gray-500">
                                    <!-- Content will be dynamically inserted here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            <div class="bg-blue-100 p-6 rounded-lg shadow-md mb-8 ml-12 mr-12">
                <form method="get" action="" class="space-y-4" id="filterForm">
                    <div class="grid grid-cols-6 gap-4">
                        <!-- Search Bar -->
                        <div class="col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                            <input type="text" id="search" name="search" 
                                value="<?php echo htmlspecialchars($search); ?>" 
                                placeholder="Search banks, billers, or specs..."
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <!-- Bank Filter -->
                        <div>
                            <label for="bank_filter" class="block text-sm font-medium text-gray-700">Bank</label>
                            <select id="bank_filter" name="bank_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">All Banks</option>
                                <?php foreach ($banks as $bank): ?>
                                <option value="<?php echo $bank['id']; ?>" 
                                        <?php echo $bank_filter == $bank['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bank['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Biller Filter -->
                        <div>
                            <label for="biller_filter" class="block text-sm font-medium text-gray-700">Biller</label>
                            <select id="biller_filter" name="biller_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">All Billers</option>
                                <?php foreach ($billers as $biller): ?>
                                <option value="<?php echo $biller['id']; ?>" 
                                        <?php echo $biller_filter == $biller['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($biller['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Spec Filter -->
                        <div>
                            <label for="spec_filter" class="block text-sm font-medium text-gray-700">Spec</label>
                            <select id="spec_filter" name="spec_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">All Specs</option>
                                <?php foreach ($specs as $spec): ?>
                                <option value="<?php echo $spec['id']; ?>" 
                                        <?php echo $spec_filter == $spec['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label for="status_filter" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="status_filter" name="status_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">All Status</option>
                                <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status['status']); ?>"
                                        <?php echo $status_filter == $status['status'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($status['status'])); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Filter Actions -->
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="resetFilters()" 
                            class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                            Reset Filters
                        </button>
                        <button type="submit" id="filterButton"
                            class="relative px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 flex items-center justify-center w-32">
                            <span id="filterButtonText" class="z-10">Apply Filters</span>
                                <div id="filterSpinner" 
                                class="absolute inset-0 hidden rounded-md" 
                                    style="background: url('https://cdn.dribbble.com/users/660047/screenshots/2549984/loader-circle3.gif') center center no-repeat; background-color: rgba(37, 99, 235, 0.9); background-size: contain;">
                                </div>
                        </button>
                    </div>

                    <!-- Hidden sort fields -->
                    <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                </form>

                <div class="flex space-x-2 mb-4">

    <button type="button" onclick="handleExportClick()" id="exportButton"
        class="relative bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
        <span id="exportButtonText" class="z-10 flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Export to Excel
        </span>
        <div id="exportSpinner" 
            class="absolute inset-0 hidden rounded-md" 
            style="background: url('https://th.bing.com/th/id/R.e42cb20be6939bc8590dede0a20cc6e0?rik=Z%2be10J5aE%2fbNQA&riu=http%3a%2f%2forig11.deviantart.net%2f03d6%2ff%2f2014%2f122%2f7%2fa%2floading_gif_by_zarzox-d7gwtxy.png&ehk=k6Hd%2bH5rAoGufy5%2fceZac7VuV1WDPSM%2bj9clzC5uzPo%3d&risl=&pid=ImgRaw&r=0') center center no-repeat; background-color: rgba(22, 163, 74, 0.9); background-size: contain;">
        </div>
    </button>
</div>
            </div>
            <!-- DataTable -->
            <div class="bg-white shadow-md rounded my-6 ml-12 mr-12">
                <table class="min-w-max w-full table-auto">
                    <thead>
                        <tr class="bg-blue-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">
                                <a href="?sort_by=bank&sort=<?php echo $sort_by == 'bank' && $sort == 'asc' ? 'desc' : 'asc'; ?>" class="flex items-center">
                                    Bank
                                    <?php if ($sort_by == 'bank'): ?>
                                        <span class="ml-1"><?php echo $sort == 'asc' ? '↑' : '↓'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="py-3 px-6 text-center">Bank ID</th>
                            <th class="py-3 px-6 text-left">
                                <a href="?sort_by=biller&sort=<?php echo $sort_by == 'biller' && $sort == 'asc' ? 'desc' : 'asc'; ?>" class="flex items-center">
                                    Biller
                                    <?php if ($sort_by == 'biller'): ?>
                                        <span class="ml-1"><?php echo $sort == 'asc' ? '↑' : '↓'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="py-3 px-6 text-center">Biller ID</th>
                            <th class="py-3 px-6 text-left">
                                <a href="?sort_by=spec&sort=<?php echo $sort_by == 'spec' && $sort == 'asc' ? 'desc' : 'asc'; ?>" class="flex items-center">
                                    Spec
                                    <?php if ($sort_by == 'spec'): ?>
                                        <span class="ml-1"><?php echo $sort == 'asc' ? '↑' : '↓'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="py-3 px-6 text-left">Details</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <?php if ($is_admin): ?>
                            <th class="py-3 px-6 text-center">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm font-light">
                        <?php if (!empty($result)): ?>
                            <?php foreach ($result as $key => $row): ?>
                                <?php 
                                // Get channels and their date_live for this connection
                                $stmt = $pdo->prepare("
                                    SELECT ch.name, cc.date_live 
                                    FROM connection_channels cc 
                                    JOIN channels ch ON cc.channel_id = ch.id 
                                    WHERE cc.prima_data_id = ? 
                                    ORDER BY cc.date_live
                                ");
                                $stmt->execute([$row['id']]);
                                $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <tr class="border-b border-gray-200 <?php echo $key % 2 === 0 ? 'bg-blue-50' : 'bg-blue-100'; ?> hover:bg-blue-200 transition-colors duration-150">
                                <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($row['bank_name']); ?></td>
                                <td class="py-3 px-6 text-center font-mono text-sm"><?php echo htmlspecialchars($row['bank_id_number']); ?></td>
                                <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($row['biller_name']); ?></td>
                                <td class="py-3 px-6 text-center font-mono text-sm"><?php echo htmlspecialchars($row['biller_id_number']); ?></td>
                                <td class="py-3 px-6 text-left">
                                    Bank: <?php echo htmlspecialchars($row['bank_spec_name']); ?><br>
                                    Biller: <?php echo htmlspecialchars($row['biller_spec_name']); ?>
                                </td>
                                <td class="py-3 px-6 text-left">
                                    <a href="#" onclick="viewDetails(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['bank_name']); ?>')" 
                                    class="text-blue-600 hover:text-blue-800">
                                        View Details
                                    </a>
                                </td>
                                <td class="py-3 px-6 text-center">
                                    <span class="<?php echo $row['status'] == 'active' ? 'bg-green-200 text-green-600' : 'bg-red-200 text-red-600'; ?> py-1 px-3 rounded-full text-xs">
                                           <?php echo htmlspecialchars(ucfirst($row['status'])); ?>
                                    </span>
                                </td>
                                    <?php if ($is_admin): ?>
                                        <td class="py-3 px-6 text-center">
                                            <div class="flex item-center justify-center">
                                                <a href="edit_connection.php?id=<?php echo $row['id']; ?>" 
                                                    class="w-4 mr-2 transform hover:text-purple-500 hover:scale-110">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $is_admin ? '6' : '5'; ?>" class="py-3 px-6 text-center">No data found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1): ?>
                    <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-4 rounded-lg shadow">
                        <div class="flex flex-1 justify-between sm:hidden">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?>" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>
                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?>" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-medium"><?php echo $offset + 1; ?></span>
                                    to
                                    <span class="font-medium"><?php echo min($offset + $items_per_page, $total_items); ?></span>
                                    of
                                    <span class="font-medium"><?php echo $total_items; ?></span>
                                    results
                                </p>
                            </div>
                            <div>
                                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                    <?php if ($current_page > 1): ?>
                                        <a href="?page=<?php echo $current_page - 1; ?>&<?php echo http_build_query(array_merge($_GET, ['page' => ($current_page - 1)])); ?>" 
                                        class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                            <span class="sr-only">Previous</span>
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    $range = 2;
                                    for ($i = max(1, $current_page - $range); $i <= min($total_pages, $current_page + $range); $i++):
                                    ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo $i === $current_page ? 'bg-blue-600 text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>

                                    <?php if ($current_page < $total_pages): ?>
                                        <a href="?page=<?php echo $current_page + 1; ?>&<?php echo http_build_query(array_merge($_GET, ['page' => ($current_page + 1)])); ?>" 
                                        class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                            <span class="sr-only">Next</span>
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>  
    </div>
    <script>
        // Add at the bottom of your script section
        const state = {
            selectedBank: null,
            selectedBiller: null,
            bankSpecs: {},
            billerSpecs: {}
        };

        function toggleModal(modalType) {
            closeModals();
            const modalId = `${modalType.toLowerCase()}Modal`;
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
            }
        }

        function closeModals() {
            const modals = document.querySelectorAll('[id$="Modal"]');
            modals.forEach(modal => modal.classList.add('hidden'));
        }

        function fetchBankSpecs(bankId) {
            if (!bankId) {
                document.getElementById('bank_spec_display').value = '';
                document.getElementById('bank_spec_id').value = '';
                return;
            }
            fetch(`get_specs.php?bank_id=${bankId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('bank_spec_display').value = data.spec_name;
                        document.getElementById('bank_spec_id').value = data.spec_id;
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function fetchBillerSpecs(billerId) {
            if (!billerId) {
                document.getElementById('biller_spec_display').value = '';
                document.getElementById('biller_spec_id').value = '';
                return;
            }
            fetch(`get_specs.php?biller_id=${billerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('biller_spec_display').value = data.spec_name;
                        document.getElementById('biller_spec_id').value = data.spec_id;
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Replace the existing submitForm function
        function submitForm(form) {
            const formData = new FormData(form);
            let allFieldsFilled = true;

            // Required fields to check
            const requiredFields = ['bank_id', 'biller_id', 'bank_spec_id', 'biller_spec_id'];
            
            requiredFields.forEach(field => {
                const value = formData.get(field);
                if (!value) {
                    allFieldsFilled = false;
                    document.querySelector(`[name="${field}"]`).classList.add('border-red-500');
                }
            });

            // Check if at least one channel is selected with a date
            const channels = form.querySelectorAll('select[name="channels[]"]');
            const dates = form.querySelectorAll('input[name="channel_dates[]"]');
            let hasValidChannel = false;

            for (let i = 0; i < channels.length; i++) {
                if (channels[i].value && dates[i].value) {
                    hasValidChannel = true;
                    break;
                }
            }

            if (!hasValidChannel) {
                showNotification('Please add at least one channel with a date', 'error');
                return false;
            }

            if (!allFieldsFilled) {
                showNotification('Please fill in all required fields', 'error');
                return false;
            }

            // If validation passes, submit the form
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModals();
                    showNotification('Connection added successfully');
                } else {
                    showNotification(data.message || 'Error adding connection', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while saving', 'error');
            });

            return false;
        }

        // Add these specific form handlers after your existing script tag
        document.getElementById('bankForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            try {
                const formData = new FormData(this);
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new TypeError("Expected JSON response");
                }

                const data = await response.json();
                console.log('Bank form response:', data);

                if (data.success) {
                    if (data.warning) {
                        await showNotification(data.warning, 'warning');
                        await new Promise(resolve => setTimeout(resolve, 2000));
                    }
                    await showNotification(data.message || 'Bank added successfully', 'success');
                    closeModals();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    if (data.message.includes('Duplicate entry')) {
                        await showNotification('This bank ID and spec combination already exists', 'error');
                    } else {
                        await showNotification(data.message || 'Error adding bank', 'error');
                    }
                }
            } catch (error) {
                console.error('Bank form error:', error);
                await showNotification('An error occurred while saving', 'error');
            } finally {
                submitButton.disabled = false;
            }
        });

        document.getElementById('billerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            try {
                const formData = new FormData(this);
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();
                console.log('Biller form response:', data);

                if (data.success) {
                    if (data.warning) {
                        // Show warning about duplicate ID but continue
                        await showNotification(data.warning, 'warning');
                        await new Promise(resolve => setTimeout(resolve, 2000));
                    }
                    await showNotification(data.message || 'Biller added successfully', 'success');
                    closeModals();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    await showNotification(data.message || 'Error adding biller', 'error');
                }
            } catch (error) {
                console.error('Biller form error:', error);
                await showNotification('An error occurred while saving', 'error');
            } finally {
                submitButton.disabled = false;
            }
        });

        document.querySelectorAll('#specForm, #channelForm').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formId = this.id;
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');

                try {
                    submitButton.disabled = true;

                    const response = await fetch(this.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    // Log raw response for debugging
                    console.log('Raw response:', response);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    console.log('Response data:', data);

                    if (data.success) {
                        if (data.warning) {
                            // Show warning but continue with save
                            await showNotification(data.warning, 'warning');
                            await new Promise(resolve => setTimeout(resolve, 2000));
                        }
                        await showNotification(data.message || 'Successfully added', 'success');
                        closeModals();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        await showNotification(data.message || 'Error occurred', 'error');
                    }
                } catch (error) {
                    console.error('Form submission error:', error);
                    await showNotification('An error occurred while saving', 'error');
                } finally {
                    submitButton.disabled = false;
                }
            });
        });
        // Update the connection form submission handler
        document.getElementById('connectionForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            if (!validateFees()) {
                return;
            }

            const formData = new FormData(this);
            
            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    await showNotification('Connection added successfully', 'success');
                    closeModals();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Error adding connection', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred while saving', 'error');
            }
        });

        // Filter functions
        function resetFilters() {
            const form = document.getElementById('filterForm');
            // Clear all inputs except page related ones
            form.querySelectorAll('input, select').forEach(element => {
                if (!['sort_by', 'sort'].includes(element.name)) {
                    if (element.type === 'text') {
                        element.value = '';
                    } else if (element.tagName === 'SELECT') {
                        element.selectedIndex = 0;
                    }
                }
            });
            // Submit the form
            form.submit();
        }

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const notificationMessage = document.getElementById('notification-message');
            
            notification.classList.remove('hidden');
            notificationMessage.textContent = message;
            
            if (type === 'error') {
                notification.querySelector('div').classList.remove('bg-green-500');
                notification.querySelector('div').classList.add('bg-red-500');
            } else {
                notification.querySelector('div').classList.remove('bg-red-500');
                notification.querySelector('div').classList.add('bg-green-500');
            }
            
            setTimeout(() => {
                notification.classList.add('hidden');
            }, 3000);
        }

        function updateQueryStringParameter(key, value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set(key, value);
            return '?' + urlParams.toString();
        }

        function viewDetails(primaDataId, bankName) {
            // Show loading state
            const detailsTitle = document.getElementById('detailsTitle');
            const detailsContent = document.getElementById('detailsContent');
            detailsContent.innerHTML = '<div class="text-center py-4">Loading...</div>';
            document.getElementById('detailsModal').classList.remove('hidden');

            fetch(`get_channel_details.php?prima_data_id=${primaDataId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        detailsTitle.textContent = `Connection Details - ${bankName}`;
                        
                        // Create fee information section
                        let feeHtml = `
                            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                <h3 class="font-medium text-lg mb-3">Fee Information</h3>
                                <div class="grid grid-cols-2 gap-y-2">
                                    <div class="text-gray-600">Fee Status:</div>
                                    <div class="text-right font-medium ${data.fees.fee_included === 'Include' ? 'text-green-600' : 'text-red-600'}">
                                        ${data.fees.fee_included}
                                    </div>
                                    
                                    <div class="text-gray-600">Fee Bank:</div>
                                    <div class="text-right font-mono">
                                        ${data.fees.fee_bank}
                                    </div>
                                    
                                    <div class="text-gray-600">Fee Biller:</div>
                                    <div class="text-right font-mono">
                                        ${data.fees.fee_biller}
                                    </div>
                                    
                                    <div class="text-gray-600">Fee Rintis:</div>
                                    <div class="text-right font-mono">
                                        ${data.fees.fee_rintis}
                                    </div>

                                    <div class="text-gray-600">Admin Fee:</div>
                                    <div class="text-right font-mono">
                                        ${data.fees.admin_fee}
                                    </div>
                                    
                                    <div class="text-gray-800 font-medium pt-2 border-t">Total Fee:</div>
                                    <div class="text-right font-medium font-mono pt-2 border-t">
                                        ${data.fees.total_fee}
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        // Create channels section
                        let channelsHtml = `
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="font-medium text-lg mb-3">Channels</h3>
                                <div class="text-sm text-gray-500 mb-4">
                                    <span class="font-medium text-gray-700">Active Channels:</span> ${data.channels.length}
                                </div>
                                <div class="space-y-3">
                        `;
                        
                        if (data.channels.length > 0) {
                            data.channels.forEach(channel => {
                                channelsHtml += `
                                    <div class="bg-white p-3 rounded-md shadow-sm border border-gray-100 hover:border-blue-200 transition-colors">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                                                <span class="font-medium text-gray-700">${channel.name}</span>
                                            </div>
                                            <div class="text-sm">
                                                <span class="text-gray-400">Live since:</span>
                                                <span class="text-gray-600 ml-1">${channel.date_live}</span>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                        } else {
                            channelsHtml += `
                                <div class="text-center text-gray-500 py-4">
                                    No channels configured
                                </div>
                            `;
                        }
                        
                        channelsHtml += `</div></div>`;
                        
                        // Combine both sections
                        detailsContent.innerHTML = feeHtml + channelsHtml;
                    } else {
                        detailsContent.innerHTML = `
                            <div class="bg-red-50 p-4 rounded-lg">
                                <p class="text-red-500 text-center">${data.message || 'Error loading details'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    detailsContent.innerHTML = `
                        <div class="bg-red-50 p-4 rounded-lg">
                            <p class="text-red-500 text-center">Error loading details: ${error.message}</p>
                        </div>
                    `;
                });
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }

        function addChannel() {
            const container = document.getElementById('channelContainer');
            const template = container.children[0].cloneNode(true);
            
            // Clear the values
            template.querySelector('select').value = '';
            template.querySelector('input[type="date"]').value = '';
            
            container.appendChild(template);
        }

        function removeChannel(button) {
            const container = document.getElementById('channelContainer');
            if (container.children.length > 1) {
                button.closest('.channel-entry').remove();
            }
        }

        function exportToExcel() {
            const currentUrl = new URL(window.location.href);
            const searchParams = currentUrl.searchParams;
            
            // Get all current filter values
            const filterParams = new URLSearchParams();
            if (searchParams.has('search')) filterParams.append('search', searchParams.get('search'));
            if (searchParams.has('bank_filter')) filterParams.append('bank_filter', searchParams.get('bank_filter'));
            if (searchParams.has('biller_filter')) filterParams.append('biller_filter', searchParams.get('biller_filter'));
            if (searchParams.has('spec_filter')) filterParams.append('spec_filter', searchParams.get('spec_filter'));
            if (searchParams.has('status_filter')) filterParams.append('status_filter', searchParams.get('status_filter'));
            
            // Redirect to export with current filters
            window.location.href = `export_excel.php?${filterParams.toString()}`;
        }   
        // Add this inside your existing <script> tags
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent immediate form submission
            
            // Show loading state
            const buttonText = document.getElementById('filterButtonText');
            const spinner = document.getElementById('filterSpinner');
            const button = document.getElementById('filterButton');
            
            // Disable button and show spinner
            button.disabled = true;
            buttonText.textContent = 'Filtering...';
            spinner.classList.remove('hidden');
            
            // Wait for 1.5 seconds before submitting the form
            setTimeout(() => {
                this.submit(); // Submit the form after delay
            }, 1500); // 1500ms = 1.5 seconds
        });

        // Add this to your existing script section
        function calculateTotalFee() {
            const feeInclusion = document.querySelector('input[name="fee_inclusion"]:checked').value;
            const feeBank = parseFloat(document.getElementById('fee_bank').value) || 0;
            const feeBiller = parseFloat(document.getElementById('fee_biller').value) || 0;
            const feeRintis = parseFloat(document.getElementById('fee_rintis').value) || 0;
            const adminFeeInput = document.getElementById('admin_fee');
            
            // Calculate subtotal (sum of bank, biller, and rintis fees)
            const subTotal = feeBank + feeBiller + feeRintis;
            
            if (feeInclusion === 'exclude') {
                // Show admin fee section for manual input
                document.getElementById('adminFeeSection').style.display = 'block';
                // Use the manually entered admin fee value
                const adminFee = parseFloat(adminFeeInput.value) || 0;
                // Update total with manually entered admin fee
                const totalFees = subTotal + adminFee;
                document.getElementById('total_fee').textContent = totalFees.toFixed(2);
            } else {
                // When included, hide admin fee section and set to 0
                document.getElementById('adminFeeSection').style.display = 'none';
                adminFeeInput.value = '0.00';
                document.getElementById('total_fee').textContent = subTotal.toFixed(2);
            }
        }

        // Add event listeners for all fee inputs including admin fee
        ['fee_bank', 'fee_biller', 'fee_rintis', 'admin_fee'].forEach(id => {
            document.getElementById(id).addEventListener('input', calculateTotalFee);
        });

        // Update fee inclusion radio handler
        document.querySelectorAll('input[name="fee_inclusion"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const adminFeeSection = document.getElementById('adminFeeSection');
                if (this.value === 'exclude') {
                    adminFeeSection.style.display = 'block';
                } else {
                    adminFeeSection.style.display = 'none';
                }
                calculateTotalFee(); // Recalculate fees when inclusion type changes
            });
        });

        // Add this to your form validation in submitForm function
        function validateFees() {
            const feeInclusion = document.querySelector('input[name="fee_inclusion"]:checked').value;
            const feeBank = parseFloat(document.getElementById('fee_bank').value) || 0;
            const feeBiller = parseFloat(document.getElementById('fee_biller').value) || 0;
            const feeRintis = parseFloat(document.getElementById('fee_rintis').value) || 0;
            const adminFee = parseFloat(document.getElementById('admin_fee').value) || 0;
            
            // Check for negative values
            if (feeBank < 0 || feeBiller < 0 || feeRintis < 0 || adminFee < 0) {
                showNotification('Fee amounts cannot be negative', 'error');
                return false;
            }

            const subTotal = feeBank + feeBiller + feeRintis;

            if (feeInclusion === 'exclude' && adminFee <= 0) {
                showNotification('Admin fee is required when fees are excluded', 'error');
                return false;
            }
            return true;
        }

        // Add to your existing script section
        function handleExportClick() {
            const button = document.getElementById('exportButton');
            const buttonText = document.getElementById('exportButtonText');
            const spinner = document.getElementById('exportSpinner');
            
            // Show animation
            button.disabled = true;
            buttonText.textContent = 'Exporting...';
            spinner.classList.remove('hidden');
            
            // Wait for animation before starting export
            setTimeout(() => {
                try {
                    // Trigger the export
                    exportToExcel();
                    
                    // Reset button state after a delay
                    setTimeout(() => {
                        button.disabled = false;
                        buttonText.innerHTML = `
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Export to Excel
                        `;
                        spinner.classList.add('hidden');
                    }, 1500);
                    
                } catch (error) {
                    console.error('Export error:', error);
                    alert('Error exporting data: ' + error.message);
                    
                    // Reset button state
                    button.disabled = false;
                    buttonText.innerHTML = `
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Export to Excel
                    `;
                    spinner.classList.add('hidden');
                }
            }, 1000);
        }
    </script>
</body>
</html>