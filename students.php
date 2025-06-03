<?php
require_once "dbcon.php";
session_start();

// Verify admin access
if ($_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';
$student = ['stud_id' => '', 'stud_name' => '', 'email' => '', 'age' => '', 'sex' => 'M', 'year' => '1'];
 
// If 'edit' is in GET parameters and it's a GET request, load the student data for editing
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['edit'])) {
    $edit_stud_id = trim($_GET['edit']);
    if (!empty($edit_stud_id)) {
        $stmt = $db->prepare("SELECT * FROM students WHERE stud_id=?");
        $stmt->bind_param("s", $edit_stud_id);
        $stmt->execute();
        $result = $stmt->get_result();
 
        if ($result->num_rows == 1) {
            $student = $result->fetch_assoc();
        } else {
            $error = "Student not found for editing.";
            unset($_GET['edit']);
        }
        $stmt->close();
    } else {
        unset($_GET['edit']);
    }
}
 
// Handle form submissions (POST requests)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add/Edit Student
    if (isset($_POST['save_student'])) {
        $stud_id = trim($_POST['stud_id']);
        $stud_name = trim($_POST['stud_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password']; // Keep plain, no trim needed for password itself
        $age = trim($_POST['age']);
        $sex = trim($_POST['sex']);
        $year = trim($_POST['year']);
        $original_id = trim($_POST['original_id']); // Used to identify if it's an update
 
        // Validate inputs
        if (empty($stud_id) || empty($stud_name)) {
            $error = "Student ID and Name are required.";
        } elseif (empty($email) && empty($original_id)) { // Email required for new students
            $error = "Email is required for new students.";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } elseif (empty($original_id) && empty($password)) { // Password required for new students
            $error = "Password is required for new students.";
        } elseif (!empty($password) && strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            $db->begin_transaction();
            try {
                // Check for email uniqueness in login table if email is provided/changed
                if (!empty($email)) {
                    $stmt_check_email = $db->prepare("SELECT id FROM login WHERE account = ? AND (user_id != ? OR user_id IS NULL)");
                    $stmt_check_email->bind_param("ss", $email, $original_id); // Exclude current student if editing
                    $stmt_check_email->execute();
                    if ($stmt_check_email->get_result()->num_rows > 0) {
                        $error = "This email is already registered to another account.";
                    }
                    $stmt_check_email->close();
                }
                
                // Check for stud_id uniqueness if adding or if stud_id is changed during edit
                if (empty($original_id) || ($original_id != $stud_id)) {
                    $stmt_check_stud_id = $db->prepare("SELECT stud_id FROM students WHERE stud_id = ?");
                    $stmt_check_stud_id->bind_param("s", $stud_id);
                    $stmt_check_stud_id->execute();
                    if ($stmt_check_stud_id->get_result()->num_rows > 0) {
                        $error = "This Student ID already exists.";
                    }
                    $stmt_check_stud_id->close();
                }

                if ($error) { // If any validation error occurred
                    $db->rollback();
                } else {
                    // Save to students table
                    if (empty($original_id)) {
                        $stmt_student = $db->prepare("INSERT INTO students (stud_id, stud_name, email, age, sex, year) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt_student->bind_param("sssisi", $stud_id, $stud_name, $email, $age, $sex, $year);
                    } else {
                        $stmt_student = $db->prepare("UPDATE students SET stud_id=?, stud_name=?, email=?, age=?, sex=?, year=? WHERE stud_id=?");
                        $stmt_student->bind_param("ssssiss", $stud_id, $stud_name, $email, $age, $sex, $year, $original_id);
                    }
                    $stmt_student->execute();
                    $stmt_student->close();

                    // Save/Update login table
                    if (empty($original_id)) { // New student
                        $stmt_login = $db->prepare("INSERT INTO login (account, password, role, user_id) VALUES (?, ?, 'student', ?)");
                        $stmt_login->bind_param("sss", $email, $password, $stud_id);
                        $stmt_login->execute();
                        $stmt_login->close();
                    } else { // Existing student
                        $login_update_fields = [];
                        $login_update_params = [];
                        $login_update_types = "";

                        // Fetch current student's email to see if it changed
                        $current_student_data_stmt = $db->prepare("SELECT email FROM students WHERE stud_id = ?");
                        $current_student_data_stmt->bind_param("s", $original_id);
                        $current_student_data_stmt->execute();
                        $current_student_db_email = $current_student_data_stmt->get_result()->fetch_assoc()['email'];
                        $current_student_data_stmt->close();

                        if (!empty($email) && $email !== $current_student_db_email) {
                            $login_update_fields[] = "account = ?";
                            $login_update_params[] = $email;
                            $login_update_types .= "s";
                        }
                        if (!empty($password)) {
                            $login_update_fields[] = "password = ?";
                            $login_update_params[] = $password;
                            $login_update_types .= "s";
                        }
                        if ($stud_id !== $original_id) { // If stud_id itself was changed
                             $login_update_fields[] = "user_id = ?";
                             $login_update_params[] = $stud_id;
                             $login_update_types .= "s";
                        }

                        if (!empty($login_update_fields)) {
                            $login_update_params[] = $original_id; // For WHERE user_id = ?
                            $login_update_types .= "s";
                            $sql_login_update = "UPDATE login SET " . implode(", ", $login_update_fields) . " WHERE user_id = ? AND role = 'student'";
                            $stmt_login_update = $db->prepare($sql_login_update);
                            $stmt_login_update->bind_param($login_update_types, ...$login_update_params);
                            $stmt_login_update->execute();
                            $stmt_login_update->close();
                        }
                    }
                    $db->commit();
                    $success = "Student record saved successfully!";
                    // Clear form if it was an add operation
                    if (empty($original_id)) {
                        $student = ['stud_id' => '', 'stud_name' => '', 'email' => '', 'age' => '', 'sex' => 'M', 'year' => '1'];
                    } else {
                         // If editing, update $student array to reflect changes for the form
                        $student['stud_id'] = $stud_id;
                        $student['stud_name'] = $stud_name;
                        $student['email'] = $email;
                        $student['age'] = $age;
                        $student['sex'] = $sex;
                        $student['year'] = $year;
                    }
                }
            } catch (mysqli_sql_exception $e) {
                $db->rollback();
                if ($e->getCode() == 1062) { // Duplicate entry
                    $error = "Error: Student ID or Email already exists.";
                } else {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
        // If there was any error (validation or DB), repopulate $student for the form
        if (!empty($error)) {
            $student['stud_id'] = $stud_id;
            $student['stud_name'] = $stud_name;
            $student['email'] = $email;
            // Password is not repopulated for security
            $student['age'] = $age;
            $student['sex'] = $sex;
            $student['year'] = $year;
        }
    }
    // Delete Student
    elseif (isset($_POST['delete_student'])) {
        $stud_id_to_delete = trim($_POST['stud_id']);
        $db->begin_transaction();
        try {
            // Delete from login table first
            $stmt_login_delete = $db->prepare("DELETE FROM login WHERE user_id = ? AND role = 'student'");
            $stmt_login_delete->bind_param("s", $stud_id_to_delete);
            $stmt_login_delete->execute();
            $stmt_login_delete->close();

            // Then delete from students table
            // The ON DELETE CASCADE on marks table will handle associated marks.
            $stmt_student_delete = $db->prepare("DELETE FROM students WHERE stud_id = ?");
            $stmt_student_delete->bind_param("s", $stud_id_to_delete);
            $stmt_student_delete->execute();
            
            if ($stmt_student_delete->affected_rows > 0) {
                $db->commit();
                $success = "Student and associated login deleted successfully!";
            } else {
                $db->rollback();
                $error = "Student not found or no changes made.";
            }
            $stmt_student_delete->close();

        } catch (mysqli_sql_exception $e) {
            $db->rollback();
            // While ON DELETE CASCADE handles marks, other unexpected FK issues could arise.
            $error = "Error deleting student: " . $e->getMessage();
        }
    }    
}

// Fetch all students
$students = [];
$result = $db->query("SELECT stud_id, stud_name, email, age, sex, year FROM students ORDER BY stud_name");
if ($result) {
    $students = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management</title>
    <link rel="stylesheet" href="css/students.css">
</head>
<body>
    <div class="container">
        <?php require_once 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <h1>Student Management</h1>
            
            <!-- Status Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Student Form -->
            <div class="card">
                <h2><?php echo empty($_GET['edit']) ? 'Add New Student' : 'Edit Student'; ?></h2>
                <form method="POST" action="students.php<?php echo isset($_GET['edit']) ? '?edit=' . htmlspecialchars($_GET['edit']) : ''; ?>">
                    <input type="hidden" name="original_id" value="<?php echo isset($_GET['edit']) ? htmlspecialchars($student['stud_id']) : ''; ?>">
                    
                    <div class="form-group">
                        <label for="stud_id">Student ID</label>
                        <input type="text" id="stud_id" name="stud_id" 
                               value="<?php echo htmlspecialchars($student['stud_id']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="stud_name">Full Name</label>
                        <input type="text" id="stud_name" name="stud_name" 
                               value="<?php echo htmlspecialchars($student['stud_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email (for login)</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" <?php echo empty($_GET['edit']) ? 'required' : ''; ?>>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" minlength="6" 
                               placeholder="<?php echo isset($_GET['edit']) ? 'Leave blank to keep current' : 'Required'; ?>" 
                               <?php echo empty($_GET['edit']) ? 'required' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="age">Age</label>
                        <input type="number" id="age" name="age" min="10" max="99"
                               value="<?php echo htmlspecialchars($student['age']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="sex">Gender</label>
                        <select id="sex" name="sex">
                            <option value="M" <?php echo ($student['sex'] == 'M') ? 'selected' : ''; ?>>Male</option>
                            <option value="F" <?php echo ($student['sex'] == 'F') ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="year">Year</label>
                        <select id="year" name="year">
                            <?php for ($i = 1; $i <= 5; $i++): // Assuming up to 5 years ?>
                                <option value="<?php echo $i; ?>" 
                                    <?php echo ($student['year'] == $i) ? 'selected' : ''; ?>>
                                    Year <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="save_student">Save Student</button>
                    <?php if (!empty($_GET['edit'])): ?>
                        <a href="students.php" class="btn">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Student List -->
            <div class="card">
                <h2>Student Records</h2>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search students by name, ID, or email...">
                </div>
                
                <table id="studentTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Year</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['stud_id']); ?></td>
                                <td><?php echo htmlspecialchars($s['stud_name']); ?></td>
                                <td><?php echo htmlspecialchars($s['email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($s['age']); ?></td>
                                <td><?php echo ($s['sex'] == 'M') ? 'Male' : 'Female'; ?></td>
                                <td>Year <?php echo htmlspecialchars($s['year']); ?></td>
                                <td class="btn-container">
                                    <a href="students.php?edit=<?php echo urlencode($s['stud_id']); ?>
                                        " class="btn btn-edit">Edit</a>
                                    <form method="POST" action="students.php" style="display:inline;">
                                        <input type="hidden" name="stud_id" value="<?php echo htmlspecialchars($s['stud_id']); ?>">
                                        <button type="submit" name="delete_student" class="btn btn-danger" 
                                                onclick="return confirm('Are you sure you want to delete this student and their login account?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="7" style="text-align:center;">No students found.</td></tr>
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
            const rows = document.querySelectorAll('#studentTable tbody tr');
            
            rows.forEach(row => {
                const cells = row.getElementsByTagName('td');
                let textContent = '';
                // Concatenate text from relevant cells for searching (ID, Name, Email)
                if(cells.length > 2) { // Ensure cells exist
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