<?php
session_start();
require_once 'db.php'; // Assume your MySQLi OOP connection is here

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_SESSION['user_id'])) {
    $reporter_id = $_SESSION['user_id'];
    $reported_user_id = intval($_POST['reported_user_id']);
    $reason = trim($_POST['reason']);

    if ($reporter_id === $reported_user_id) {
        $_SESSION['msg'] = "You cannot report yourself!";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    // Prevent duplicate reports (optional)
    $check = $conn->prepare("SELECT id FROM reports WHERE reporter_id = ? AND reported_user_id = ?");
    $check->bind_param("ii", $reporter_id, $reported_user_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $_SESSION['msg'] = "You have already reported this user.";
    } else {
        $stmt = $conn->prepare("INSERT INTO reports (reporter_id, reported_user_id, reason, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $reporter_id, $reported_user_id, $reason);
        $stmt->execute();
        $_SESSION['msg'] = "Report submitted successfully!";
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}
?>
