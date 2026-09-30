<?php
$session_lifetime = 30 * 24 * 60 * 60;
ini_set('session.gc_maxlifetime', (string) $session_lifetime);
session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
require 'includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Use password_verify to check the hashed password
        if (password_verify($password, $user['password'])) {
            // Password is correct, so start a new session
            $_SESSION['loggedin'] = true;
            $_SESSION['id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            // Redirect to the dashboard
            header("location: dashboard.php");
        } else {
            // Password is not valid
            echo "Invalid password.";
        }
    } else {
        // No user found with that email
        echo "No user found with that email.";
    }
    $stmt->close();
}
$conn->close();
?>