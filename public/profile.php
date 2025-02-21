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
    <title>Profile - Primacom</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            background-image: url('https://wallpaperaccess.com/full/340434.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
        }
        
    </style>
</head>
<body style="background-image: url('https://wallpaperaccess.com/full/340434.png'); background-size: cover; background-position: center;">
    <?php include '../includes/nav.php'; ?>
        <div class="max-w-3xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
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
                <form method="POST" class="space-y-6 p-6">
                    <div class="flex flex-col items-center gap-6 max-w-md mx-auto">

                        <div class="text-center">
                            <label class="font-medium text-gray-500">Profile Picture</label>
                            <div class="mt-2">
                                <img class="h-20 w-20 rounded-full mx-auto" 
                                     src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['username']); ?>&size=80&background=random" 
                                     alt="">
                            </div>
                        </div>

                        <div class="w-full">
                            <label class="block text-sm font-medium text-gray-700 text-center">Username</label>
                            <input type="text" 
                                   name="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-center">

                        </div>

                        <div class="w-full">
                            <label class="block text-sm font-medium text-gray-700 text-center">Email</label>
                            <input type="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   class="mt-1 block w-full rounded-md border-gray-300 text-center">

                        </div>

                        <div class="w-full">
                            <label class="block text-sm font-medium text-gray-700 text-center">Current Password</label>
                            <input type="password" 
                                   name="current_password" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div class="w-full">
                            <label class="block text-sm font-medium text-gray-700 text-center">New Password</label>
                            <input type="password" 
                                   name="new_password" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                    </div>

                    <div class="flex justify-center">
                        <button type="submit" 
                                id="saveButton"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transform transition duration-200 ease-in-out hover:scale-105 active:scale-95">
                            <span class="inline-flex items-center">
                                <span id="buttonText">Save Changes</span>
                                <svg id="checkmark" class="hidden w-5 h-5 ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const container = document.getElementById('profileContainer');
            const button = document.getElementById('saveButton');
            const buttonText = document.getElementById('buttonText');
            const checkmark = document.getElementById('checkmark');
            const form = this;

            // Show loading state
            buttonText.textContent = 'Saving...';
            button.classList.add('opacity-75', 'cursor-not-allowed');
            container.classList.add('animate-save');
            
            // Submit form data using fetch
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form)
            })
            .then(response => response.text())
            .then(data => {
                // Show success state
                buttonText.textContent = 'Saved!';
                checkmark.classList.remove('hidden');
                
                // Reset after animation
                setTimeout(() => {
                    container.classList.remove('animate-save');
                    buttonText.textContent = 'Save Changes';
                    checkmark.classList.add('hidden');
                    button.classList.remove('opacity-75', 'cursor-not-allowed');
                    location.reload();
                }, 2000);
            })
            .catch(error => {
                container.classList.remove('animate-save');
                button.classList.remove('opacity-75', 'cursor-not-allowed');
                buttonText.textContent = 'Save Changes';
                console.error('Error:', error);
            });
        });
    </script>
</body>
</html>
