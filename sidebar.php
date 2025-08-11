<?php
// Render Menu if user role is admin
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'admin') { ?>
    <div id="sidebar">
    <h2 style="padding: 0 20px;">School Management</h2>
    <a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'style="background: #34495e;"' : ''; ?>>Dashboard</a>
    <a href="students.php" <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'style="background: #34495e;"' : ''; ?>>Students</a>
    <a href="teachers.php" <?php echo basename($_SERVER['PHP_SELF']) == 'teachers.php' ? 'style="background: #34495e;"' : ''; ?>>Teachers</a>
    <a href="courses.php" <?php echo basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'style="background: #34495e;"' : ''; ?>>Courses</a>
    <a href="rooms.php" <?php echo basename($_SERVER['PHP_SELF']) == 'rooms.php' ? 'style="background: #34495e;"' : ''; ?>>Rooms</a>
    <a href="schedules.php" <?php echo basename($_SERVER['PHP_SELF']) == 'schedules.php' ? 'style="background: #34495e;"' : ''; ?>>Schedules</a>
    <a href="marks.php" <?php echo basename($_SERVER['PHP_SELF']) == 'marks.php' ? 'style="background: #34495e;"' : ''; ?>>Marks</a>
    <hr>
    <a href="logout.php" style="color: #e74c3c;">Logout</a>
</div>
<?php }?>

<?php
// Render Menu if user role is teacher
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'teacher') { ?>
    <div id="sidebar">
    <h2 style="padding: 0 20px;">School Management</h2>
    <a href="teacher_schedule.php" <?php echo basename($_SERVER['PHP_SELF']) == 'teacher_schedule.php' ? 'style="background: #34495e;"' : ''; ?>>My Schedule</a>
    <a href="marks.php" <?php echo basename($_SERVER['PHP_SELF']) == 'marks.php' ? 'style="background: #34495e;"' : ''; ?>>Marks</a>
    <hr>
    <a href="login.php" <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'style="background: #34495e;"' : ''; ?>>Login</a>
    <a href="register.php" <?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'style="background: #34495e;"' : ''; ?>>Register</a>
    <a href="logout.php" style="color: #e74c3c;">Logout</a>
</div>
<?php }?>

<style>
#sidebar {
    width: 250px;
    background: #2c3e50;
    color: white;
    padding: 20px 0;
    position: fixed;
    height: 100vh;
}
#sidebar h2 {
    color: white;
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
#sidebar a {
    color: white;
    padding: 15px 20px;
    text-decoration: none;
    display: block;
    transition: 0.3s;
}
#sidebar a:hover {
    background: #34495e;
}
</style>