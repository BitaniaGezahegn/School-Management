<?php
// Start session and check admin role
session_start();
if ($_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit();
}

require_once "dbcon.php";

// Fetch counts
$students_count = $db->query("SELECT COUNT(*) FROM students")->fetch_row()[0];
$teachers_count = $db->query("SELECT COUNT(*) FROM teachers")->fetch_row()[0];
$courses_count = $db->query("SELECT COUNT(*) FROM courses")->fetch_row()[0];

// Fetch more analytical data
$students_by_year = $db->query("SELECT year, COUNT(*) as count FROM students GROUP BY year ORDER BY year");
$teachers_by_major = $db->query("SELECT major, COUNT(*) as count FROM teachers WHERE major IS NOT NULL AND major != '' GROUP BY major ORDER BY major");
$courses_by_department = $db->query("SELECT department, COUNT(*) as count FROM courses WHERE department IS NOT NULL AND department != '' GROUP BY department ORDER BY department");
$sessions_count = $db->query("SELECT COUNT(*) FROM class_sessions")->fetch_row()[0];
$average_grade_result = $db->query("SELECT AVG(grade) FROM marks");
$average_grade = $average_grade_result ? number_format($average_grade_result->fetch_row()[0] ?? 0, 2) : 'N/A';

// Fetch results into arrays
$students_by_year_data = [];
if ($students_by_year) {
    while ($row = $students_by_year->fetch_assoc()) {
        $students_by_year_data[] = $row;
    }
    $students_by_year->free();
}
$teachers_by_major_data = $teachers_by_major ? $teachers_by_major->fetch_all(MYSQLI_ASSOC) : [];
$courses_by_department_data = $courses_by_department ? $courses_by_department->fetch_all(MYSQLI_ASSOC) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | School Management</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php require_once "sidebar.php"; ?>
    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <div>Welcome, <?php echo $_SESSION['account']; ?></div>
        </div>

        <!-- Summary Cards with PHP Data -->
        <div class="card-container">
            <div class="card">
                <h3>Total Students</h3>
                <p><?php echo $students_count; ?></p>
            </div>
            <div class="card">
                <h3>Total Teachers</h3>
                <p><?php echo $teachers_count; ?></p>
            </div>
            <div class="card">
                <h3>Total Courses</h3>
                <p><?php echo $courses_count; ?></p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3>Quick Actions</h3>
            <div class="action-buttons">
                <a href="students.php" class="btn">Add Student</a>
                <a href="teachers.php" class="btn">Add Teacher</a>
                <a href="courses.php" class="btn">Add Course</a>
                <a href="rooms.php" class="btn">Add Room</a>
            </div>
        </div>

        <!-- More Analytical Data -->
        <div class="analytics-container">
            <div class="card">
                <h3>Students by Year</h3>
                <ul>
                    <?php if (empty($students_by_year_data)): ?>
                        <li>No student data available.</li>
                    <?php else: ?>
                        <?php foreach ($students_by_year_data as $data): ?>
                            <li>Year <?php echo htmlspecialchars($data['year']); ?>: <?php echo htmlspecialchars($data['count']); ?></li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="card">
                <h3>Teachers by Major</h3>
                <ul>
                     <?php if (empty($teachers_by_major_data)): ?>
                        <li>No major data available.</li>
                    <?php else: ?>
                        <?php foreach ($teachers_by_major_data as $data): ?>
                            <li><?php echo htmlspecialchars($data['major']); ?>: <?php echo htmlspecialchars($data['count']); ?></li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="card">
                <h3>Courses by Department</h3>
                 <ul>
                     <?php if (empty($courses_by_department_data)): ?>
                        <li>No department data available.</li>
                    <?php else: ?>
                        <?php foreach ($courses_by_department_data as $data): ?>
                            <li><?php echo htmlspecialchars($data['department']); ?>: <?php echo htmlspecialchars($data['count']); ?></li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

             <div class="card">
                <h3>Scheduled Sessions</h3>
                <p><?php echo htmlspecialchars($sessions_count); ?></p>
            </div>

             <div class="card">
                <h3>Average Grade</h3>
                <p><?php echo htmlspecialchars($average_grade); ?></p>
            </div>
        </div>
    </div>
</body>
</html>
<?php $db->close(); ?>