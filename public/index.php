<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
include '../includes/nav.php';

if (!is_logged_in()) {
    redirect('login.php');
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
    'date_live' => 'pd.date_live',
    'status' => 'pd.status'
];

// Get the sort column
$sort_column = $sort_columns[$sort_by] ?? 'b.name';

// Verify user exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    redirect('login.php');
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
        bl.name as biller_name,
        bs.name as bank_spec_name,
        bls.name as biller_spec_name,
        u.username as created_by_user,
        pd.status,
        pd.date_live
        FROM prima_data pd
        LEFT JOIN banks b ON pd.bank_id = b.id
        LEFT JOIN billers bl ON pd.biller_id = bl.id
        LEFT JOIN specs bs ON pd.bank_spec_id = bs.id
        LEFT JOIN specs bls ON pd.biller_spec_id = bls.id
        LEFT JOIN users u ON pd.created_by = u.id
        WHERE 1=1";

// Apply filters
if ($search) {
    $sql .= " AND (b.name LIKE :search OR bl.name LIKE :search OR bs.name LIKE :search OR bls.name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($bank_filter) {
    $sql .= " AND b.id = :bank_id";
    $params[':bank_id'] = $bank_filter;
}

if ($biller_filter) {
    $sql .= " AND bl.id = :biller_id";
    $params[':biller_id'] = $biller_filter;
}

if ($spec_filter) {
    $sql .= " AND (pd.bank_spec_id = :spec_id OR pd.biller_spec_id = :spec_id)";
    $params[':spec_id'] = $spec_filter;
}

if ($status_filter) {
    $sql .= " AND pd.status = :status";
    $params[':status'] = $status_filter;
}

// Apply sorting
$sql .= " ORDER BY " . $sort_column . " " . $sort;

// Execute main query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@2.8.2/dist/alpine.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <?php if ($is_admin): ?>
            <div class="mb-6" x-data="{ showBankModal: false, showBillerModal: false, showSpecModal: false, showConnectionModal: false }">
                <button @click="showBankModal = true" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2">
                    Add Bank
                </button>
                <button @click="showBillerModal = true" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2">
                    Add Biller
                </button>
                <button @click="showSpecModal = true" class="bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded inline-flex items-center mr-2">
                    Add Spec
                </button>
                <button @click="showConnectionModal = true" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    Add Connection
                </button>

                <!-- Bank Modal -->
                <div x-show="showBankModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" @click="showBankModal = false">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form action="add_bank_ajax.php" method="POST" class="p-6">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Add New Bank</h3>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="bank_name">Bank Name</label>
                                    <input type="text" name="bank_name" id="bank_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                <div class="flex justify-end">
                                    <button type="button" @click="showBankModal = false" class="mr-2 px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-700">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- Biller Modal -->
                <div x-show="showBillerModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" @click="showBillerModal = false">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form action="add_biller_ajax.php" method="POST" class="p-6">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Add New Biller</h3>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="biller_name">Biller Name</label>
                                    <input type="text" name="biller_name" id="biller_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                <div class="flex justify-end">
                                    <button type="button" @click="showBillerModal = false" class="mr-2 px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-700">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Spec Modal -->
                <div x-show="showSpecModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" @click="showSpecModal = false">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form action="add_spec_ajax.php" method="POST" class="p-6">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Add New Spec</h3>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="spec_name">Spec Name</label>
                                    <input type="text" name="spec_name" id="spec_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                <div class="flex justify-end">
                                    <button type="button" @click="showSpecModal = false" class="mr-2 px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-700">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Connection Modal -->
                <div x-show="showConnectionModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" @click="showConnectionModal = false">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>
                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <form action="add_connection_ajax.php" method="POST" class="p-6">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Add New Connection</h3>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="bank_id">Bank</label>
                                    <select name="bank_id" id="bank_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                        <option value="">Select Bank</option>
                                        <?php foreach ($banks as $bank): ?>
                                            <option value="<?php echo $bank['id']; ?>">
                                                <?php echo htmlspecialchars($bank['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="biller_id">Biller</label>
                                    <select name="biller_id" id="biller_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                        <option value="">Select Biller</option>
                                        <?php foreach ($billers as $biller): ?>
                                            <option value="<?php echo $biller['id']; ?>">
                                                <?php echo htmlspecialchars($biller['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Replace the spec selection part in the Connection Modal -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="bank_spec_id">Bank Spec</label>
                                        <select name="bank_spec_id" id="bank_spec_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                            <option value="">Select Bank Spec</option>
                                            <?php 
                                            $sql = "SELECT * FROM specs ORDER BY name " . ($sort_by == 'spec' ? $sort : 'ASC');
                                            $stmt = $pdo->query($sql);
                                            $specs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($specs as $spec): 
                                            ?>
                                            <option value="<?php echo $spec['id']; ?>">
                                                <?php echo htmlspecialchars($spec['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="biller_spec_id">Biller Spec</label>
                                        <select name="biller_spec_id" id="biller_spec_id" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                            <option value="">Select Biller Spec</option>
                                            <?php foreach ($specs as $spec): ?>
                                            <option value="<?php echo $spec['id']; ?>">
                                                <?php echo htmlspecialchars($spec['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="date_live">Date Live</label>
                                    <input type="date" name="date_live" id="date_live" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="status">Status</label>
                                    <select name="status" id="status" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="flex justify-end">
                                    <button type="button" @click="showConnectionModal = false" class="mr-2 px-4 py-2 text-gray-500 hover:text-gray-700">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-700">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
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
                        <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Apply Filters
                        </button>
                    </div>

                    <!-- Hidden sort fields -->
                    <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                </form>
            </div>
            <!-- DataTable -->
            <div class="bg-white shadow-md rounded my-6">
                <table class="min-w-max w-full table-auto">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-6 text-left">
                                <a href="?sort_by=bank&sort=<?php echo $sort_by == 'bank' && $sort == 'asc' ? 'desc' : 'asc'; ?>" class="flex items-center">
                                    Bank
                                    <?php if ($sort_by == 'bank'): ?>
                                        <span class="ml-1"><?php echo $sort == 'asc' ? '↑' : '↓'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="py-3 px-6 text-left">
                                <a href="?sort_by=biller&sort=<?php echo $sort_by == 'biller' && $sort == 'asc' ? 'desc' : 'asc'; ?>" class="flex items-center">
                                    Biller
                                    <?php if ($sort_by == 'biller'): ?>
                                        <span class="ml-1"><?php echo $sort == 'asc' ? '↑' : '↓'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="py-3 px-6 text-left">
                                <a href="?sort_by=spec&sort=<?php echo $sort_by == 'spec' && $sort == 'asc' ? 'desc' : 'asc'; ?>" class="flex items-center">
                                    Spec
                                    <?php if ($sort_by == 'spec'): ?>
                                        <span class="ml-1"><?php echo $sort == 'asc' ? '↑' : '↓'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="py-3 px-6 text-center">Date Live</th>
                            <th class="py-3 px-6 text-center">Status</th>
                            <?php if ($is_admin): ?>
                            <th class="py-3 px-6 text-center">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm font-light">
                        <?php if (!empty($result)): ?>
                            <?php foreach ($result as $row): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-100">
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($row['bank_name']); ?></td>
                                    <td class="py-3 px-6 text-left"><?php echo htmlspecialchars($row['biller_name']); ?></td>
                                    <td class="py-3 px-6 text-left">
                                        Bank: <?php echo htmlspecialchars($row['bank_spec_name']); ?><br>
                                        Biller: <?php echo htmlspecialchars($row['biller_spec_name']); ?>
                                    </td>
                                    <td class="py-3 px-6 text-center"><?php echo htmlspecialchars($row['date_live']); ?></td>
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
            </div>  
    </div>
    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdown-menu');
            if (dropdown) {
                dropdown.classList.toggle('hidden');
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#user-menu-button') && !e.target.closest('#dropdown-menu')) {
                    const dropdown = document.getElementById('dropdown-menu');
                    if (dropdown && !dropdown.classList.contains('hidden')) {
                        dropdown.classList.add('hidden');
                    }
                }
            });
        }
        function submitForm(formElement) {
        // Check if this is the filter form
            if (formElement.id === 'filterForm') {
                // Allow normal form submission for filters
                return true;
            }

            // For modal forms (add bank, biller, spec, connection)
            const formData = new FormData(formElement);
            
            fetch(formElement.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the data');
            });

            return false;
        }

        // Initialize form handlers
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!submitForm(this)) {
                    e.preventDefault();
                }
            });
        });

        function resetFilters() {
            document.getElementById('search').value = '';
            document.getElementById('bank_filter').value = '';
            document.getElementById('biller_filter').value = '';
            document.getElementById('spec_filter').value = '';
            document.getElementById('status_filter').value = '';
            document.getElementById('filterForm').submit();
        }

        // Preserve filters when sorting
        document.querySelectorAll('th a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const currentUrl = new URL(this.href);
                const searchParams = new URLSearchParams(window.location.search);
                
                // Preserve all current filter values
                ['search', 'bank_filter', 'biller_filter', 'spec_filter', 'status_filter'].forEach(param => {
                    if (searchParams.has(param)) {
                        currentUrl.searchParams.set(param, searchParams.get(param));
                    }
                });
                
                window.location.href = currentUrl.toString();
            });
        });
    </script>
</body>
</html>