<?php
require_once 'dbcon.php';

$admin_account_name = 'admin';
$admin_plain_password = 'admin';
$admin_role_name = 'admin';
$admin_user_identifier = 'admin';

// Use explicit column names for clarity and robustness
$stmt = $db->prepare("INSERT INTO login (account, password, role, user_id) VALUES (?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("ssss", $admin_account_name, $admin_plain_password, $admin_role_name, $admin_user_identifier);
    if ($stmt->execute()) {
        echo "Admin user '$admin_account_name' with role '$admin_role_name' created successfully.\n<br>";
        echo "You should now be able to log in with account: '$admin_account_name' and password: '$admin_plain_password'.\n<br>";
    } else {
        echo "Error creating admin user: " . $stmt->error . " (Error Code: " . $db->errno . ")\n<br>";
    }
    $stmt->close();
} else {
    echo "Error preparing insert statement: " . $db->error . "\n<br>";
}

$db->close();
 ?>