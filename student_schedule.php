<?php
session_start();
require_once "dbcon.php";

// Verify student access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

$terms_available = ["First Term", "Second Term", "Third Term", "Forth Term"];
$selected_term = isset($_GET['term']) && in_array($_GET['term'], $terms_available) ? $_GET['term'] : $terms_available[0];

// Fetch courses student is enrolled in (via marks table for the current semester)
$student_course_ids = [];
$stmt_student_courses = $db->prepare(
    // Assuming marks table has a semester_term or you filter by active courses
    // For simplicity, let's assume all marks are for current/relevant courses
    // A more robust system might have an explicit enrollments table or semester in marks
    "SELECT DISTINCT m.course_id 
     FROM marks m
     WHERE m.student_id = ?" 
     // Add "AND m.semester_term = ?" if marks are term-specific and you want to filter
);
$stmt_student_courses->bind_param("s", $student_id);
$stmt_student_courses->execute();
$course_results = $stmt_student_courses->get_result();
while($row = $course_results->fetch_assoc()){
    $student_course_ids[] = $row['course_id'];
}
$stmt_student_courses->close();

$schedule_data = [];
if (!empty($student_course_ids) && $selected_term) {
    $placeholders = implode(',', array_fill(0, count($student_course_ids), '?'));
    $types = str_repeat('s', count($student_course_ids));
    
    $sql_schedule = "SELECT cs.*, c.c_name, t.t_name, r.room_id as room_display_name
                     FROM class_sessions cs
                     JOIN courses c ON cs.course_id = c.c_id
                     LEFT JOIN teachers t ON cs.teacher_id = t.t_id
                     JOIN rooms r ON cs.room_id = r.room_id
                     WHERE cs.course_id IN ($placeholders) 
                     AND cs.semester_term = ? 
                     ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time";
    
    $stmt_schedule = $db->prepare($sql_schedule); // Error check this prepare
    if ($stmt_schedule) {
        $params = array_merge($student_course_ids, [$selected_term]);
        $stmt_schedule->bind_param($types . "s", ...$params);
        $stmt_schedule->execute();
        $schedule_data = $stmt_schedule->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_schedule->close();
    } else {
        // Handle error in preparing statement
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - <?php echo htmlspecialchars($selected_term); ?></title>
    <link rel="stylesheet" href="css/students.css"> <!-- Or your general student-facing CSS -->
    <style>
        /* Add any specific styles for schedule page */
        .main-content { padding: 20px; margin: 0px; display: flex; justify-content: center; flex-direction: column;}
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
                #sidebar {
        background: transparent;
        color: black;
        padding: 20px 0;
        position: absolute;
        top: 0;
        right: 180px;
        display: flex;
        height: fit-content;
        gap: 8px;
        }
        #sidebar h2 {
            color: black;
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 10px;
        }
        #sidebar a {
            color: black;
            padding: 15px 20px;
            text-decoration: none;
            display: block;
            transition: 0.3s;
            border-bottom: 1px solid black;
        }
        #sidebar a:hover {
            background: transparent;
            border-bottom: 1px solid #3498db;
        }
    </style>
</head>
<body>
    <?php // You might want to include a student-specific sidebar or header here ?>
    <div class="main-content">
        <h1>My Class Schedule - <?php echo htmlspecialchars($selected_term); ?></h1>

        <form method="GET" action="student_schedule.php" style="margin-bottom: 20px;">
            <label for="term">Select Term:</label>
            <select name="term" id="term" onchange="this.form.submit()">
                <?php foreach ($terms_available as $term_option): ?>
                    <option value="<?php echo htmlspecialchars($term_option); ?>" <?php echo ($selected_term == $term_option) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($term_option); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit">View Schedule</button></noscript>
        </form>
            <div id="sidebar">
                <a href="grades.php" <?php echo basename($_SERVER['PHP_SELF']) == 'grades.php' ? 'style="background: #34495e;"' : ''; ?>>My Grades</a>
                <a href="login.php" <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'style="background: #34495e;"' : ''; ?>>Login</a>
                <a href="register.php" <?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'style="background: #34495e;"' : ''; ?>>Register</a>
                <a href="logout.php" style="color: #e74c3c;">Logout</a>
            </div>

        <table>
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Course</th>
                    <th>Room</th>
                    <th>Teacher</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schedule_data)): ?>
                    <tr><td colspan="5" style="text-align:center;">No classes scheduled for you this semester, or no schedule available.</td></tr>
                <?php else: ?>
                    <?php foreach ($schedule_data as $session): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($session['day_of_week']); ?></td>
                            <td><?php echo date("g:i A", strtotime($session['start_time'])); ?> - <?php echo date("g:i A", strtotime($session['end_time'])); ?></td>
                            <td><?php echo htmlspecialchars($session['c_name']); ?> (<?php echo htmlspecialchars($session['course_id']); ?>)</td>
                            <td><?php echo htmlspecialchars($session['room_display_name']); ?></td>
                            <td><?php echo htmlspecialchars($session['t_name'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php $db->close(); ?>