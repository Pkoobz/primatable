<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signin'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $errors = [];
    
    if (empty($username) || empty($password)) {
        $errors[] = "Both username and password are required";
    } else {
        // Check user credentials
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if user exists and verify password
        if ($user && password_verify($password, $user['password'])) {
            $session_token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) 
                                VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $session_token, $expires_at]);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['session_token'] = $session_token;

            log_activity($pdo, 'login', 'users', $user['id'], null, [
                'username' => $user['username']
            ], 'User logged in successfully');
            redirect('./public/index.php'); // Fixed redirect path
        } else {
            $errors[] = "Invalid username or password";
        }
    }
}

// Check for registration success message
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Primacom - Sign In</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        @keyframes buttonPush {
            0% { transform: scale(1); }
            50% { transform: scale(0.95); }
            100% { transform: scale(1); }
        }

        .button-push {
            animation: buttonPush 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen grid grid-cols-1 lg:grid-cols-2">
        <!-- Left side with animated background -->
        <div class="hidden lg:block relative overflow-hidden">
            <div class="absolute inset-0">
                <img src="https://media.giphy.com/media/teXdkckBJvbLW/giphy.gif" 
                     alt="Background Animation" 
                     class="w-full h-full object-cover">
            </div>
            <div class="relative flex flex-col justify-center items-center text-white p-12 h-full space-y-6 z-10">
                <h1 class="text-4xl font-bold text-white drop-shadow-lg">𝐖𝐞𝐥𝐜𝐨𝐦𝐞 𝐁𝐚𝐜𝐤</h1>
                <div class="w-48 h-48 rounded-full overflow-hidden border-4 border-white shadow-lg">
                    <img src="https://th.bing.com/th/id/OIP.oU2fh5ahpbiPxz7TUWulxAHaHa?rs=1&pid=ImgDetMain" 
                         alt="Welcome Image" 
                         class="w-full h-full object-cover">
                </div>
                <p class="text-lg text-center text-white drop-shadow-lg">Manage you connection and billing with ease</p>
            </div>
        </div>

        <!-- Right side with login form --> 
        <div class="flex items-center justify-center p-8"style="background-image: url('https://wallpapercave.com/wp/wp3616922.jpg'); background-size: cover;">
            <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
                <div class="text-center space-y-4 mb-8">    
                    <h2 class="text-2xl font-bold">Sign in to your account</h2>
                    <p class="text-gray-600">Enter your credentials to continue</p>
                </div>

                <form method="POST" action="">
                    <?php if (!empty($success_message)): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="username">
                            Username
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               id="username" name="username" type="text" autocomplete="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                            Password
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline"
                               id="password" name="password" type="password" autocomplete="current-password" required>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Sign In Button -->
                        <button class="bg-gradient-to-b from-blue-600 to-purple-800 hover:from-purple-900 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline w-full button-push"
                                type="submit" name="signin">
                            Sign In
                        </button>
                    </div>
                    
                    <div class="mt-4">
                        <!-- Create Account Button -->
                        <a href="register.php" 
                           class="bg-gray-100 hover:bg-gray-600 text-gray-700 font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline w-full block text-center button-push">
                            Create new account
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const signInBtn = document.querySelector('button[name="signin"]');
            const createAccountBtn = document.querySelector('a[href="register.php"]');

            function addButtonAnimation(element) {
                element.addEventListener('click', function(e) {
                    this.classList.add('button-push');
                    setTimeout(() => {
                        this.classList.remove('button-push');
                    }, 300);
                });
            }

            addButtonAnimation(signInBtn);
            addButtonAnimation(createAccountBtn);
        });
    </script>
</body>
</html>
