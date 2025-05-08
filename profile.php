<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
$mysqli = $conn;

$page_title = "Your Profile";
$success = '';


$errors = [];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    
    // Handle profile picture upload
    if (!empty($_FILES['profile_picture']['name'])) {
        $upload = uploadImage($_FILES['profile_picture'], 'uploads/profile/');
        if ($upload['success']) {
            $profile_picture = $upload['filename'];
            
            // Delete old profile picture if it exists
            if (!empty($user['profile_picture']) && file_exists('uploads/profile/' . $user['profile_picture'])) {
                unlink('uploads/profile/' . $user['profile_picture']);
            }
        } else {
            $errors[] = $upload['message'];
        }
    } else {
        $profile_picture = $user['profile_picture'];
    }
    
    // Handle password change if provided
    $password_change = '';
    if (!empty($_POST['new_password'])) {
        if (password_verify($_POST['current_password'], $user['password'])) {
            if ($_POST['new_password'] === $_POST['confirm_password']) {
                $password_change = ', password = ?';
                $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            } else {
                $errors[] = 'New passwords do not match';
            }
        } else {
            $errors[] = 'Current password is incorrect';
        }
    }
    
    if (empty($errors)) {
        // Prepare the query and parameters based on whether password change is needed
        if (!empty($password_change)) {
            $stmt = $mysqli->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, profile_picture = ? $password_change WHERE id = ?");
            $params = [$name, $email, $phone, $address, $profile_picture, $new_password, $_SESSION['user_id']];
            $param_types = "ssssssi"; // 6 strings and 1 integer
        } else {
            $stmt = $mysqli->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, profile_picture = ? WHERE id = ?");
            $params = [$name, $email, $phone, $address, $profile_picture, $_SESSION['user_id']];
            $param_types = "sssssi"; // 5 strings and 1 integer
        }
        
        // Dynamically bind parameters with the correct types
        if ($stmt->bind_param($param_types, ...$params) && $stmt->execute()) {
            $success = 'Profile updated successfully!';
            // Refresh user data
            $stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } else {
            $errors[] = 'Failed to update profile. Please try again.';
        }
    }
}

// Get user ratings
$stmt = $mysqli->prepare("SELECT * FROM ratings WHERE to_user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$ratings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate average rating
$avg_rating = 0;
if (count($ratings) > 0) {
    $sum = 0;
    foreach ($ratings as $rating) {
        $sum += $rating['rating'];
    }
    $avg_rating = $sum / count($ratings);
}


include 'includes/header.php';
?>
<div class="min-h-screen flex items-center justify-center bg-gray-100">
<div class="max-w-4xl w-full p-4">
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Profile Header -->
        <div class="bg-green-600 p-6 text-white">
            <div class="flex items-center">
                <div class="mr-4">
                    <img src="uploads/profile/<?php echo !empty($user['profile_picture']) ? $user['profile_picture'] : 'default.png'; ?>" 
                         alt="<?php echo $user['name']; ?>" 
                         class="w-20 h-20 rounded-full object-cover border-4 border-white">
                </div>
                <div>
                    <h1 class="text-2xl font-bold"><?php echo $user['name']; ?></h1>
                    <p class="text-green-100"><?php echo $user['email']; ?></p>
                    <div class="flex items-center mt-2">
                        <div class="flex">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg class="w-5 h-5 <?php echo $i <= round($avg_rating) ? 'text-yellow-400' : 'text-gray-300'; ?>" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                            <?php endfor; ?>
                        </div>
                        <span class="ml-2 text-white"><?php echo number_format($avg_rating, 1); ?> (<?php echo count($ratings); ?> reviews)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Content -->
        <div class="p-6">
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Edit Profile Form -->
                <div class="md:col-span-2">
                    <h2 class="text-xl font-semibold mb-4">Edit Profile</h2>
                    <form action="profile.php" method="POST" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="name" class="block text-gray-700 mb-2">Full Name</label>
                                <input type="text" id="name" name="name" value="<?php echo $user['name']; ?>" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                            </div>
                            <div>
                                <label for="email" class="block text-gray-700 mb-2">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo $user['email']; ?>" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="phone" class="block text-gray-700 mb-2">Phone</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo $user['phone']; ?>" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                            </div>
                            <div>
                                <label for="profile_picture" class="block text-gray-700 mb-2">Profile Picture</label>
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="address" class="block text-gray-700 mb-2">Address</label>
                            <textarea id="address" name="address" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600"><?php echo $user['address']; ?></textarea>
                        </div>
                        
                        <h3 class="text-lg font-semibold mb-2 mt-6">Change Password</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="current_password" class="block text-gray-700 mb-2">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                            </div>
                            <div>
                                <label for="new_password" class="block text-gray-700 mb-2">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-gray-700 mb-2">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                            </div>
                        </div>
                        
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">Update Profile</button>
                    </form>
                </div>
                
                <!-- Ratings Section -->
                <div>
                    <h2 class="text-xl font-semibold mb-4">Your Ratings</h2>
                    
                    <?php if (empty($ratings)): ?>
                        <p class="text-gray-500">You haven't received any ratings yet.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($ratings as $rating): ?>
                                <div class="border-b pb-4">
                                    <div class="flex items-center mb-2">
                                        <?php 
                                        $stmt = $pdo->prepare("SELECT name, profile_picture FROM users WHERE id = ?");
                                        $stmt->execute([$rating['from_user_id']]);
                                        $rater = $stmt->fetch();
                                        ?>
                                        <img src="uploads/profile/<?php echo !empty($rater['profile_picture']) ? $rater['profile_picture'] : 'default.png'; ?>" 
                                             alt="<?php echo $rater['name']; ?>" 
                                             class="w-8 h-8 rounded-full mr-2">
                                        <span class="font-medium"><?php echo $rater['name']; ?></span>
                                    </div>
                                    <div class="flex mb-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <svg class="w-4 h-4 <?php echo $i <= $rating['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                            </svg>
                                        <?php endfor; ?>
                                    </div>
                                    <p class="text-sm text-gray-600"><?php echo $rating['review']; ?></p>
                                    <p class="text-xs text-gray-400 mt-1"><?php echo date('M j, Y', strtotime($rating['created_at'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>

<?php include 'includes/footer.php'; ?>