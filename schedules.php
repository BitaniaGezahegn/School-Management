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
$session_data = ['session_id' => '', 'course_id' => '', 'teacher_id' => '', 'room_id' => '', 'day_of_week' => 'Monday', 'start_time' => '', 'end_time' => '', 'semester_term' => ''];

// Fetch data for dropdowns
$courses = $db->query("SELECT c_id, c_name FROM courses ORDER BY c_name")->fetch_all(MYSQLI_ASSOC);
$teachers = $db->query("SELECT t_id, t_name FROM teachers ORDER BY t_name")->fetch_all(MYSQLI_ASSOC);
$rooms = $db->query("SELECT room_id FROM rooms ORDER BY room_id")->fetch_all(MYSQLI_ASSOC);
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$terms_available = ["First Term", "Second Term", "Third Term", "Forth Term"];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_session'])) {
        $session_id_edit = $_POST['original_session_id'] ?? null; // For editing
        $course_id = trim($_POST['course_id']);
        $teacher_id = trim($_POST['teacher_id']) ?: null; // Allow empty teacher
        $room_id = trim($_POST['room_id']);
        $day_of_week = trim($_POST['day_of_week']);
        $start_time = trim($_POST['start_time']);
        $end_time = trim($_POST['end_time']);
        $semester_term = trim($_POST['semester_term']);

        // Repopulate form data in case of error
        $session_data = compact('course_id', 'teacher_id', 'room_id', 'day_of_week', 'start_time', 'end_time', 'semester_term');
        $session_data['session_id'] = $session_id_edit;

        // Basic Validation
        if (empty($course_id) || empty($room_id) || empty($day_of_week) || empty($start_time) || empty($end_time) || empty($semester_term)) {
            $error = "All fields except Teacher are required.";
        } elseif (strtotime($start_time) >= strtotime($end_time)) {
            $error = "Start time must be before end time.";
        } else {
            // Conflict Check
            // 1. Room Conflict
            $stmt_check_room = $db->prepare(
                "SELECT session_id FROM class_sessions 
                 WHERE room_id = ? AND day_of_week = ? AND semester_term = ?
                   AND start_time < ? AND end_time > ?
                   AND (? IS NULL OR session_id != ?)"
            );
            $stmt_check_room->bind_param("sssssii", $room_id, $day_of_week, $semester_term, $end_time, $start_time, $session_id_edit, $session_id_edit);
            $stmt_check_room->execute();
            if ($stmt_check_room->get_result()->num_rows > 0) {
                $error = "Room Conflict: This room is already booked at this time/day for this semester.";
            }
            $stmt_check_room->close();

            // 2. Teacher Conflict (if teacher is assigned)
            if (!$error && !empty($teacher_id)) {
                $stmt_check_teacher = $db->prepare(
                    "SELECT session_id FROM class_sessions 
                     WHERE teacher_id = ? AND day_of_week = ? AND semester_term = ?
                       AND start_time < ? AND end_time > ?
                       AND (? IS NULL OR session_id != ?)"
                );
                $stmt_check_teacher->bind_param("sssssii", $teacher_id, $day_of_week, $semester_term, $end_time, $start_time, $session_id_edit, $session_id_edit);
                $stmt_check_teacher->execute();
                if ($stmt_check_teacher->get_result()->num_rows > 0) {
                    $error = "Teacher Conflict: This teacher is already scheduled at this time/day for this semester.";
                }
                $stmt_check_teacher->close();
            }

            if (empty($error)) {
                try {
                        if (empty($session_id_edit)) { // Add new session
                            $stmt = $db->prepare("INSERT INTO class_sessions (course_id, teacher_id, room_id, day_of_week, start_time, end_time, semester_term) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("sssssss", $course_id, $teacher_id, $room_id, $day_of_week, $start_time, $end_time, $semester_term);
                        } else { // Update existing session
                            $stmt = $db->prepare("UPDATE class_sessions SET course_id=?, teacher_id=?, room_id=?, day_of_week=?, start_time=?, end_time=?, semester_term=? WHERE session_id=?");
                            $stmt->bind_param("sssssssi", $course_id, $teacher_id, $room_id, $day_of_week, $start_time, $end_time, $semester_term, $session_id_edit);
                        }

                        if ($stmt->execute()) {
                            $success = "Class session saved successfully!";
                            $session_data = ['session_id' => '', 'course_id' => '', 'teacher_id' => '', 'room_id' => '', 'day_of_week' => 'Monday', 'start_time' => '', 'end_time' => '', 'semester_term' => '']; // Reset form
                            unset($_GET['edit']); // Clear edit mode
                        } else {
                            $error = "Error saving session: " . $db->error;
                        }
                        $stmt->close();
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        } // Closes the else for basic validation
    } // This closes the `if (isset($_POST['save_session']))` block
    elseif (isset($_POST['delete_session'])) {
        $session_id_delete = $_POST['session_id_delete'];
        $stmt = $db->prepare("DELETE FROM class_sessions WHERE session_id = ?");
        $stmt->bind_param("i", $session_id_delete);
        if ($stmt->execute()) {
            $success = "Session deleted successfully!";
        } else {
            $error = "Error deleting session: " . $db->error;
        }
        $stmt->close();
    } // Closes elseif for delete_session
} // This closes the main if ($_SERVER['REQUEST_METHOD'] == 'POST')

// Load session for editing
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['edit'])) {
    $edit_id = trim($_GET['edit']);
    if (!empty($edit_id) && is_numeric($edit_id)) { // Added is_numeric check for robustness
        $stmt = $db->prepare("SELECT * FROM class_sessions WHERE session_id = ?");
        if ($stmt) { // Check if prepare was successful
            $stmt->bind_param("i", $edit_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $session_data = $result->fetch_assoc();
            } else {
                $error = "Session not found for editing.";
                unset($_GET['edit']);
            }
            $stmt->close();
        } else {
            $error = "Error preparing statement to load session for edit: " . $db->error;
        }
    } else {
        $error = "Invalid session ID for editing."; // More specific error
        unset($_GET['edit']);
    }
}

// Fetch all class sessions for display
$all_sessions = [];
$result = $db->query("
    SELECT cs.*, c.c_name, t.t_name, r.room_id as room_display_name
    FROM class_sessions cs
    JOIN courses c ON cs.course_id = c.c_id
    LEFT JOIN teachers t ON cs.teacher_id = t.t_id
    JOIN rooms r ON cs.room_id = r.room_id
    ORDER BY cs.semester_term DESC, FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time
"); // Added FIELD for logical day sorting
if ($result) {
    $all_sessions = $result->fetch_all(MYSQLI_ASSOC);
    $result->free(); // Free result set
} else {
    $error = "Error fetching class sessions: " . $db->error; // Handle query error
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules</title>
    <link rel="stylesheet" href="css/students.css"> <!-- Re-use existing admin CSS -->
</head>
<body>
    <div class="container">
        <?php require_once 'sidebar.php'; ?>
        <div class="main-content">
            <h1>Manage Class Schedules</h1>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <h2><?php echo empty($_GET['edit']) && empty($session_data['session_id']) ? 'Add New Class Session' : 'Edit Class Session'; ?></h2>
                <form method="POST" action="schedules.php<?php echo isset($_GET['edit']) ? '?edit=' . htmlspecialchars($_GET['edit']) : ''; ?>">
                    <input type="hidden" name="original_session_id" value="<?php echo htmlspecialchars($session_data['session_id'] ?? ''); ?>">

                    <div class="form-group">
                        <label for="semester_term">Term</label>
                        <select id="semester_term" name="semester_term" required>
                            <option value="">-- Select Term --</option>
                            <?php foreach ($terms_available as $term_option): ?>
                                <option value="<?php echo htmlspecialchars($term_option); ?>" 
                                    <?php echo (isset($session_data['semester_term']) && $session_data['semester_term'] == $term_option) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($term_option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="course_id">Course</label>
                        <select id="course_id" name="course_id" required>
                            <option value="">-- Select Course --</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course['c_id']); ?>" <?php echo ($session_data['course_id'] == $course['c_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['c_name']); ?> (<?php echo htmlspecialchars($course['c_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="teacher_id">Teacher (Optional)</label>
                        <select id="teacher_id" name="teacher_id">
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo htmlspecialchars($teacher['t_id']); ?>" <?php echo ($session_data['teacher_id'] == $teacher['t_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['t_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="room_id">Room</label>
                        <select id="room_id" name="room_id" required>
                            <option value="">-- Select Room --</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo htmlspecialchars($room['room_id']); ?>" <?php echo ($session_data['room_id'] == $room['room_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($room['room_id']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="day_of_week">Day of Week</label>
                        <select id="day_of_week" name="day_of_week" required>
                            <?php foreach ($days_of_week as $day): ?>
                                <option value="<?php echo $day; ?>" <?php echo ($session_data['day_of_week'] == $day) ? 'selected' : ''; ?>>
                                    <?php echo $day; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" value="<?php echo htmlspecialchars($session_data['start_time']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" value="<?php echo htmlspecialchars($session_data['end_time']); ?>" required>
                    </div>

                    <button type="submit" name="save_session">Save Session</button>
                    <?php if (!empty($_GET['edit']) || !empty($session_data['session_id'])): ?>
                        <a href="schedules.php" class="btn">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card">
                <h2>Scheduled Class Sessions</h2>
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search schedules...">
                </div>
                <table id="schedulesTable">
                    <thead>
                        <tr>
                            <th>Term</th>
                            <th>Course</th>
                            <th>Teacher</th>
                            <th>Room</th>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_sessions)): ?>
                            <tr><td colspan="7" style="text-align:center;">No class sessions scheduled yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_sessions as $session): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($session['semester_term']); ?></td>
                                    <td><?php echo htmlspecialchars($session['c_name']); ?> (<?php echo htmlspecialchars($session['course_id']); ?>)</td>
                                    <td><?php echo htmlspecialchars($session['t_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($session['room_display_name']); ?></td>
                                    <td><?php echo htmlspecialchars($session['day_of_week']); ?></td>
                                    <td><?php echo date("g:i A", strtotime($session['start_time'])); ?> - <?php echo date("g:i A", strtotime($session['end_time'])); ?></td>
                                    <td>
                                        <a href="schedules.php?edit=<?php echo $session['session_id']; ?>" class="btn btn-edit">Edit</a>
                                        <form method="POST" action="schedules.php" style="display:inline;">
                                            <input type="hidden" name="session_id_delete" value="<?php echo $session['session_id']; ?>">
                                            <button type="submit" name="delete_session" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this session?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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
            const rows = document.querySelectorAll('#schedulesTable tbody tr'); // Assuming your table has id="schedulesTable"

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
<?php if (isset($db) && $db instanceof mysqli) $db->close(); ?>
