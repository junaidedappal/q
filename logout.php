<?php
// Initialize the session
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
 
// Unset all of the session variables
$_SESSION = array();
 
// Destroy the session.
session_destroy();

setcookie(session_name(), '', time() - 42000, '/');
 
// Redirect to login page
header("location: login.php");
exit;
?>