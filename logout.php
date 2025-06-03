<?php
session_start();
$_SESSION = array();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>