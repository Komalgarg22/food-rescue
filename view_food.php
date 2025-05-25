<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$food_id = $_GET['id'];
$pdo = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($pdo->connect_error) {
    die("Connection failed: " . $pdo->connect_error);
}

$stmt = $pdo->prepare("SELECT f.*, u.name as user_name, u.profile_picture as user_image, u.phone as user_phone 
                      FROM food_items f 
                      JOIN users u ON f.user_id = u.id 
                      WHERE f.id = ?");
$stmt->bind_param('i', $food_id);
$stmt->execute();
$result = $stmt->get_result();
$food = $result->fetch_assoc();

if (!$food) {
    header('Location: index.php');
    exit();
}

$page_title = $food['title'];
include 'includes/header.php';

// Check if current user is the owner of the food item
$is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $food['user_id'];

// Get similar food items (same category)
$stmt = $pdo->prepare("SELECT f.*, u.name as user_name 
                      FROM food_items f 
                      JOIN users u ON f.user_id = u.id 
                      WHERE f.category = ? AND f.id != ? AND f.expiration_time > NOW() 
                      ORDER BY f.created_at DESC LIMIT 3");
$stmt->bind_param('si', $food['category'], $food_id);
$stmt->execute();
$result = $stmt->get_result();
$similar_foods = $result->fetch_all(MYSQLI_ASSOC);

// Get user location if available
$user_lat = $_SESSION['user_lat'] ?? null;
$user_lng = $_SESSION['user_lng'] ?? null;
$distance = null;

if ($user_lat && $user_lng && $food['latitude'] && $food['longitude']) {
    $distance = calculateDistance($user_lat, $user_lng, $food['latitude'], $food['longitude']);
}
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Food Image -->
        <div class="relative h-64 md:h-96">
            <img src="uploads/food/<?php echo $food['image']; ?>" alt="<?php echo $food['title']; ?>" class="w-full h-full object-cover">
            <?php if (strtotime($food['expiration_time']) < time()): ?>
                <div class="absolute top-4 right-4 bg-red-600 text-white px-3 py-1 rounded-full text-sm font-bold">
                    Expired
                </div>
            <?php endif; ?>
        </div>

        <!-- Food Details -->
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-2xl font-bold"><?php echo $food['title']; ?></h1>
                    <p class="text-gray-500"><?php echo $food['category']; ?></p>
                </div>
                <div class="text-right">
                    <p class="text-3xl font-bold text-green-600">₹<?php echo $food['price']; ?></p>
                    <p class="text-sm text-gray-500">Quantity: <?php echo $food['quantity']; ?></p>
                </div>
            </div>

            <div class="mb-6">
                <p class="text-gray-700"><?php echo $food['description']; ?></p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Seller Info -->
                <div class="relative border rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-3">Seller Information</h3>



                    <div class="flex items-center mb-3">
                        <img src="uploads/profile/<?php echo $food['user_image']; ?>" alt="<?php echo $food['user_name']; ?>" class="w-12 h-12 rounded-full mr-3">
                        <div>
                            <p class="font-medium"><?php echo $food['user_name']; ?></p>
                            <p class="text-sm text-gray-500">Member since <?php echo date('M Y', strtotime($food['created_at'])); ?></p>
                        </div>
                    </div>

                    <?php if ($distance !== null): ?>
                        <p class="text-sm text-gray-600 mb-2">
                            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            About <?php echo round($distance, 2); ?> km away
                        </p>
                    <?php endif; ?>

                    <p class="text-sm text-gray-600 mb-2">
                        <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        <?php echo $food['user_phone'] ?? 'Phone not available'; ?>
                    </p>

                    <?php if (!$is_owner && isset($_SESSION['user_id'])): ?>
                        <a href="messages.php?to=<?php echo $food['user_id']; ?>" class="inline-block mt-2 text-green-600 hover:underline">
                            Contact Seller
                        </a>
                        <?php
                        $seller_id = $food['user_id'];
                        $avg_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM ratings WHERE to_user_id = ?");
                        $avg_stmt->bind_param("i", $seller_id);
                        $avg_stmt->execute();
                        $avg_stmt->bind_result($avg_rating, $total_reviews);
                        $avg_stmt->fetch();
                        $avg_stmt->close();

                        if ($total_reviews > 0):
                        ?>
                            <div class="flex items-center gap-1 text-yellow-500 text-sm font-medium">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="<?= ($i <= round($avg_rating)) ? 'text-yellow-400' : 'text-gray-300' ?>">&#9733;</span>
                                <?php endfor; ?>
                                <span class="ml-2 text-gray-600">(<?= number_format($avg_rating, 1) ?> from <?= $total_reviews ?> reviews)</span>
                            </div>
                        <?php endif; ?>

                        <form id="ratingForm" class="space-y-3">
                            <input type="hidden" name="to_user_id" value="<?php echo $food['user_id']; ?>">
                            <input type="hidden" name="from_user_id" value="<?php echo $_SESSION['user_id']; ?>">
                            <input type="hidden" name="rating" id="ratingInput" value="0">

                            <div class="flex space-x-1 cursor-pointer text-2xl text-gray-300" id="starContainer">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span data-value="<?= $i ?>" class="star hover:text-yellow-400 transition-all">&#9733;</span>
                                <?php endfor; ?>
                            </div>

                            <textarea name="review" placeholder="Write a short review (optional)"
                                class="w-full border rounded px-3 py-2 text-sm" rows="2"></textarea>

                            <button type="submit"
                                class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded w-full text-sm font-medium">Submit Rating</button>
                        </form>

                        <div id="ratingMessage" class="mt-2 text-sm text-green-600 hidden">Rating submitted successfully!</div>

                        <script>
                            const stars = document.querySelectorAll('.star');
                            const ratingInput = document.getElementById('ratingInput');

                            stars.forEach((star, idx) => {
                                star.addEventListener('click', () => {
                                    const selected = idx + 1;
                                    ratingInput.value = selected;
                                    stars.forEach((s, i) => {
                                        s.classList.toggle('text-yellow-400', i < selected);
                                        s.classList.toggle('text-gray-300', i >= selected);
                                    });
                                });
                            });

                            // Submit Rating with AJAX
                            // Replace the existing rating form script with this
                            const ratingForm = document.getElementById('ratingForm');
                            ratingForm.addEventListener('submit', async function(e) {
                                e.preventDefault();

                                const button = this.querySelector('button[type="submit"]');
                                const originalText = button.innerHTML;

                                // Show loading state
                                button.innerHTML = `
        <span class="flex items-center">
            <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Submitting...
        </span>
    `;
                                button.disabled = true;

                                try {
                                    const formData = new FormData(this);
                                    const response = await fetch('rate_user.php', {
                                        method: 'POST',
                                        body: formData
                                    });

                                    const data = await response.json();

                                    if (!response.ok || !data.success) {
                                        throw new Error(data.message || 'Failed to submit rating');
                                    }

                                    // Success - update UI
                                    document.getElementById('ratingMessage').classList.remove('hidden');
                                    document.getElementById('ratingMessage').textContent = data.message;

                                    // Reload reviews
                                    const sellerId = <?= $seller_id ?>;
                                    const reviewContainer = document.getElementById('reviewContainer');

                                    const reviewResponse = await fetch(`load_reviews.php?seller_id=${sellerId}`);
                                    reviewContainer.innerHTML = await reviewResponse.text();

                                    // Reset form
                                    this.reset();
                                    stars.forEach(s => s.classList.remove('text-yellow-400'));

                                } catch (error) {
                                    showToast(error.message, 'error');
                                } finally {
                                    button.innerHTML = originalText;
                                    button.disabled = false;
                                }
                            });
                        </script>








                    <?php endif; ?>

                    <?php if (!$is_owner && isset($_SESSION['user_id'])): ?>
                        <div class="absolute top-4 right-4">
                            <!-- 🚩 Report Icon Button -->
                            <button id="toggleReportForm" class="flex items-center space-x-1 text-sm text-red-500 hover:text-red-600 focus:outline-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 fill-current" viewBox="0 0 20 20">
                                    <path d="M18.364 1.636a1 1 0 0 0-1.414 0L10 8.586 3.05 1.636A1 1 0 1 0 1.636 3.05L8.586 10l-6.95 6.95a1 1 0 1 0 1.414 1.414L10 11.414l6.95 6.95a1 1 0 0 0 1.414-1.414L11.414 10l6.95-6.95a1 1 0 0 0 0-1.414z" />
                                </svg>
                                <span>Report</span>
                            </button>

                            <!-- 🔽 Hidden Popup Report Form -->
                            <div id="reportForm" class="hidden absolute z-10 bg-white border border-gray-300 rounded shadow-lg w-64 mt-2 p-4">
                                <form id="reportUserForm" action="report_user.php" method="POST" class="space-y-3">
                                    <input type="hidden" name="reported_user_id" value="<?php echo $food['user_id']; ?>">
                                    <input type="hidden" name="reporter_id" value="<?php echo $_SESSION['user_id']; ?>">

                                    <label class="block text-sm font-medium text-gray-700">Reason</label>
                                    <select name="reason" required class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-red-400">
                                        <option value="">Choose one</option>
                                        <option value="Fake listing">Fake listing</option>
                                        <option value="Abusive behavior">Abusive behavior</option>
                                        <option value="Scam or fraud">Scam or fraud</option>
                                        <option value="Other">Other</option>
                                    </select>

                                    <textarea name="details" rows="2" placeholder="More details (optional)" class="w-full border border-gray-300 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-red-400"></textarea>

                                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm w-full">
                                        Submit Report
                                    </button>
                                </form>
                                <p id="reportMessage" class="hidden text-green-600 text-sm mt-2">Report submitted successfully.</p>
                            </div>
                        </div>

                        <!-- 🧠 Toggle Script -->
                        <script>
                            // Toggle the report form visibility
                            document.getElementById('toggleReportForm').addEventListener('click', () => {
                                const formWrapper = document.getElementById('reportForm');
                                formWrapper.classList.toggle('hidden');
                            });

                            // Submit report form using AJAX
                            document.getElementById('reportUserForm').addEventListener('submit', async function(e) {
                                e.preventDefault();
                                const formData = new FormData(this);

                                const response = await fetch('report_user.php', {
                                    method: 'POST',
                                    body: formData
                                });

                                if (response.ok) {
                                    document.getElementById('reportMessage').classList.remove('hidden');
                                    this.reset();
                                    document.getElementById('reportForm').classList.add('hidden');
                                } else {
                                    alert("Failed to submit report.");
                                }
                            });
                        </script>
                    <?php endif; ?>

                </div>

                <!-- Food Details -->
                <div class="border rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-3">Food Details</h3>
                    <div class="space-y-2">
                        <p class="text-sm">
                            <span class="font-medium text-gray-600">Posted:</span>
                            <?php echo date('M j, Y g:i A', strtotime($food['created_at'])); ?>
                        </p>
                        <p class="text-sm">
                            <span class="font-medium text-gray-600">Expires:</span>
                            <?php echo date('M j, Y g:i A', strtotime($food['expiration_time'])); ?>
                            <?php if (strtotime($food['expiration_time']) < time()): ?>
                                <span class="text-red-500 ml-2">(Expired)</span>
                            <?php endif; ?>
                        </p>
                        <p class="text-sm">
                            <span class="font-medium text-gray-600">Condition:</span>
                            <?php
                            $expired = strtotime($food['expiration_time']) < time();
                            echo $expired ? 'Expired' : 'Fresh';
                            ?>
                        </p>
                        <?php if ($food['latitude'] && $food['longitude']): ?>
                            <p class="text-sm">
                                <span class="font-medium text-gray-600">Location:</span>
                                <a href="https://www.google.com/maps?q=<?php echo $food['latitude']; ?>,<?php echo $food['longitude']; ?>" target="_blank" class="text-green-600 hover:underline">
                                    View on Map
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap gap-3 mb-8">
                <?php if ($is_owner): ?>
                    <a href="edit_food.php?id=<?php echo $food_id; ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Edit Listing
                    </a>
                    <button onclick="deleteFoodItem(<?php echo $food_id; ?>)"
                        class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition flex items-center justify-center">
                        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Delete Listing
                    </button>
                <?php elseif (isset($_SESSION['user_id']) && strtotime($food['expiration_time']) > time()): ?>
                    <a href="order_food.php?id=<?php echo $food_id; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        Order Now
                    </a>
                    <a href="exchange_request.php?food_id=<?php echo $food_id; ?>" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition">
                        Request Exchange
                    </a>
                    <button onclick="saveForLater(<?php echo $food_id; ?>)"
                        class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition flex items-center justify-center">
                        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                        </svg>
                        Save for Later
                    </button>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <a href="login.php?redirect=view_food.php?id=<?php echo $food_id; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        Login to Order
                    </a>
                <?php else: ?>
                    <button disabled class="bg-gray-400 text-white px-4 py-2 rounded-lg cursor-not-allowed">
                        Expired Item
                    </button>
                <?php endif; ?>
            </div>

            <div id="reviewContainer">
                <?php
                // Pass seller_id directly to load_reviews.php
                $seller_id = $food['user_id'];
                include 'load_reviews.php';
                ?>
            </div>

            <!-- Update pagination script -->
            <script>
                document.addEventListener("click", function(e) {
                    if (e.target.classList.contains("page-btn")) {
                        const page = e.target.getAttribute("data-page");
                        const sellerId = <?= $seller_id ?>;

                        fetch(`load_reviews.php?page=${page}&seller_id=${sellerId}`)
                            .then(res => res.text())
                            .then(html => {
                                document.getElementById("reviewContainer").innerHTML = html;
                            })
                            .catch(err => console.error('Error loading reviews:', err));
                    }
                });
            </script>

            <!-- Similar Items -->
            <?php if (!empty($similar_foods)): ?>
                <div class="mt-8">
                    <h3 class="text-xl font-semibold mb-4">Similar Items</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php foreach ($similar_foods as $similar): ?>
                            <div class="border rounded-lg overflow-hidden hover:shadow-md transition">
                                <a href="view_food.php?id=<?php echo $similar['id']; ?>">
                                    <img src="uploads/food/<?php echo $similar['image']; ?>" alt="<?php echo $similar['title']; ?>" class="w-full h-32 object-cover">
                                    <div class="p-3">
                                        <h4 class="font-medium"><?php echo $similar['title']; ?></h4>
                                        <p class="text-green-600 font-bold">₹<?php echo $similar['price']; ?></p>
                                        <p class="text-sm text-gray-500"><?php echo $similar['category']; ?></p>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<script>
    async function deleteFoodItem(foodId) {
        if (!confirm('Are you sure you want to delete this food item? This action cannot be undone.')) {
            return;
        }

        const button = event.currentTarget;
        const originalHTML = button.innerHTML;

        // Show loading state
        button.innerHTML = `
        <span class="flex items-center">
            <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Deleting...
        </span>
    `;
        button.disabled = true;

        try {
            const response = await fetch('delete_food.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${foodId}`
            });

            // First check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error(`Server returned invalid response: ${text.substring(0, 100)}`);
            }

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to delete item');
            }

            // Success - show message and redirect
            showToast('Food item deleted successfully!', 'success');

            // Redirect after 1.5 seconds
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 1500);

        } catch (error) {
            console.error('Delete error:', error);
            showToast(error.message, 'error');
            button.innerHTML = originalHTML;
            button.disabled = false;
        }
    }
    async function saveForLater(foodId, buttonElement) {
        const button = buttonElement || event.currentTarget;
        const originalHTML = button.innerHTML;

        // Show loading state
        button.innerHTML = `
        <span class="flex items-center">
            <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Saving...
        </span>
    `;
        button.disabled = true;

        try {
            const response = await fetch('save_food.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `food_id=${foodId}`
            });

            // First check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error(`Server returned invalid response: ${text.substring(0, 100)}`);
            }

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to save item');
            }

            // Success - update button
            button.innerHTML = `
            <span class="flex items-center">
                <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path>
                </svg>
                Saved
            </span>
        `;
            button.classList.remove('bg-gray-600', 'hover:bg-gray-700');
            button.classList.add('bg-green-600', 'hover:bg-green-700');
            button.disabled = true;

            showToast('Item saved to your favorites!', 'success');
        } catch (error) {
            console.error('Save error:', error);
            showToast(error.message, 'error');
            button.innerHTML = originalHTML;
            button.disabled = false;
        }
    }
    // Add this toast notification function
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded-md shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    }`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('opacity-0', 'transition-opacity', 'duration-300');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
</script>

<?php include 'includes/footer.php'; ?>