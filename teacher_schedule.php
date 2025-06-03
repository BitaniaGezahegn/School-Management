<?php
session_start();
require_once "dbcon.php";

// Verify teacher access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

$terms_available = ["First Term", "Second Term", "Third Term", "Forth Term"];
$selected_term = isset($_GET['term']) && in_array($_GET['term'], $terms_available) ? $_GET['term'] : $terms_available[0];

$schedule_data = [];

if ($selected_term) {
    $stmt_teacher_schedule = $db->prepare(
        "SELECT cs.*, c.c_name, r.room_id as room_display_name
         FROM class_sessions cs
         JOIN courses c ON cs.course_id = c.c_id
         JOIN rooms r ON cs.room_id = r.room_id
         WHERE cs.teacher_id = ? AND cs.semester_term = ?
         ORDER BY FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time"
    );
    if ($stmt_teacher_schedule) {
        $stmt_teacher_schedule->bind_param("ss", $teacher_id, $selected_term);
        $stmt_teacher_schedule->execute();
        $schedule_data = $stmt_teacher_schedule->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_teacher_schedule->close();
    } else {
        // Handle error in preparing statement, e.g., log it or display a message
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Teaching Schedule - <?php echo htmlspecialchars($selected_term); ?></title>
    <link rel="stylesheet" href="css/students.css"> <!-- Includes styles for .main-content margin -->
     <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <?php require_once "sidebar.php"; ?>
     <div class="main-content">
        <h1>My Teaching Schedule - <?php echo htmlspecialchars($selected_term); ?></h1>

        <form method="GET" action="teacher_schedule.php" style="margin-bottom: 20px;">
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


        <table>
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Course</th>
                    <th>Room</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schedule_data)): ?>
                    <tr><td colspan="4" style="text-align:center;">No classes scheduled for you this semester.</td></tr>
                <?php else: ?>
                    <?php foreach ($schedule_data as $session): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($session['day_of_week']); ?></td>
                            <td><?php echo date("g:i A", strtotime($session['start_time'])); ?> - <?php echo date("g:i A", strtotime($session['end_time'])); ?></td>
                            <td><?php echo htmlspecialchars($session['c_name']); ?> (<?php echo htmlspecialchars($session['course_id']); ?>)</td>
                            <td><?php echo htmlspecialchars($session['room_display_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php $db->close(); ?>