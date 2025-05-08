<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = 'Invalid CSRF token.';
    header('Location: exchange_requests.php');
    exit();
}

// Validate required parameters
if (!isset($_POST['action']) || !isset($_POST['exchange_id']) || !ctype_digit($_POST['exchange_id'])) {
    $_SESSION['error'] = 'Invalid request parameters.';
    header('Location: exchange_requests.php');
    exit();
}

$action = $_POST['action'];
$exchange_id = (int)$_POST['exchange_id'];
$mysqli = $conn;

try {
    // Get the exchange request details
    $stmt = $mysqli->prepare("SELECT * FROM exchanges WHERE id = ?");
    $stmt->bind_param("i", $exchange_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exchange = $result->fetch_assoc();
    $stmt->close();

    if (!$exchange) {
        $_SESSION['error'] = 'Exchange request not found.';
        header('Location: exchange_requests.php');
        exit();
    }

    // Verify user has permission to perform this action
    $allowed = false;
    switch ($action) {
        case 'accept':
        case 'decline':
            $allowed = ($_SESSION['user_id'] == $exchange['to_user_id']);
            break;
        case 'cancel':
            $allowed = ($_SESSION['user_id'] == $exchange['from_user_id']);
            break;
        default:
            $_SESSION['error'] = 'Invalid action.';
            header('Location: exchange_requests.php');
            exit();
    }

    if (!$allowed) {
        $_SESSION['error'] = 'You are not authorized to perform this action.';
        header('Location: exchange_requests.php');
        exit();
    }

    // Process the action
    switch ($action) {
        case 'accept':
            // Begin transaction
            $mysqli->begin_transaction();

            try {
                // Update exchange status
                $stmt = $mysqli->prepare("UPDATE exchanges SET status = 'accepted', updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $exchange_id);
                $stmt->execute();
                $stmt->close();

                // Mark both food items as exchanged
                $stmt = $mysqli->prepare("UPDATE food_items SET status = 'exchanged' WHERE id IN (?, ?)");
                $stmt->bind_param("ii", $exchange['from_food_id'], $exchange['to_food_id']);
                $stmt->execute();
                $stmt->close();

                // Commit transaction
                $mysqli->commit();

                $_SESSION['success'] = 'Exchange request accepted successfully!';
            } catch (Exception $e) {
                $mysqli->rollback();
                $_SESSION['error'] = 'Failed to accept exchange request. Please try again.';
            }
            break;

        case 'decline':
            $stmt = $mysqli->prepare("UPDATE exchanges SET status = 'declined', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $exchange_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Exchange request declined.';
            } else {
                $_SESSION['error'] = 'Failed to decline exchange request.';
            }
            $stmt->close();
            break;

        case 'cancel':
            $stmt = $mysqli->prepare("UPDATE exchanges SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $exchange_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Exchange request cancelled.';
            } else {
                $_SESSION['error'] = 'Failed to cancel exchange request.';
            }
            $stmt->close();
            break;
    }

} catch (mysqli_sql_exception $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = 'Database error occurred. Please try again.';
}

header('Location: exchange_requests.php');
exit();
?>