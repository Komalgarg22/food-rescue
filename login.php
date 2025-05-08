<?php
session_start();

require_once 'includes/db.php'; // Ensure this uses MySQLi OOP
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$page_title = "Login";
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(sanitizeInput($_POST['email']));
    $password = $_POST['password'];

    // Prepare statement using MySQLi OOP
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];

            // Optional: redirect by role
            if ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit();
        } else {
            $error = 'Invalid email or password';
        }

        $stmt->close();
    } else {
        $error = 'Database error. Please try again later.';
    }
}

include 'includes/header.php';
?>

<div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold mb-6 text-center">Login</h2>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div class="mb-4">
            <label for="email" class="block text-gray-700 mb-2">Email</label>
            <input type="email" id="email" name="email" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
        </div>

        <div class="mb-4">
            <label for="password" class="block text-gray-700 mb-2">Password</label>
            <div class="relative">
                <input type="password" id="password" name="password" required minlength="6"
                    class="w-full px-3 py-2 border rounded-lg pr-12 focus:outline-none focus:ring-2 focus:ring-green-600">
                <button type="button" onclick="togglePassword('password', 'icon1')" 
                    class="absolute right-2 top-1/2 transform -translate-y-1/2 text-xl text-gray-600 focus:outline-none" 
                    id="icon1">👁</button>
            </div>
        </div>

        <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition">
            Login
        </button>
    </form>

    <p class="mt-4 text-center">
        Don't have an account? <a href="register.php" class="text-green-600 hover:underline">Register here</a>
    </p>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    
    if (input.type === "password") {
        input.type = "text";
        icon.textContent = "🙈";
    } else {
        input.type = "password";
        icon.textContent = "👁";
    }
}
</script>
