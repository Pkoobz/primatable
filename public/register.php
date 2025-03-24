<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signup'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Username or email already exists";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        if ($stmt->execute([$username, $email, $hashed_password])) {
            $_SESSION['success'] = "Registration successful! Please sign in.";
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rintis - Sign Up</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
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
        <div class="hidden lg:block relative overflow-hidden bg-black">
            <div class="flex flex-col justify-center items-center text-white p-12 h-full space-y-6">
                <h1 class="text-4xl font-bold text-white drop-shadow-lg">𝐖𝐞𝐥𝐜𝐨𝐦𝐞 𝐁𝐚𝐜𝐤</h1>
                <div class="w-48 h-48 rounded-full overflow-hidden border-4 border-white shadow-lg">
                    <img src="./assets/images/logo_rintis.png" 
                         alt="Welcome Image" 
                         class="w-full h-full object-cover">
                </div>
                <p class="text-lg text-center text-white drop-shadow-lg">Manage you connection and billing with ease</p>
            </div>
        </div>


        <!-- Right side with registration form -->
        <div class="flex items-center justify-center p-8" 
             style="background-image: url('https://wallpapercave.com/wp/wp3616922.jpg'); background-size: cover;">
            <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
                <div class="text-center space-y-4 mb-8">    
                    <h2 class="text-2xl font-bold text-gray-800">Create your account</h2>
                    <p class="text-gray-600">Enter your details to continue</p>
                </div>

                <form method="POST" action="">
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
                               id="username" name="username" type="text" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                            Email
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               id="email" name="email" type="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                            Password
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               id="password" name="password" type="password" required>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">
                            Confirm Password
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               id="confirm_password" name="confirm_password" type="password" required>
                    </div>
                    
                    <div class="space-y-4">
                        <button class="bg-gradient-to-b from-blue-600 to-purple-800 hover:from-purple-900 hover:to-blue-600 text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline w-full button-push"
                                type="submit" name="signup">
                            Sign Up      
                        </button>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <a href="login.php" class="text-blue-500 hover:text-blue-600 text-sm">
                            Already have an account? Sign In
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const signUpBtn = document.querySelector('button[name="signup"]');
            
            function addButtonAnimation(element) {
                element.addEventListener('click', function(e) {
                    this.classList.add('button-push');
                    setTimeout(() => {
                        this.classList.remove('button-push');
                    }, 300);
                });
            }

            addButtonAnimation(signUpBtn);
        });
    </script>
</body>
</html>
