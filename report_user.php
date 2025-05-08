<?php 
require_once 'includes/auth.php';
require_once 'includes/db.php'; // Assumes $conn as the MySQLi object
require_once 'includes/functions.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$reported_user_id = $_GET['id'];
$page_title = "Report User";
$errors = [];
$success = false;

// Verify the reported user exists and is not the current user
$stmt = $conn->prepare("SELECT id, name FROM users WHERE id = ?");
$stmt->bind_param("i", $reported_user_id);
$stmt->execute();
$result = $stmt->get_result();
$reported_user = $result->fetch_assoc();
$stmt->close();

if (!$reported_user || $reported_user_id == $_SESSION['user_id']) {
    $_SESSION['error'] = 'Invalid user to report.';
    header('Location: index.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = sanitizeInput($_POST['reason']);
    $details = sanitizeInput($_POST['details']);
    
    // Validate inputs
    if (empty($reason)) {
        $errors[] = 'Please select a reason for reporting';
    }
    if (empty($details)) {
        $errors[] = 'Please provide details about your report';
    }
    
    if (empty($errors)) {
        // Create the report
        $stmt = $conn->prepare("INSERT INTO reports 
                               (reporter_id, reported_user_id, reason, details, created_at) 
                               VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiss", $_SESSION['user_id'], $reported_user_id, $reason, $details);

        if ($stmt->execute()) {
            $success = true;
            $stmt->close();

            // Create notification for admin
            $admin_message = "New report about user: " . $reported_user['name'];
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, content, created_at) 
                                    SELECT id, ?, NOW() FROM users WHERE role = 'admin'");
            $stmt->bind_param("s", $admin_message);
            $stmt->execute();
            $stmt->close();
        } else {
            $errors[] = 'Failed to submit report. Please try again.';
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold mb-6">Report User: <?php echo htmlspecialchars($reported_user['name']); ?></h2>
    
    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            Your report has been submitted successfully. Our team will review it shortly.
        </div>
        <a href="profile.php?id=<?php echo $reported_user_id; ?>" class="block text-center bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
            Back to Profile
        </a>
    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form action="report_user.php?id=<?php echo $reported_user_id; ?>" method="POST">
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Reason for Reporting*</label>
                <select name="reason" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                    <option value="">Select a reason</option>
                    <option value="Spam">Spam or fake account</option>
                    <option value="Inappropriate Content">Inappropriate content</option>
                    <option value="Harassment">Harassment or bullying</option>
                    <option value="Scam">Scam or fraud</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 mb-2">Details*</label>
                <textarea name="details" rows="4" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600" placeholder="Please provide specific details about your report..."></textarea>
            </div>
            
            <div class="flex justify-between">
                <a href="profile.php?id=<?php echo $reported_user_id; ?>" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                    Cancel
                </a>
                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                    Submit Report
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
