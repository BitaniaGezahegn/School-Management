<?php
session_start();
require_once "dbcon.php";

// Verify admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';
$teacher = ['t_id' => '', 't_name' => '', 'email' => '', 'major' => ''];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add/Edit Teacher
    if (isset($_POST['save_teacher'])) {
        $t_id = trim($_POST['t_id']);
        $t_name = trim($_POST['t_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password']; // Keep plain
        $major = trim($_POST['major']);
        $original_id = trim($_POST['original_id']);

        // Validate inputs
        if (empty($t_id) || empty($t_name)) {
            $error = "Teacher ID and Name are required";
        } elseif (empty($email) && empty($original_id)) { // Email required for new teachers
            $error = "Email is required for new teachers.";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif (empty($original_id) && empty($password)) { // Password required for new teachers
            $error = "Password is required for new teachers.";
        } elseif (!empty($password) && strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            $db->begin_transaction();
            try {
                // Check for email uniqueness in login table if email is provided/changed
                if (!empty($email)) {
                    $stmt_check_email = $db->prepare("SELECT id FROM login WHERE account = ? AND (user_id != ? OR user_id IS NULL)");
                    $stmt_check_email->bind_param("ss", $email, $original_id); // Exclude current teacher if editing
                    $stmt_check_email->execute();
                    if ($stmt_check_email->get_result()->num_rows > 0) {
                        $error = "This email is already registered to another account.";
                    }
                    $stmt_check_email->close();
                }

                // Check for t_id uniqueness if adding or if t_id is changed during edit
                if (empty($original_id) || ($original_id != $t_id)) {
                    $stmt_check_t_id = $db->prepare("SELECT t_id FROM teachers WHERE t_id = ?");
                    $stmt_check_t_id->bind_param("s", $t_id);
                    $stmt_check_t_id->execute();
                    if ($stmt_check_t_id->get_result()->num_rows > 0) {
                        $error = "This Teacher ID already exists.";
                    }
                    $stmt_check_t_id->close();
                }

                if ($error) { // If any validation error occurred
                    $db->rollback();
                } else {
                    // Save to teachers table
                    if (empty($original_id)) {
                        $stmt_teacher = $db->prepare("INSERT INTO teachers (t_id, t_name, email, major) VALUES (?, ?, ?, ?)");
                        $stmt_teacher->bind_param("ssss", $t_id, $t_name, $email, $major);
                    } else {
                        $stmt_teacher = $db->prepare("UPDATE teachers SET t_id=?, t_name=?, email=?, major=? WHERE t_id=?");
                        $stmt_teacher->bind_param("sssss", $t_id, $t_name, $email, $major, $original_id);
                    }
                    $stmt_teacher->execute();
                    $stmt_teacher->close();

                    // Save/Update login table
                    if (empty($original_id)) { // New teacher
                        $stmt_login = $db->prepare("INSERT INTO login (account, password, role, user_id) VALUES (?, ?, 'teacher', ?)");
                        $stmt_login->bind_param("sss", $email, $password, $t_id);
                        $stmt_login->execute();
                        $stmt_login->close();
                    } else { // Existing teacher
                        $login_update_fields = [];
                        $login_update_params = [];
                        $login_update_types = "";

                        // Fetch current teacher's email to see if it changed
                        $current_teacher_data_stmt = $db->prepare("SELECT email FROM teachers WHERE t_id = ?");
                        $current_teacher_data_stmt->bind_param("s", $original_id);
                        $current_teacher_data_stmt->execute();
                        $current_teacher_db_email = $current_teacher_data_stmt->get_result()->fetch_assoc()['email'];
                        $current_teacher_data_stmt->close();

                        if (!empty($email) && $email !== $current_teacher_db_email) {
                            $login_update_fields[] = "account = ?";
                            $login_update_params[] = $email;
                            $login_update_types .= "s";
                        }
                        if (!empty($password)) {
                            $login_update_fields[] = "password = ?";
                            $login_update_params[] = $password;
                            $login_update_types .= "s";
                        }
                         if ($t_id !== $original_id) { // If t_id itself was changed
                             $login_update_fields[] = "user_id = ?";
                             $login_update_params[] = $t_id;
                             $login_update_types .= "s";
                        }

                        if (!empty($login_update_fields)) {
                            $login_update_params[] = $original_id; // For WHERE user_id = ?
                            $login_update_types .= "s";
                            $sql_login_update = "UPDATE login SET " . implode(", ", $login_update_fields) . " WHERE user_id = ? AND role = 'teacher'";
                            $stmt_login_update = $db->prepare($sql_login_update);
                            $stmt_login_update->bind_param($login_update_types, ...$login_update_params);
                            $stmt_login_update->execute();
                            $stmt_login_update->close();
                        }
                    }
                    $db->commit();
                    $success = "Teacher record saved successfully!";
                    if (empty($original_id)) {
                        $teacher = ['t_id' => '', 't_name' => '', 'email' => '', 'major' => ''];
                    } else {
                        $teacher['t_id'] = $t_id;
                        $teacher['t_name'] = $t_name;
                        $teacher['email'] = $email;
                        $teacher['major'] = $major;
                    }
                }
            } catch (mysqli_sql_exception $e) {
                $db->rollback();
                if ($e->getCode() == 1062) { // Duplicate entry
                    $error = "Error: Teacher ID or Email already exists.";
                } else {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
        // If there was any error (validation or DB), repopulate $teacher for the form
        if (!empty($error)) {
            $teacher['t_id'] = $t_id;
            $teacher['t_name'] = $t_name;
            $teacher['email'] = $email;
            // Password is not repopulated
            $teacher['major'] = $major;
        }
    }
    // Delete Teacher
    elseif (isset($_POST['delete_teacher'])) {
        $t_id_to_delete = trim($_POST['t_id']);
        $db->begin_transaction();
        try {
            // Delete from login table first
            $stmt_login_delete = $db->prepare("DELETE FROM login WHERE user_id = ? AND role = 'teacher'");
            $stmt_login_delete->bind_param("s", $t_id_to_delete);
            $stmt_login_delete->execute();
            $stmt_login_delete->close();

            // Then delete from teachers table
            // ON DELETE SET NULL for courses.teacher_id will handle courses.
            $stmt_teacher_delete = $db->prepare("DELETE FROM teachers WHERE t_id = ?");
            $stmt_teacher_delete->bind_param("s", $t_id_to_delete);
            $stmt_teacher_delete->execute();

            if ($stmt_teacher_delete->affected_rows > 0) {
                $db->commit();
                $success = "Teacher and associated login deleted successfully!";
            } else {
                $db->rollback();
                $error = "Teacher not found or no changes made.";
            }
            $stmt_teacher_delete->close();

        } catch (mysqli_sql_exception $e) {
            $db->rollback();
            if ($e->getCode() == 1451) { // Foreign key constraint (e.g., if courses.teacher_id was NOT ON DELETE SET NULL)
                $error = "Cannot delete this teacher as they are assigned to one or more courses. Please update or remove the courses associated with this teacher first.";
            } else {
                $error = "Error deleting teacher: " . $e->getMessage();
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['edit'])) {
    $edit_t_id = trim($_GET['edit']);
    if (!empty($edit_t_id)) {
        $stmt = $db->prepare("SELECT * FROM teachers WHERE t_id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $edit_t_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $teacher = $result->fetch_assoc();
            } else {
                $error = "Teacher not found for editing.";
                unset($_GET['edit']);
            }
            $stmt->close();
        } else {
            $error = "Error preparing edit statement: " . $db->error;
        }
    } else {
        unset($_GET['edit']);
    }
}

// Fetch all teachers
$teachers = [];
$result = $db->query("SELECT t_id, t_name, email, major FROM teachers ORDER BY t_name");
if ($result) {
    $teachers = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Management</title>
    <link rel="stylesheet" href="css/students.css">
</head>
<body>
    <div class="container">
        <?php require_once "sidebar.php"; ?>

        <!-- Main Content -->
        <div class="main-content">
            <h1>Teacher Management</h1>
            
            <!-- Status Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Teacher Form -->
            <div class="card">
                <h2><?php echo empty($_GET['edit']) ? 'Add New Teacher' : 'Edit Teacher'; ?></h2>
                <form method="POST" action="teachers.php<?php echo isset($_GET['edit']) ? '?edit=' . htmlspecialchars($_GET['edit']) : ''; ?>">
                    <input type="hidden" name="original_id" value="<?php echo isset($_GET['edit']) ? htmlspecialchars($teacher['t_id']) : ''; ?>">

                    <div class="form-group">
                        <label for="t_id">Teacher ID</label>
                        <input type="text" id="t_id" name="t_id" 
                               value="<?php echo htmlspecialchars($teacher['t_id']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="t_name">Full Name</label>
                        <input type="text" id="t_name" name="t_name" 
                               value="<?php echo htmlspecialchars($teacher['t_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email (for login)</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($teacher['email'] ?? ''); ?>" <?php echo empty($_GET['edit']) ? 'required' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" minlength="6" 
                               placeholder="<?php echo isset($_GET['edit']) ? 'Leave blank to keep current' : 'Required'; ?>" 
                               <?php echo empty($_GET['edit']) ? 'required' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <label for="major">Major/Department</label>
                        <input type="text" id="major" name="major" 
                               value="<?php echo htmlspecialchars($teacher['major']); ?>">
                    </div>
                    
                    <button type="submit" name="save_teacher">Save Teacher</button>
                    <?php if (!empty($_GET['edit'])): ?>
                        <a href="teachers.php" class="btn">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Teacher List -->
            <div class="card">
                <h2>Teacher Records</h2>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search teachers by name, ID, or email...">
                </div>
                
                <table id="teacherTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Major/Department</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $t): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($t['t_id']); ?></td>
                                <td><?php echo htmlspecialchars($t['t_name']); ?></td>
                                <td><?php echo htmlspecialchars($t['email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($t['major'] ?? 'N/A'); ?></td>
                                <td class="btn-container">
                                    <a href="teachers.php?edit=<?php echo urlencode($t['t_id']); ?>" class="btn btn-edit">Edit</a>
                                    <form method="POST" action="teachers.php" style="display:inline;">
                                        <input type="hidden" name="t_id" value="<?php echo htmlspecialchars($t['t_id']); ?>">
                                        <button type="submit" name="delete_teacher" class="btn btn-danger" 
                                                onclick="return confirm('Are you sure you want to delete this teacher and their login account?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($teachers)): ?>
                            <tr><td colspan="5" style="text-align:center;">No teachers found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Simple client-side search
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const input = this.value.toLowerCase();
            const rows = document.querySelectorAll('#teacherTable tbody tr');
            
            rows.forEach(row => {
                const cells = row.getElementsByTagName('td');
                let textContent = '';
                if(cells.length > 2) { // ID, Name, Email
                    textContent = (cells[0].textContent + ' ' + cells[1].textContent + ' ' + cells[2].textContent).toLowerCase();
                } else {
                    textContent = row.textContent.toLowerCase();
                }
                row.style.display = textContent.includes(input) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
<?php if (isset($db) && $db instanceof mysqli) $db->close(); ?>