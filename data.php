<!-- <?php
require_once './includes/db.php';
require_once './includes/functions.php';

// Random names for Indian users
$names = [
    'Aarav', 'Vivaan', 'Aditya', 'Vihaan', 'Arjun', 'Sai', 'Ishaan', 'Raghav', 
    'Saanvi', 'Ananya', 'Priya', 'Shreya', 'Diya', 'Pooja', 'Neha', 'Riya', 
    'Madhav', 'Karthik', 'Manav', 'Raj', 'Vikram', 'Nikhil', 'Tanvi', 'Aisha', 
    'Rohan', 'Dev', 'Akash', 'Kavya', 'Kriti', 'Jai'
];

// Check if 'user' role already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE role = 'user' LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    die("User data already exists. Initialization aborted.");
}

// Insert random users
for ($i = 12; $i <= 41; $i++) {
    // Randomly select a name from the list
    $random_name = $names[array_rand($names)];
    
    // Generate random email
    // $email = strtolower($random_name) . rand(100, 999) . "@example.com";
    $email = strtolower($random_name) . ".1@gmail.com";
    
    // Set a default password
    $password = "111111";
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Random address and profile image
    $address = "Address " . rand(1, 100) . " XYZ, Ghaziabad/Noida";
    $profile_picture = "logo.jpeg"; // Default profile picture
    
    // Random latitude and longitude for Ghaziabad/Noida
    $latitude = 28.6139 + (rand(-1000, 1000) / 10000);
    $longitude = 77.2090 + (rand(-1000, 1000) / 10000);

    // Prepare the SQL query to insert user
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, address, profile_picture, latitude, longitude, role, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'user', NOW())");
    $phone = "+91" . rand(7000000000, 9999999999); // Generate random phone number
    $stmt->bind_param("ssssssdds", $random_name, $email, $hashed_password, $phone, $address, $profile_picture, $latitude, $longitude);

    // Execute the query
    if ($stmt->execute()) {
        echo "User {$random_name} created successfully!<br>";
        echo "Email: " . htmlspecialchars($email) . "<br>";
        echo "Password: " . htmlspecialchars($password) . "<br>";
        echo "Phone: " . htmlspecialchars($phone) . "<br>";
        echo "Address: " . htmlspecialchars($address) . "<br><br>";
    } else {
        echo "Error creating user {$random_name}: " . $conn->error . "<br>";
    }
}

$stmt->close();
$conn->close();
?> -->
