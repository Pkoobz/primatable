<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    redirect('./public/login.php');
}

$database = new Database();
$pdo = $database->getConnection();

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $errors = [];
    
    // Verify current password
    if (!empty($current_password)) {
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        } elseif (!empty($new_password)) {
            // Update password
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$password_hash, $_SESSION['user_id']]);
        }
    }
    
    if (empty($errors)) {
        // Update profile
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        if ($stmt->execute([$username, $email, $_SESSION['user_id']])) {
            $_SESSION['username'] = $username;
            $success_message = "Profile updated successfully";
        } else {
            $errors[] = "Failed to update profile";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Rintis</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body style="background: url('assets/images/p.gif') center center fixed; 
             background-size: cover; 
             background-repeat: no-repeat;">
    <?php include '../includes/nav.php'; ?>

    <div class="max-w-3xl mx-auto py-6 sm:px-6 lg:px-8">  <!-- Changed max-w-7xl to max-w-3xl -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg max-w-2xl mx-auto">  <!-- Added max-w-2xl and mx-auto -->
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 text-center">Profile Settings</h3>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mx-6 mb-4">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mx-6 mb-4">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <div class="border-t border-gray-200">
                <form method="POST" class="space-y-6 p-8">  <!-- Increased padding from p-6 to p-8 -->
                    <div class="grid grid-cols-1 gap-6 max-w-md mx-auto">  <!-- Added max-w-md and mx-auto -->
                        <div class="text-center">  <!-- Added text-center -->
                            <label class="block text-sm font-medium text-gray-700">Profile Picture</label>
                            <div class="mt-2 flex justify-center">  <!-- Added justify-center -->
                                <img class="h-24 w-24 rounded-full border-4 border"
                                     src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['username']); ?>&size=80&background=random" 
                                     alt="">
                            </div>
                        </div>

                        <div class="text-center">  <!-- Added text-center -->
                            <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                            <input type="text" 
                                   name="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center">
                        </div>

                        <div class="text-center">  <!-- Added text-center -->
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center">
                        </div>

                        <div class="text-center">  <!-- Added text-center -->
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                            <input type="password" 
                                   name="current_password" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center">
                        </div>

                        <div class="text-center">  <!-- Added text-center -->
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                            <input type="password" 
                                   name="new_password" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center">
                        </div>
                    </div>

                    <div class="flex justify-center mt-6">  <!-- Changed to justify-center -->
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdown-menu');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        window.addEventListener('click', function(e) {
            if (!e.target.closest('#user-menu-button')) {
                document.getElementById('dropdown-menu').classList.add('hidden');
            }
        });
    </script>
</body>
</html>