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
    // admin pass = Admin@123
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
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
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
</head>
<body class="bg-gray-100">
    <div class="min-h-screen grid grid-cols-1 lg:grid-cols-2">
        <!-- Left side with solid color background -->
        <div class="hidden lg:block bg-blue-600 rounded-r-3xl">
            <div class="flex flex-col justify-center items-center text-white p-12 h-full space-y-6">
                <h1 class="text-4xl font-bold">Welcome Back</h1>
                <div class="w-48 h-48 rounded-full overflow-hidden border-4 border-white">
                    <img src="https://th.bing.com/th/id/OIP.oU2fh5ahpbiPxz7TUWulxAHaHa?rs=1&pid=ImgDetMain" alt="Welcome Image" class="w-full h-full object-cover">
                </div>
                <p class="text-lg text-center">Manage your connections and billing with ease</p>
            </div>
        </div>

        <!-- Right side with login form --> 
        <div class="flex items-center justify-center p-8">
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
                        <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline w-full"
                                type="submit" name="signin">
                            Sign In
                        </button>
                    </div>
                    
                    <div class="mt-4">
                        <a href="register.php" 
                        class="bg-gray-100 hover:bg-gray-600 text-gray-700 font-bold py-3 px-6 rounded-lg focus:outline-none focus:shadow-outline w-full block text-center">
                            Create new account
                        </a>
                    </div>

                    <div class="mt-6 text-center">
                        <a href="#" class="text-blue-600 hover:text-blue-700 text-sm">Forgot password?</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
