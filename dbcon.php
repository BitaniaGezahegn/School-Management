<?php

// Database connection
$db = new mysqli('localhost', 'root', '', 'school_management');
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}