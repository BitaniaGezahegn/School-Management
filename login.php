<?php
require_once "dbcon.php"; // Your database connection file
session_start(); // Start session at the very beginning

// Initialize variables
$error = '';
$account = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $account = trim($_POST['account']);
    $password = trim($_POST['password']);

    // Validate inputs
    if (empty($account) || empty($password)) {
        $error = "Please enter both account and password";
    } else {
        // Prepare SQL (prevent SQL injection)
        // Include 'status' if you implement account approval later
        $stmt = $db->prepare("SELECT password, role, user_id, status FROM login WHERE account = ?");
        $stmt->bind_param("s", $account);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            // Optional: Check for account status (if you implement 'pending' status)
            // if ($user['status'] === 'pending') {
            //     $error = "Your account is pending approval. Please contact an administrator.";
            // } else
            if ($password === $user['password']) {
                // Start secure session
                session_regenerate_id(true);
                
                $_SESSION['loggedin'] = true;
                $_SESSION['account'] = $account;
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_id'] = $user['user_id'];

                // Fetch user's full name
                if ($user['role'] == 'student' && !empty($user['user_id'])) {
                    $name_stmt = $db->prepare("SELECT stud_name FROM students WHERE stud_id = ?");
                } elseif ($user['role'] == 'teacher' && !empty($user['user_id'])) {
                    $name_stmt = $db->prepare("SELECT t_name FROM teachers WHERE t_id = ?");
                }
                if (isset($name_stmt)) {
                    $name_stmt->bind_param("s", $user['user_id']);
                    $name_stmt->execute();
                    $name_result = $name_stmt->get_result()->fetch_assoc();
                    $_SESSION['full_name'] = $name_result[$user['role'] == 'student' ? 'stud_name' : 't_name'] ?? 'User';
                    $name_stmt->close();
                    
                }
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: index.php");
                        break;
                    case 'teacher':
                        header("Location: marks.php");
                        break;
                    case 'student':
                        header("Location: grades.php");
                        break;
                    default:
                        $error = "Invalid role assigned";
                }
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "Account not found";
        }
        $stmt->close();
    }
}
// It's generally better to close the DB connection at the end of the script,
// or let PHP handle it, especially if other includes might need it.
// $db->close(); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - School Management</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-card">
        <h2>School Management Login</h2>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form action="login.php" method="post">
            <input 
                type="text" 
                name="account" 
                placeholder="Enter your email address" 
                value="<?php echo htmlspecialchars($account); ?>" 
                required
            >
            <input 
                type="password" 
                name="password" 
                placeholder="Enter your password" 
                required
            >
            <button type="submit">Login</button>
        </form>
        <p style="text-align: center; margin-top: 20px;">
            Don't have an account? <a href="register.php">Register here</a>
        </p>
    </div>
</body>
</html>
<?php if (isset($db) && $db instanceof mysqli) $db->close(); ?>