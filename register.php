<?php
require_once "dbcon.php"; // Your database connection
session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = $_POST['role']; // 'student' or 'teacher'
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password']; // No hashing as per request

    // Validate inputs
    if (empty($role) || empty($full_name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 6) { // Basic password length check
        $error = "Password must be at least 6 characters long.";
    } else {
        $db->begin_transaction();
        try {
            // Check if email (account) already exists in login
            $stmt_check = $db->prepare("SELECT account FROM login WHERE account = ?");
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $error = "This email is already registered. Please login or use a different email.";
                $db->rollback(); // Rollback transaction
            } else {
                $stmt_check->close();

                // 1. Create a preliminary login record to get an auto-increment ID for stud_id/t_id generation
                // The user_id field will be updated later.
                $stmt_login_pre = $db->prepare("INSERT INTO login (account, password, role) VALUES (?, ?, ?)");
                $stmt_login_pre->bind_param("sss", $email, $password, $role);
                $stmt_login_pre->execute();
                $login_ai_id = $db->insert_id; // This is the auto-incremented 'id' from the login table
                $stmt_login_pre->close();

                $entity_id = '';

                // 2. Create role-specific record (student or teacher)
                if ($role == 'student') {
                    $entity_id = 'S' . str_pad($login_ai_id, 3, '0', STR_PAD_LEFT);
                    $stmt_entity = $db->prepare("INSERT INTO students (stud_id, stud_name, email, age, sex, year) VALUES (?, ?, ?, NULL, 'M', 1)");
                    // Default age to NULL, sex to 'M', year to '1'. Admin can update later.
                    $stmt_entity->bind_param("sss", $entity_id, $full_name, $email);
                } elseif ($role == 'teacher') {
                    $entity_id = 'T' . str_pad($login_ai_id, 3, '0', STR_PAD_LEFT);
                    $stmt_entity = $db->prepare("INSERT INTO teachers (t_id, t_name, email, major) VALUES (?, ?, ?, NULL)");
                    // Default major to NULL. Admin can update later.
                    $stmt_entity->bind_param("sss", $entity_id, $full_name, $email);
                }
                $stmt_entity->execute();
                $stmt_entity->close();

                // 3. Update the login record with the generated entity_id (stud_id or t_id)
                $stmt_login_update = $db->prepare("UPDATE login SET user_id = ? WHERE id = ?");
                $stmt_login_update->bind_param("si", $entity_id, $login_ai_id);
                $stmt_login_update->execute();
                $stmt_login_update->close();

                $db->commit();
                $success = "Registration successful! You can now <a href='login.php'>login</a>.";
            }
        } catch (mysqli_sql_exception $e) {
            $db->rollback();
            if ($e->getCode() == 1062) { // Duplicate entry
                $error = "Registration failed: This email or generated ID might already exist. Please try a different email.";
            } else {
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - School Management</title>
    <link rel="stylesheet" href="css/login_register.css"> <!-- You might want a dedicated CSS -->
</head>
<body>
    <div class="form-container card">
        <h2>Create Account</h2>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        
        <?php if (!$success): // Hide form on success ?>
        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="role">I am a:</label>
                <select id="role" name="role" required>
                    <option value="">-- Select Role --</option>
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                </select>
            </div>
            <div class="form-group">
                <label for="full_name">Full Name:</label>
                <input type="text" id="full_name" name="full_name" required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="email">Email (for login):</label>
                <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" minlength="6" required>
            </div>
            <button type="submit">Register</button>
        </form>
        <?php endif; ?>
        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
</body>
</html>

<style>
/* Basic styling for login_register.css - can be moved to a separate file */
body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box;}
.form-container.card { background: white; padding: 25px 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
.form-container h2 { text-align: center; color: #333; margin-bottom: 20px; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
.form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
.form-group input:focus, .form-group select:focus { border-color: #007bff; outline: none; }
button[type="submit"] { width: 100%; padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background-color 0.3s ease; }
button[type="submit"]:hover { background-color: #0056b3; }
.alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; }
.alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.form-container p { text-align: center; margin-top: 20px; }
.form-container p a { color: #007bff; text-decoration: none; }
.form-container p a:hover { text-decoration: underline; }
</style>