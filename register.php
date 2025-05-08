<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$page_title = "Register";
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);

    // Validate inputs
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match';

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) $errors[] = 'Email already registered';
    $stmt->close();

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $default_profile_picture = 'logo.jpeg';

        $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, address, profile_picture, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssss", $name, $email, $hashed_password, $phone, $address, $default_profile_picture);

        if ($stmt->execute()) {
            // ✅ Auto login after registration
            $_SESSION['user_id'] = $stmt->insert_id;
            $_SESSION['user_role'] = 'user'; // default role
            header('Location: index.php');
            exit();
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }

        $stmt->close();
    }
}

include 'includes/header.php';
?>

<div class="max-w-md mx-auto bg-white p-8 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold mb-6 text-center">Register</h2>
    
    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form action="register.php" method="POST">
        <div class="mb-4">
            <label for="name" class="block text-gray-700 mb-2">Full Name</label>
            <input type="text" id="name" name="name" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
        </div>
        
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
        
        <div class="mb-4">
            <label for="confirm_password" class="block text-gray-700 mb-2">Confirm Password</label>
            <div class="relative">
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                    class="w-full px-3 py-2 border rounded-lg pr-12 focus:outline-none focus:ring-2 focus:ring-green-600">
                <button type="button" onclick="togglePassword('confirm_password', 'icon2')" 
                    class="absolute right-2 top-1/2 transform -translate-y-1/2 text-xl text-gray-600 focus:outline-none" 
                    id="icon2">👁</button>
            </div>
        </div>
        
        <div class="mb-4">
            <label for="phone" class="block text-gray-700 mb-2">Phone Number</label>
            <input type="tel" id="phone" name="phone" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
        </div>
        
        <div class="mb-6">
            <label for="address" class="block text-gray-700 mb-2">Address</label>
            <textarea id="address" name="address" rows="3" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"></textarea>
        </div>
        
        <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition">
            Register
        </button>
    </form>
    
    <p class="mt-4 text-center">
        Already have an account? <a href="login.php" class="text-green-600 hover:underline">Login here</a>
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
