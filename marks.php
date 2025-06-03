<?php
session_start();
require_once "dbcon.php";

// Verify teacher or admin access
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'teacher' && $_SESSION['role'] != 'admin')) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';
$mark = ['mark_id' => '', 'student_id' => '', 'course_id' => '', 'grade' => '']; // Initialize for form

// Get courses (for dropdown)
$courses = [];
if ($_SESSION['role'] == 'teacher') {
    // Teachers only see their own courses
    $stmt = $db->prepare("SELECT c_id, c_name FROM courses WHERE teacher_id = ?");
    $stmt->bind_param("s", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $courses = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    // Admins see all courses
    $result = $db->query("SELECT c_id, c_name FROM courses ORDER BY c_name");
    if ($result) {
        $courses = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// If 'edit' is in GET parameters and it's a GET request, load the mark data for editing
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['edit'])) {
    $edit_mark_id = trim($_GET['edit']);
    if (!empty($edit_mark_id) && is_numeric($edit_mark_id)) {
        $stmt = $db->prepare("SELECT * FROM marks WHERE mark_id=?");
        if ($stmt) {
            $stmt->bind_param("i", $edit_mark_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $mark = $result->fetch_assoc();
            } else {
                $error = "Mark not found for editing.";
                unset($_GET['edit']);
            }
            $stmt->close();
        } else {
            $error = "Error preparing edit statement: " . $db->error;
        }
    } else {
        $error = "Invalid mark ID for editing.";
        unset($_GET['edit']);
    }
}
// Handle form submissions (POST requests)
elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add/Edit Mark
    if (isset($_POST['save_mark'])) {
        $student_id = trim($_POST['student_id']);
        $course_id = trim($_POST['course_id']);
        $grade = trim($_POST['grade']);
        $original_id = trim($_POST['original_id']); // Used to identify if it's an update

        // Validate inputs
        if (empty($student_id) || empty($course_id) || empty($grade)) {
            $error = "All fields are required";
        } elseif (!is_numeric($grade) || $grade < 0 || $grade > 100) {
            $error = "Grade must be a number between 0 and 100";
        }

        if (empty($error)) {
            try {
                // Check if adding new or editing
                if (empty($original_id)) {
                    // Add new mark
                    $stmt = $db->prepare("INSERT INTO marks (student_id, course_id, grade) VALUES (?, ?, ?)");
                    $stmt->bind_param("ssd", $student_id, $course_id, $grade);
                } else {
                    // Update existing
                    $stmt = $db->prepare("UPDATE marks SET student_id=?, course_id=?, grade=? WHERE mark_id=?");
                    $stmt->bind_param("ssdi", $student_id, $course_id, $grade, $original_id);
                }
                
                if ($stmt) {
                    if ($stmt->execute()) {
                        $success = "Mark saved successfully!";
                        // Clear form fields after successful save if it was an add operation
                        if (empty($original_id)) {
                             $mark = ['mark_id' => '', 'student_id' => '', 'course_id' => '', 'grade' => ''];
                        } else { // If it was an update, reload the (potentially changed) mark data
                            $_GET['edit'] = $original_id; // Keep in edit mode
                            // Optionally re-fetch to show updated data, or rely on current $mark if no ID changed
                        }
                    } else {
                        $error = "Error saving mark: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Error preparing save statement: " . $db->error;
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
        // If there was an error (validation or save), repopulate $mark for the form
        if (!empty($error)) {
            $mark['student_id'] = $student_id;
            $mark['course_id'] = $course_id;
            $mark['grade'] = $grade;
            $mark['mark_id'] = $original_id; // Keep original_id if it was an edit attempt
        }
    }
    // Delete Mark
    elseif (isset($_POST['delete_mark'])) {
        $mark_id = trim($_POST['mark_id']);

        if (!empty($mark_id) && is_numeric($mark_id)) {
            $stmt = $db->prepare("DELETE FROM marks WHERE mark_id=?");
            if ($stmt) {
                $stmt->bind_param("i", $mark_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success = "Mark deleted successfully!";
                    } else {
                        $error = "Mark not found or no changes made.";
                    }
                } else {
                    $error = "Error deleting mark: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Error preparing delete statement: " . $db->error;
            }
        } else {
            $error = "Invalid Mark ID for deletion.";
        }
    }
}

// Fetch marks with student and course names
$marks = [];
$query = "
    SELECT m.mark_id, m.grade, 
           s.stud_id as student_id, s.stud_name as student_name,
           c.c_id as course_id, c.c_name as course_name
    FROM marks m
    JOIN students s ON m.student_id = s.stud_id
    JOIN courses c ON m.course_id = c.c_id
";

// Teachers only see their own courses' marks
if ($_SESSION['role'] == 'teacher') {
    $query .= " WHERE c.teacher_id = ?";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("s", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) $marks = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    // Admins see all marks
    $result = $db->query($query);
    if ($result) {
        $marks = $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks Management</title>
    <link rel="stylesheet" href="css/students.css">
    <style>
        .grade-A { background-color: #dff0d8; }
        .grade-B { background-color: #d9edf7; }
        .grade-C { background-color: #fcf8e3; }
        .grade-D { background-color: #f2dede; }
        .grade-F { background-color: #f2dede; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <?php require_once "sidebar.php"; ?>

        <!-- Main Content -->
        <div class="main-content">
            <h1><?php echo $_SESSION['role'] == 'teacher' ? 'My Course Marks' : 'Marks Management'; ?></h1>
            
            <!-- Status Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Marks Form -->
            <div class="card">
                <h2><?php echo empty($_GET['edit']) ? 'Add New Mark' : 'Edit Mark'; ?></h2>
                <form method="POST">
                    <input type="hidden" name="original_id" value="<?php echo isset($_GET['edit']) ? htmlspecialchars($mark['mark_id']) : ''; ?>">
                    
                    <div class="form-group">
                        <label for="student_id">Student</label>
                        <select id="student_id" name="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php 
                            $students = $db->query("SELECT stud_id, stud_name FROM students ORDER BY stud_name");
                            while ($s = $students->fetch_assoc()): 
                            ?>
                                <option value="<?php echo htmlspecialchars($s['stud_id']); ?>"
                                    <?php echo ($mark['student_id'] == $s['stud_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['stud_name']); ?> (<?php echo htmlspecialchars($s['stud_id']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_id">Course</label>
                        <select id="course_id" name="course_id" required>
                            <option value="">-- Select Course --</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['c_id']); ?>"
                                    <?php echo ($mark['course_id'] == $c['c_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['c_name']); ?> (<?php echo htmlspecialchars($c['c_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="grade">Grade (0-100)</label>
                        <input type="number" id="grade" name="grade" min="0" max="100" step="0.01"
                               value="<?php echo htmlspecialchars($mark['grade']); ?>" required>
                    </div>
                    
                    <button type="submit" name="save_mark">Save Mark</button>
                    <?php if (!empty($_GET['edit'])): ?>
                        <a href="marks.php" class="btn">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Marks List -->
            <div class="card">
                <h2>Mark Records</h2>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search marks...">
                </div>
                
                <table id="marksTable">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Grade</th>
                            <th>Letter</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($marks as $m): 
                            $letter_grade = '';
                            $grade_class = '';
                            if ($m['grade'] >= 90) {
                                $letter_grade = 'A';
                                $grade_class = 'grade-A';
                            } elseif ($m['grade'] >= 80) {
                                $letter_grade = 'B';
                                $grade_class = 'grade-B';
                            } elseif ($m['grade'] >= 70) {
                                $letter_grade = 'C';
                                $grade_class = 'grade-C';
                            } elseif ($m['grade'] >= 60) {
                                $letter_grade = 'D';
                                $grade_class = 'grade-D';
                            } else {
                                $letter_grade = 'F';
                                $grade_class = 'grade-F';
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['student_name']); ?> (<?php echo htmlspecialchars($m['student_id']); ?>)</td>
                                <td><?php echo htmlspecialchars($m['course_name']); ?> (<?php echo htmlspecialchars($m['course_id']); ?>)</td>
                                <td class="<?php echo $grade_class; ?>"><?php echo htmlspecialchars($m['grade']); ?></td>
                                <td class="<?php echo $grade_class; ?>"><?php echo $letter_grade; ?></td>
                                <td>
                                    <a href="marks.php?edit=<?php echo urlencode($m['mark_id']); ?>" 
                                       class="btn btn-edit">Edit</a>
                                    <?php if ($_SESSION['role'] == 'admin'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="mark_id" value="<?php echo htmlspecialchars($m['mark_id']); ?>">
                                            <button type="submit" name="delete_mark" class="btn btn-danger" 
                                                    onclick="return confirm('Delete this mark?')">Delete</button>
                                        </form>
                                    <?php endif; ?>
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
            const rows = document.querySelectorAll('#marksTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
<?php $db->close(); ?>