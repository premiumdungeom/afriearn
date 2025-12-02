<?php
// index.php - Redirect to login or dashboard
session_start();

// Check if user is logged in
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit();
?>
