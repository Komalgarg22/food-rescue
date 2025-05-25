<?php
session_start();
require_once 'db.php'; // Assume your MySQLi OOP connection is here

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_SESSION['user_id'])) {
    $from_user_id = $_SESSION['user_id'];
    $to_user_id = intval($_POST['to_user_id']);
    $rating = intval($_POST['rating']);
    $review = trim($_POST['review']);

    if ($from_user_id === $to_user_id) {
        $_SESSION['msg'] = "You cannot rate yourself!";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    // Check if the user has already rated
    $check = $conn->prepare("SELECT id FROM ratings WHERE from_user_id = ? AND to_user_id = ?");
    $check->bind_param("ii", $from_user_id, $to_user_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        // Update rating
        $update = $conn->prepare("UPDATE ratings SET rating = ?, review = ?, updated_at = NOW() WHERE from_user_id = ? AND to_user_id = ?");
        $update->bind_param("isii", $rating, $review, $from_user_id, $to_user_id);
        $update->execute();
        $_SESSION['msg'] = "Rating updated successfully!";
    } else {
        // Insert new rating
        $stmt = $conn->prepare("INSERT INTO ratings (from_user_id, to_user_id, rating, review, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiis", $from_user_id, $to_user_id, $rating, $review);
        $stmt->execute();
        $_SESSION['msg'] = "Rating submitted successfully!";
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
?>
