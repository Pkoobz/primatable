<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    redirect('login.php');
}

$user = get_user_by_id($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex justify-between">
                <div class="flex space-x-7">
                    <div class="flex items-center py-4">
                        <img src="assets/images/primacom-logo.png" alt="Logo" class="h-8 w-auto">
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="text-gray-700">Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
                    <a href="logout.php" class="py-2 px-4 bg-red-500 hover:bg-red-600 text-white rounded">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-4">Dashboard</h1>
        <!-- Add your dashboard content here -->
    </div>
</body>
</html>