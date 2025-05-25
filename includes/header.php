<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$page_title = $page_title ?? 'Home';

// Get current page without query string
$current_page = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Hide search form on login/register
$hide_search = in_array($current_page, ['login.php', 'register.php']);

// Active class checker
function active_link($page)
{
    global $current_page;
    return $current_page === $page ? 'text-yellow-400 font-bold' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Food Rescue - <?php echo htmlspecialchars($page_title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="bg-gray-100">
<nav class="bg-green-600 text-white p-4 shadow-lg">
    <div class="container mx-auto flex justify-between items-center">
        <a href="index.php" class="text-2xl font-bold hover:text-yellow-300 transition">Food Rescue</a>

        <!-- Desktop Menu -->
        <div class="hidden md:flex items-center space-x-6">

            <!-- Search Form -->
            <?php if (!$hide_search): ?>
                <form action="search.php" method="GET" class="flex items-center gap-2">
                    <input 
                        type="text" 
                        name="q" 
                        placeholder="Search food..."
                        class="px-3 py-2 rounded-lg border border-white focus:outline-none focus:ring-2 focus:ring-yellow-400 text-black w-64"
                    >
                    <button type="submit" class="bg-yellow-400 text-black px-3 py-2 rounded-lg hover:bg-yellow-500">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="dashboard.php" class="transition hover:text-yellow-300 transform hover:scale-105 <?php echo active_link('dashboard.php'); ?>">
                    <i class="fas fa-home mr-1"></i> Dashboard
                </a>
                <a href="messages.php" class="transition hover:text-yellow-300 transform hover:scale-105 <?php echo active_link('messages.php'); ?>">
                    <i class="fas fa-envelope mr-1"></i> Messages
                </a>
                <a href="order_food.php" class="transition hover:text-yellow-300 transform hover:scale-105 <?php echo active_link('order_food.php'); ?>">
                    <i class="fas fa-utensils mr-1"></i> Order Food
                </a>
                <a href="saved_items.php" class="transition hover:text-yellow-300 transform hover:scale-105 <?php echo active_link('saved_items.php'); ?>">
                    <i class="fas fa-bookmark mr-1"></i> Saved Items
                </a>
                <a href="exchange_list.php" class="transition hover:text-yellow-300 transform hover:scale-105 <?php echo active_link('exchange_list.php'); ?>">
                    <i class="fas fa-exchange-alt mr-1"></i> Exchanges
                </a>
                <a href="my_order.php" class="transition hover:text-yellow-300 transform hover:scale-105 <?php echo active_link('track_order.php'); ?>">
                    <i class="fas fa-map-marker-alt mr-1"></i> Track Order
                </a>
                <a href="notifications.php" class="transition hover:text-yellow-300 transform hover:scale-105 <?php echo active_link('notifications.php'); ?>">
                    <i class="fas fa-bell mr-1"></i> Notifications
                </a>
                <a href="profile.php" class="transition hover:text-yellow-300 transform hover:scale-105 <?php echo active_link('profile.php'); ?>">
                    <i class="fas fa-user mr-1"></i> Profile
                </a>
                <a href="logout.php" class="transition hover:text-yellow-300 transform hover:scale-105">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </a>
            <?php else: ?>
                <a href="login.php" class="transition hover:text-yellow-300 transform hover:scale-105 <?php echo active_link('login.php'); ?>">
                    <i class="fas fa-sign-in-alt mr-1"></i> Login
                </a>
                <a href="register.php" class="transition hover:text-yellow-300 transform hover:scale-105 <?php echo active_link('register.php'); ?>">
                    <i class="fas fa-user-plus mr-1"></i> Register
                </a>
            <?php endif; ?>
        </div>

        <!-- Mobile Toggle Button -->
        <div class="md:hidden">
            <button id="mobile-menu-button" class="focus:outline-none">
                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-green-700 mt-2 rounded">
        <div class="px-4 py-2 space-y-2">

            <!-- Mobile Search -->
            <?php if (!$hide_search): ?>
                <form action="search.php" method="GET" class="flex items-center gap-2 mb-4">
                    <input 
                        type="text" 
                        name="q" 
                        placeholder="Search food..."
                        class="w-full px-3 py-2 rounded-lg border border-white focus:outline-none focus:ring-2 focus:ring-yellow-400 text-black"
                    >
                    <button type="submit" class="bg-yellow-400 text-black px-3 py-2 rounded-lg hover:bg-yellow-500">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            <?php endif; ?>

            <?php
            $mobile_links = [
                "dashboard.php" => "Dashboard",
                "messages.php" => "Messages",
                "order_food.php" => "Order Food",
                "saved_items.php" => "Saved Items",
                "exchange_list.php" => "Exchanges",
                "my.php" => "Track Order",
                "notifications.php" => "Notifications",
                "profile.php" => "Profile",
                "logout.php" => "Logout"
            ];

            $guest_links = [
                "login.php" => "Login",
                "register.php" => "Register"
            ];

            $links_to_render = isset($_SESSION['user_id']) ? $mobile_links : $guest_links;

            foreach ($links_to_render as $file => $label): ?>
                <a href="<?= $file ?>" class="block px-4 py-2 rounded hover:bg-yellow-400 hover:text-black transition <?php echo active_link($file); ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>


<script>
    // Mobile menu toggle
    document.getElementById('mobile-menu-button').addEventListener('click', function () {
        document.getElementById('mobile-menu').classList.toggle('hidden');
    });
</script>
</body>
</html>
