<?php
require_once 'db.php';
require_once 'functions.php';

class Mailer {
    private $pdo;
    private $mailer;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        // Configure PHPMailer (you'll need to install it via composer)
        $this->mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host = 'your-smtp-host.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = 'your-email@example.com';
        $this->mailer->Password = 'your-email-password';
        $this->mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = 587;
        $this->mailer->setFrom('no-reply@foodrescue.com', 'Food Rescue');
    }

    public function sendOrderNotification($order_id) {
        try {
            // Get order details
            $stmt = $this->pdo->prepare("SELECT o.*, f.title as food_title, 
                                        u1.name as buyer_name, u1.email as buyer_email,
                                        u2.name as seller_name, u2.email as seller_email
                                        FROM orders o
                                        JOIN food_items f ON o.food_id = f.id
                                        JOIN users u1 ON o.buyer_id = u1.id
                                        JOIN users u2 ON o.seller_id = u2.id
                                        WHERE o.id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();

            if (!$order) return false;

            // Email to seller
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($order['seller_email'], $order['seller_name']);
            $this->mailer->Subject = 'New Order: ' . $order['food_title'];
            $this->mailer->Body = "Hello {$order['seller_name']},\n\n"
                                . "You have a new order for your food item: {$order['food_title']}\n\n"
                                . "Buyer: {$order['buyer_name']}\n"
                                . "Order Date: " . date('M j, Y g:i A', strtotime($order['created_at'])) . "\n\n"
                                . "Please log in to your account to manage this order.\n\n"
                                . "Thank you,\nFood Rescue Team";
            $this->mailer->send();

            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $e->getMessage());
            return false;
        }
    }

    public function sendExchangeNotification($exchange_id) {
        // Similar implementation for exchange notifications
    }

    public function sendGeneralNotification($user_id, $subject, $message) {
        // Implementation for general notifications
    }
}
?>