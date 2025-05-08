<?php
require_once 'includes/auth.php';
require_once 'includes/db.php'; // defines $conn (MySQLi)
require_once 'includes/functions.php';

$page_title = "Add Food";
$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $category = sanitizeInput($_POST['category']);
    $price = sanitizeInput($_POST['price']);
    $quantity = sanitizeInput($_POST['quantity']);
    $expiration_time = sanitizeInput($_POST['expiration_time']);
    $user_id = $_SESSION['user_id'];
    $image = "";

    // Validations
    if (empty($title)) $errors[] = 'Title is required.';
    if (empty($description)) $errors[] = 'Description is required.';
    if (empty($category)) $errors[] = 'Category is required.';
    if (!is_numeric($price) || $price < 0) $errors[] = 'Price must be a positive number.';
    if (empty($expiration_time)) $errors[] = 'Expiration time is required.';

    // Handle image upload
    $image = null; // Initialize as null
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = uploadImage($_FILES['image'], 'uploads/food/');
        if ($upload['success']) {
            $image = $upload['filename'];
        } else {
            $errors[] = $upload['message'];
        }
    }

    if (empty($errors)) {
        // Prepare the SQL statement based on whether we have an image
        if ($image) {
            $stmt = $conn->prepare("INSERT INTO food_items 
                (title, description, category, price, quantity, image, expiration_time, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdissi", 
                $title, $description, $category, $price, $quantity, $image, $expiration_time, $user_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO food_items 
                (title, description, category, price, quantity, expiration_time, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdsi", 
                $title, $description, $category, $price, $quantity, $expiration_time, $user_id);
        }

        if ($stmt->execute()) {
            $success = "Food item added successfully!";
            $_POST = []; // Clear form
        } else {
            $errors[] = "Database error: " . $conn->error;
            
            // If we had an image, try to delete it since the DB insert failed
            if ($image && file_exists('uploads/food/' . $image)) {
                unlink('uploads/food/' . $image);
            }
        }
        $stmt->close();
    }
}

include 'includes/header.php';
?>

<div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold mb-6">Add New Food</h2>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 text-red-700 px-4 py-3 rounded mb-4">
            <ul><?php foreach ($errors as $error) echo "<li>$error</li>"; ?></ul>
        </div>
    <?php elseif (!empty($success)): ?>
        <div class="bg-green-100 text-green-700 px-4 py-3 rounded mb-4"><?php echo $success; ?></div>
    <?php endif; ?>

    <form action="add_food.php" method="POST" enctype="multipart/form-data">
        <div class="mb-4">
            <label class="block text-gray-700">Title*</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required class="w-full px-3 py-2 border rounded-lg">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700">Description*</label>
            <textarea name="description" required class="w-full px-3 py-2 border rounded-lg"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700">Category*</label>
            <select name="category" required class="w-full px-3 py-2 border rounded-lg">
                <option value="">Select a category</option>
                <?php
                $categories = ['Fruits', 'Vegetables', 'Dairy', 'Bakery', 'Meat', 'Prepared Meals', 'Grains', 'Other'];
                foreach ($categories as $cat) {
                    $selected = (($_POST['category'] ?? '') === $cat) ? 'selected' : '';
                    echo "<option value=\"$cat\" $selected>$cat</option>";
                }
                ?>
            </select>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700">Price ($)*</label>
            <input type="number" step="0.01" min="0" name="price" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" required class="w-full px-3 py-2 border rounded-lg">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700">Quantity</label>
            <input type="text" name="quantity" value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>" class="w-full px-3 py-2 border rounded-lg" placeholder="e.g., 1kg, 2 pieces">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700">Expiration Time*</label>
            <input type="datetime-local" name="expiration_time" value="<?php echo htmlspecialchars($_POST['expiration_time'] ?? ''); ?>" required class="w-full px-3 py-2 border rounded-lg">
        </div>

        <div class="mb-6">
            <label class="block text-gray-700">Image</label>
            <input type="file" name="image" accept="image/*" class="w-full px-3 py-2 border rounded-lg">
        </div>

        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">Add Food</button>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const now = new Date();
        const timezoneOffset = now.getTimezoneOffset() * 60000;
        const localISOTime = (new Date(now - timezoneOffset)).toISOString().slice(0, 16);
        document.querySelector('input[name="expiration_time"]').min = localISOTime;
    });
</script>

<?php include 'includes/footer.php'; ?>
