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
$course = ['c_id' => '', 'c_name' => '', 'department' => '', 'teacher_id' => '']; // Initialize for form

// Get all teachers for dropdown
$teachers = [];
$teacher_result = $db->query("SELECT t_id, t_name FROM teachers ORDER BY t_name");
if ($teacher_result) {
    $teachers = $teacher_result->fetch_all(MYSQLI_ASSOC);
    $teacher_result->free();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add/Edit Course
    if (isset($_POST['save_course'])) {
        $c_id = trim($_POST['c_id']);
        $c_name = trim($_POST['c_name']);
        $department = trim($_POST['department']);
        $teacher_id = trim($_POST['teacher_id']);

        // Validate inputs
        if (empty($c_id) || empty($c_name)) {
            $error = "Course ID and Name are required";
            // Repopulate $course with submitted data to show back to the user
            $course['c_id'] = $c_id;
            $course['c_name'] = $c_name;
            $course['department'] = $department;
            $course['teacher_id'] = $teacher_id;
            // Note: original_id is handled by the hidden input value
        } else {
            try {
                // Check if adding new or editing
                if (empty($_POST['original_id'])) {
                    // Add new course
                    $stmt = $db->prepare("INSERT INTO courses (c_id, c_name, department, teacher_id) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $c_id, $c_name, $department, $teacher_id);
                } else {
                    // Update existing
                    $stmt = $db->prepare("UPDATE courses SET c_id=?, c_name=?, department=?, teacher_id=? WHERE c_id=?");
                    $stmt->bind_param("sssss", $c_id, $c_name, $department, $teacher_id, $_POST['original_id']);
                }
                
                if ($stmt->execute()) {
                    $success = "Course saved successfully!";
                } else {
                    $error = "Error saving course: " . $db->error;
                }
                $stmt->close();
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
            // If there was an error during save, repopulate $course for the form
            if (!empty($error)) {
                $course['c_id'] = $c_id;
                $course['c_name'] = $c_name;
                $course['department'] = $department;
                $course['teacher_id'] = $teacher_id;
            }
        }
    }
    // Delete Course
    elseif (isset($_POST['delete_course'])) {
        $c_id = trim($_POST['c_id']);
        $stmt = $db->prepare("DELETE FROM courses WHERE c_id=?");

        if ($stmt) {
            $stmt->bind_param("s", $c_id);
            try {
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success = "Course deleted successfully!";
                    } else {
                        // This case handles if the c_id doesn't exist or was already deleted.
                        $error = "Course not found or no changes made.";
                    }
                } else {
                    // This else might not be reached if execute() throws an exception for all errors.
                    $error = "Error deleting course: " . $stmt->error;
                }
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1451) { // MySQL error code for foreign key constraint violation
                    $error = "Cannot delete this course as it is assigned to students (e.g., in the marks table). Please update or remove associated student marks first.";
                } else {
                    $error = "Database error deleting course: " . $e->getMessage() . " (Code: " . $e->getCode() . ")";
                }
            } finally {
                $stmt->close();
            }
        } else {
            $error = "Error preparing delete statement: " . $db->error;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['edit'])) {
    // Edit Course (load data for GET request)
    $edit_c_id = trim($_GET['edit']);
    if (!empty($edit_c_id)) {
        $stmt = $db->prepare("SELECT * FROM courses WHERE c_id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $edit_c_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $course = $result->fetch_assoc();
            } else {
                $error = "Course not found for editing.";
                // Reset GET edit parameter so the form doesn't stay in edit mode
                unset($_GET['edit']); 
            }
            $stmt->close();
        } else {
             $error = "Error preparing edit statement: " . $db->error;
        }
    } else {
         // Reset GET edit parameter if it was empty
         unset($_GET['edit']);
    }
}

// Fetch all courses with teacher names
$courses = [];
$result = $db->query("
    SELECT c.*, t.t_name as teacher_name 
    FROM courses c
    LEFT JOIN teachers t ON c.teacher_id = t.t_id
    ORDER BY c.c_name
");
if ($result) {
    $courses = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management</title>
    <link rel="stylesheet" href="css/students.css">
</head>
<body>
    <div class="container">
        <?php require_once 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <h1>Course Management</h1>
            
            <!-- Status Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Course Form -->
            <div class="card">
                <h2><?php echo empty($_GET['edit']) ? 'Add New Course' : 'Edit Course'; ?></h2>
                <form method="POST">
                    <input type="hidden" name="original_id" value="<?php echo isset($_GET['edit']) ? htmlspecialchars($course['c_id']) : ''; ?>">
                    
                    <div class="form-group">
                        <label for="c_id">Course ID</label>
                        <input type="text" id="c_id" name="c_id" 
                               value="<?php echo htmlspecialchars($course['c_id']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="c_name">Course Name</label>
                        <input type="text" id="c_name" name="c_name" 
                               value="<?php echo htmlspecialchars($course['c_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" 
                               value="<?php echo htmlspecialchars($course['department']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="teacher_id">Assigned Teacher</label>
                        <select id="teacher_id" name="teacher_id">
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['t_id']); ?>"
                                    <?php echo ($course['teacher_id'] == $t['t_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['t_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="save_course">Save Course</button>
                    <?php if (!empty($_GET['edit'])): ?>
                        <a href="courses.php" class="btn">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Course List -->
            <div class="card">
                <h2>Course Records</h2>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search courses...">
                </div>
                
                <table id="courseTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Teacher</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['c_id']); ?></td>
                                <td><?php echo htmlspecialchars($c['c_name']); ?></td>
                                <td><?php echo htmlspecialchars($c['department']); ?></td>
                                <td><?php echo htmlspecialchars($c['teacher_name'] ?? 'Unassigned'); ?></td>
                                <td>
                                    <a href="courses.php?edit=<?php echo urlencode($c['c_id']); ?>" 
                                       class="btn btn-edit">Edit</a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="c_id" value="<?php echo htmlspecialchars($c['c_id']); ?>">
                                        <button type="submit" name="delete_course" class="btn btn-danger" 
                                                onclick="return confirm('Delete this course?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Simple client-side search
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const input = this.value.toLowerCase();
            const rows = document.querySelectorAll('#courseTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
<?php $db->close(); ?>