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
    </div>
</body>
</html>
<?php $db->close(); ?>