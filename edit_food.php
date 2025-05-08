<?php
require_once 'includes/auth.php';
require_once 'includes/db.php'; // should define $conn (MySQLi)
require_once 'includes/functions.php';

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$food_id = intval($_GET['id']);
$page_title = "Edit Food Listing";
$errors = [];

$user_id = $_SESSION['user_id'];
$food = null;

// Fetch food item using MySQLi
$stmt = $conn->prepare("SELECT * FROM food_items WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $food_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$food = $result->fetch_assoc();
$stmt->close();

if (!$food) {
    $_SESSION['error'] = 'Food item not found or you do not have permission to edit it.';
    header('Location: dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $category = sanitizeInput($_POST['category']);
    $price = sanitizeInput($_POST['price']);
    $quantity = sanitizeInput($_POST['quantity']);
    $expiration_time = sanitizeInput($_POST['expiration_time']);
    
    // Validate inputs
    if (empty($title)) $errors[] = 'Title is required';
    if (empty($description)) $errors[] = 'Description is required';
    if (!is_numeric($price) || $price < 0) $errors[] = 'Price must be a positive number';
    if (empty($expiration_time)) $errors[] = 'Expiration time is required';
    
    // Handle image upload
    $image = $food['image'];
    if (!empty($_FILES['image']['name'])) {
        $upload = uploadImage($_FILES['image'], 'uploads/food/');
        if ($upload['success']) {
            if (file_exists('uploads/food/' . $food['image'])) {
                unlink('uploads/food/' . $food['image']);
            }
            $image = $upload['filename'];
        } else {
            $errors[] = $upload['message'];
        }
    }

    // Update if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE food_items SET 
            title = ?, description = ?, category = ?, 
            price = ?, quantity = ?, image = ?, 
            expiration_time = ?
            WHERE id = ?");

        $stmt->bind_param("sssdsssi", $title, $description, $category, $price, $quantity, $image, $expiration_time, $food_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = 'Food item updated successfully!';
            $stmt->close();
            header('Location: view_food.php?id=' . $food_id);
            exit();
        } else {
            $errors[] = 'Failed to update food item. Please try again.';
        }
        $stmt->close();
    }
}

include 'includes/header.php';
?>

<!-- HTML FORM UI -->
<div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold mb-6">Edit Food Listing</h2>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="edit_food.php?id=<?php echo $food_id; ?>" method="POST" enctype="multipart/form-data">
        <div class="mb-4">
            <label for="title" class="block text-gray-700 mb-2">Food Title*</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($food['title']); ?>" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
        </div>

        <div class="mb-4">
            <label for="description" class="block text-gray-700 mb-2">Description*</label>
            <textarea id="description" name="description" rows="3" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"><?php echo htmlspecialchars($food['description']); ?></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="category" class="block text-gray-700 mb-2">Category*</label>
                <select id="category" name="category" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                    <?php
                    $categories = [  "Home Cooked",
                    "Leftover Food",
                    "Fruits & Vegetables",
                    "Meals",
                    "Snacks",
                    "Beverages",
                    "Bakery & Desserts",
                    "Non-Veg",
                    "South Indian",
                    "North Indian",
                    "Chinese",
                    "Fast Food",
                    "Salads", 'Other'];
                    foreach ($categories as $cat) {
                        $selected = $food['category'] === $cat ? 'selected' : '';
                        echo "<option value=\"$cat\" $selected>$cat</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="price" class="block text-gray-700 mb-2">Price ($)*</label>
                <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($food['price']); ?>" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="quantity" class="block text-gray-700 mb-2">Quantity</label>
                <input type="text" id="quantity" name="quantity" value="<?php echo htmlspecialchars($food['quantity']); ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600" placeholder="e.g., 1 kg, 2 pieces, etc.">
            </div>
            <div>
                <label for="expiration_time" class="block text-gray-700 mb-2">Expiration Time*</label>
                <input type="datetime-local" id="expiration_time" name="expiration_time" value="<?php echo date('Y-m-d\TH:i', strtotime($food['expiration_time'])); ?>" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
            </div>
        </div>

        <div class="mb-6">
            <label for="image" class="block text-gray-700 mb-2">Food Image</label>
            <div class="flex items-center mb-2">
                <img src="uploads/food/<?php echo $food['image']; ?>" alt="Current image" class="w-16 h-16 rounded-md object-cover mr-3">
                <span class="text-sm text-gray-500">Current image</span>
            </div>
            <input type="file" id="image" name="image" accept="image/*" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
            <p class="text-xs text-gray-500 mt-1">Leave blank to keep current image</p>
        </div>

        <div class="flex justify-between">
            <a href="view_food.php?id=<?php echo $food_id; ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">Cancel</a>
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">Update Food Item</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const now = new Date();
    const timezoneOffset = now.getTimezoneOffset() * 60000;
    const localISOTime = (new Date(now - timezoneOffset)).toISOString().slice(0, 16);
    document.getElementById('expiration_time').min = localISOTime;
});
</script>

<?php include 'includes/footer.php'; ?>
