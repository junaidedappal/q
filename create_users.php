<?php
require 'config/database.php';
$database = new Database();
$conn = $database->getConnection();

$users = [
    ['Admin User', 'admin@example.com', '1112223331', 'password123', 'admin'],
    ['Sales User', 'sales@example.com', '1112223332', 'password123', 'sales'],
    ['Demo User', 'demo@example.com', '1112223333', 'password123', 'demo'],
    ['Mentor User', 'mentor@example.com', '1112223334', 'password123', 'mentor'],
    ['Teacher User', 'teacher@example.com', '1112223335', 'password123', 'teacher'],
    ['Accounts User', 'accounts@example.com', '1112223336', 'password123', 'accounts']
];

$query = "INSERT INTO users (name, email, phone, password, role) VALUES (:name, :email, :phone, :password, :role)";
$stmt = $conn->prepare($query);

foreach ($users as $user) {
    $hashed_password = password_hash($user[3], PASSWORD_DEFAULT);
    $stmt->bindParam(':name', $user[0]);
    $stmt->bindParam(':email', $user[1]);
    $stmt->bindParam(':phone', $user[2]);
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':role', $user[4]);
    $stmt->execute();
}

echo "Users created successfully!";
?>