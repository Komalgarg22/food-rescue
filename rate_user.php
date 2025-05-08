<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$rated_user_id = $_GET['id'];
$page_title = "Rate User";
$errors = [];
$success = false;

// Verify the rated user exists and is not the current user
$stmt = $mysqli->prepare("SELECT id, name FROM users WHERE id = ?");
$stmt->bind_param("i", $rated_user_id);
$stmt->execute();
$result = $stmt->get_result();
$rated_user = $result->fetch_assoc();

if (!$rated_user || $rated_user_id == $_SESSION['user_id']) {
    $_SESSION['error'] = 'Invalid user to rate.';
    header('Location: index.php');
    exit();
}

// Check if rating already exists
$stmt = $mysqli->prepare("SELECT * FROM ratings WHERE from_user_id = ? AND to_user_id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $rated_user_id);
$stmt->execute();
$result = $stmt->get_result();
$existing_rating = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $review = sanitizeInput($_POST['review']);

    // Validate inputs
    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please select a valid rating (1-5 stars)';
    }
    if (empty($review)) {
        $errors[] = 'Please write a review';
    }

    if (empty($errors)) {
        if ($existing_rating) {
            // Update existing rating
            $stmt = $mysqli->prepare("UPDATE ratings SET rating = ?, review = ?, created_at = NOW() WHERE id = ?");
            $stmt->bind_param("isi", $rating, $review, $existing_rating['id']);
            $success = $stmt->execute();
        } else {
            // Create new rating
            $stmt = $mysqli->prepare("INSERT INTO ratings (from_user_id, to_user_id, rating, review, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiis", $_SESSION['user_id'], $rated_user_id, $rating, $review);
            $success = $stmt->execute();
        }

        if ($success) {
            $_SESSION['success'] = 'Thank you for your rating!';
            header('Location: profile.php?id=' . $rated_user_id);
            exit();
        } else {
            $errors[] = 'Failed to submit rating. Please try again.';
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold mb-6">Rate User: <?php echo $rated_user['name']; ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="rate_user.php?id=<?php echo $rated_user_id; ?>" method="POST">
        <div class="mb-6">
            <label class="block text-gray-700 mb-2">Your Rating*</label>
            <div class="flex items-center">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>"
                           <?php echo ($existing_rating && $existing_rating['rating'] == $i) ? 'checked' : ''; ?>
                           class="hidden">
                    <label for="star<?php echo $i; ?>" class="text-3xl cursor-pointer <?php echo ($existing_rating && $i <= $existing_rating['rating']) ? 'text-yellow-400' : 'text-gray-300'; ?>">★</label>
                <?php endfor; ?>
            </div>
        </div>

        <div class="mb-6">
            <label for="review" class="block text-gray-700 mb-2">Your Review*</label>
            <textarea id="review" name="review" rows="4" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600" placeholder="Share your experience with this user..."><?php echo $existing_rating ? htmlspecialchars($existing_rating['review']) : ''; ?></textarea>
        </div>

        <div class="flex justify-between">
            <a href="profile.php?id=<?php echo $rated_user_id; ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                Cancel
            </a>
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                Submit Rating
            </button>
        </div>
    </form>
</div>

<script>
// Star rating interaction
document.querySelectorAll('label[for^="star"]').forEach(label => {
    label.addEventListener('click', function() {
        const starId = this.getAttribute('for');
        const starValue = parseInt(starId.replace('star', ''));

        // Update stars visually
        document.querySelectorAll('label[for^="star"]').forEach((star, index) => {
            star.classList.toggle('text-yellow-400', index < starValue);
            star.classList.toggle('text-gray-300', index >= starValue);
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
