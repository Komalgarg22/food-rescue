<?php 
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = "Exchange Requests";
include 'includes/header.php';

// Initialize database connection
$mysqli = $conn;

// Get incoming exchange requests
$incoming_requests = [];
$stmt = $mysqli->prepare("SELECT e.*, 
                      f1.title as from_food_title, f1.image as from_food_image, u1.name as from_user_name,
                      f2.title as to_food_title, f2.image as to_food_image
                      FROM exchanges e
                      JOIN food_items f1 ON e.from_food_id = f1.id
                      JOIN users u1 ON e.from_user_id = u1.id
                      JOIN food_items f2 ON e.to_food_id = f2.id
                      WHERE e.to_user_id = ? AND e.status = 'pending'
                      ORDER BY e.created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $incoming_requests[] = $row;
}
$stmt->close();

// Get outgoing exchange requests
$outgoing_requests = [];
$stmt = $mysqli->prepare("SELECT e.*, 
                      f1.title as from_food_title, f1.image as from_food_image,
                      f2.title as to_food_title, f2.image as to_food_image, u2.name as to_user_name
                      FROM exchanges e
                      JOIN food_items f1 ON e.from_food_id = f1.id
                      JOIN food_items f2 ON e.to_food_id = f2.id
                      JOIN users u2 ON e.to_user_id = u2.id
                      WHERE e.from_user_id = ?
                      ORDER BY e.created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $outgoing_requests[] = $row;
}
$stmt->close();
?>

<div class="min-h-screen px-6 flex flex-col bg-gray-100">
    <div class="container mx-auto py-8 flex-1">
        <h1 class="text-2xl font-bold mb-6">Exchange Requests</h1>

        <!-- Display messages if any -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Incoming Requests -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-green-600 text-white p-4">
                <h2 class="text-xl font-semibold">Incoming Requests</h2>
            </div>
            <div class="p-4">
                <?php if (empty($incoming_requests)): ?>
                    <p class="text-gray-500">You don't have any incoming exchange requests.</p>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($incoming_requests as $request): ?>
                            <div class="border rounded-lg p-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <!-- Their Offer -->
                                    <div class="border-r pr-4">
                                        <h3 class="font-medium text-gray-500 mb-2">Their Offer</h3>
                                        <div class="flex items-center">
                                            <img src="uploads/food/<?php echo htmlspecialchars($request['from_food_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($request['from_food_title']); ?>" 
                                                 class="w-12 h-12 rounded-md object-cover mr-2">
                                            <div>
                                                <p><?php echo htmlspecialchars($request['from_food_title']); ?></p>
                                                <p class="text-sm text-gray-500">From: <?php echo htmlspecialchars($request['from_user_name']); ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Your Food -->
                                    <div class="border-r pr-4">
                                        <h3 class="font-medium text-gray-500 mb-2">Your Food</h3>
                                        <div class="flex items-center">
                                            <img src="uploads/food/<?php echo htmlspecialchars($request['to_food_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($request['to_food_title']); ?>" 
                                                 class="w-12 h-12 rounded-md object-cover mr-2">
                                            <div>
                                                <p><?php echo htmlspecialchars($request['to_food_title']); ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div>
                                        <h3 class="font-medium text-gray-500 mb-2">Actions</h3>
                                        <div class="flex flex-wrap gap-2">
                                            <form action="process_exchange.php" method="POST" class="inline">
                                                <input type="hidden" name="exchange_id" value="<?php echo (int)$request['id']; ?>">
                                                <input type="hidden" name="action" value="accept">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 transition text-sm">
                                                    Accept
                                                </button>
                                            </form>
                                            <form action="process_exchange.php" method="POST" class="inline">
                                                <input type="hidden" name="exchange_id" value="<?php echo (int)$request['id']; ?>">
                                                <input type="hidden" name="action" value="decline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition text-sm">
                                                    Decline
                                                </button>
                                            </form>
                                            <a href="messages.php?exchange=<?php echo (int)$request['id']; ?>" 
                                               class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition text-sm">
                                                Message
                                            </a>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">
                                            Requested: <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Outgoing Requests -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-blue-600 text-white p-4">
                <h2 class="text-xl font-semibold">Your Requests</h2>
            </div>
            <div class="p-4">
                <?php if (empty($outgoing_requests)): ?>
                    <p class="text-gray-500">You haven't made any exchange requests.</p>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($outgoing_requests as $request): ?>
                            <div class="border rounded-lg p-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <!-- Your Offer -->
                                    <div class="border-r pr-4">
                                        <h3 class="font-medium text-gray-500 mb-2">Your Offer</h3>
                                        <div class="flex items-center">
                                            <img src="uploads/food/<?php echo htmlspecialchars($request['from_food_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($request['from_food_title']); ?>" 
                                                 class="w-12 h-12 rounded-md object-cover mr-2">
                                            <div>
                                                <p><?php echo htmlspecialchars($request['from_food_title']); ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Their Food -->
                                    <div class="border-r pr-4">
                                        <h3 class="font-medium text-gray-500 mb-2">Their Food</h3>
                                        <div class="flex items-center">
                                            <img src="uploads/food/<?php echo htmlspecialchars($request['to_food_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($request['to_food_title']); ?>" 
                                                 class="w-12 h-12 rounded-md object-cover mr-2">
                                            <div>
                                                <p><?php echo htmlspecialchars($request['to_food_title']); ?></p>
                                                <p class="text-sm text-gray-500">From: <?php echo htmlspecialchars($request['to_user_name']); ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status -->
                                    <div>
                                        <h3 class="font-medium text-gray-500 mb-2">Status</h3>
                                        <span class="px-2 py-1 rounded-full text-xs 
                                            <?php 
                                            switch($request['status']) {
                                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'accepted': echo 'bg-green-100 text-green-800'; break;
                                                case 'declined': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                        <p class="text-xs text-gray-500 mt-2">
                                            Requested: <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                                        </p>
                                        <?php if ($request['status'] == 'pending'): ?>
                                            <form action="process_exchange.php" method="POST" class="mt-2" onsubmit="return confirm('Are you sure you want to cancel this request?');">
                                                <input type="hidden" name="exchange_id" value="<?php echo (int)$request['id']; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <button type="submit" class="text-red-600 hover:underline text-sm">Cancel Request</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>