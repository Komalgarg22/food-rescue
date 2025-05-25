<?php
require_once  'includes/db.php'; // or your DB connection
if (isset($_GET['seller_id'])) {
    $seller_id = (int)$_GET['seller_id'];
} elseif (isset($seller_id)) {
    // Use variable from parent script
    $seller_id = (int)$seller_id;
} else {
    die("Seller ID not provided.");
}
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 3;
$offset = ($page - 1) * $limit;

$count_stmt = $conn->prepare("SELECT COUNT(*) FROM ratings WHERE to_user_id = ?");
$count_stmt->bind_param("i", $seller_id);
$count_stmt->execute();
$count_stmt->bind_result($total_reviews);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_reviews / $limit);

$stmt = $conn->prepare("SELECT r.rating, r.review, r.created_at, u.name FROM ratings r 
    JOIN users u ON r.from_user_id = u.id 
    WHERE r.to_user_id = ? 
    ORDER BY r.created_at DESC 
    LIMIT ?, ?");
$stmt->bind_param("iii", $seller_id, $offset, $limit);
$stmt->execute();
$reviews = $stmt->get_result();
?>

<div class="bg-white border rounded p-4 shadow-md">
    <h3 class="text-md font-semibold text-gray-800 mb-4">User Reviews</h3>

    <?php while ($review = $reviews->fetch_assoc()): ?>
        <div class="border-b py-3">
            <div class="flex items-center space-x-1 mb-1 text-yellow-400 text-sm">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="<?= ($i <= $review['rating']) ? 'text-yellow-400' : 'text-gray-300' ?>">&#9733;</span>
                <?php endfor; ?>
                <span class="text-gray-500 ml-2 text-xs">
                    <?= htmlspecialchars($review['name']); ?> • <?= date("d M Y", strtotime($review['created_at'])); ?>
                </span>
            </div>
            <?php if (!empty($review['review'])): ?>
                <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($review['review'])); ?></p>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>

    <div class="mt-4 flex justify-center space-x-2">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <button class="page-btn px-3 py-1 <?= ($i == $page) ? 'bg-red-500 text-white' : 'bg-gray-200' ?> rounded text-sm"
                data-page="<?= $i ?>"><?= $i ?></button>
        <?php endfor; ?>
    </div>
</div>
