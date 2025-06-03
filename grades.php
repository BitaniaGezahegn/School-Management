<?php
require_once "dbcon.php";
require_once "sidebar.php";
session_start();

// Verify student access
if ($_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

// Get student ID from session
$student_id = $_SESSION['user_id'];

// Fetch student details
$student = [];
$stmt = $db->prepare("SELECT stud_id, stud_name, year FROM students WHERE stud_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// Fetch student's grades with course names
$grades = [];
$stmt = $db->prepare("
    SELECT m.grade, c.c_id, c.c_name, c.department, t.t_name as teacher_name
    FROM marks m
    JOIN courses c ON m.course_id = c.c_id
    JOIN teachers t ON c.teacher_id = t.t_id
    WHERE m.student_id = ?
    ORDER BY c.c_name
");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$grades = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate GPA
$total_credits = 0;
$total_points = 0;
foreach ($grades as $grade) {
    $letter_grade = '';
    $grade_points = 0;
    
    if ($grade['grade'] >= 90) {
        $letter_grade = 'A';
        $grade_points = 4.0;
    } elseif ($grade['grade'] >= 80) {
        $letter_grade = 'B';
        $grade_points = 3.0;
    } elseif ($grade['grade'] >= 70) {
        $letter_grade = 'C';
        $grade_points = 2.0;
    } elseif ($grade['grade'] >= 60) {
        $letter_grade = 'D';
        $grade_points = 1.0;
    } else {
        $letter_grade = 'F';
        $grade_points = 0.0;
    }
    
    // Assuming each course is worth 3 credits (adjust as needed)
    $credits = 3;
    $total_credits += $credits;
    $total_points += ($grade_points * $credits);
}

$gpa = $total_credits > 0 ? $total_points / $total_credits : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .container {
            display: flex;
            min-height: 100vh;
        }
        .main-content {
            flex: 1;
            padding: 20px;
        }
        .student-info {
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .student-info h2 {
            margin-top: 0;
            color: #2c3e50;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        .info-item {
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            color: #7f8c8d;
        }
        .grades-card {
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #f9f9f9;
        }
        .grade-A { background-color: #dff0d8; }
        .grade-B { background-color: #d9edf7; }
        .grade-C { background-color: #fcf8e3; }
        .grade-D { background-color: #f2dede; }
        .grade-F { background-color: #f2dede; font-weight: bold; }
        .gpa-display {
            margin-top: 20px;
            padding: 15px;
            background-color: #e8f4f8;
            border-radius: 5px;
            text-align: center;
            font-size: 1.2em;
        }
        .gpa-value {
            font-weight: bold;
            color: #2c3e50;
            font-size: 1.5em;
        }
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
    <div class="container">
        <!-- Main Content -->
        <div class="main-content">
            <h1>My Academic Record</h1>
            <div id="sidebar">
                    <a href="student_schedule.php" <?php echo basename($_SERVER['PHP_SELF']) == 'student_schedule.php' ? 'style="background: #34495e;"' : ''; ?>>My Schedule</a>
                    <a href="login.php" <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'style="background: #34495e;"' : ''; ?>>Login</a>
                    <a href="register.php" <?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'style="background: #34495e;"' : ''; ?>>Register</a>
                    <a href="logout.php" style="color: #e74c3c;">Logout</a>
            </div>
            
            <!-- Student Information -->
            <div class="student-info">
                <h2>Student Profile</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Student ID</div>
                        <div><?php echo htmlspecialchars($student['stud_id']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div><?php echo htmlspecialchars($student['stud_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Year</div>
                        <div>Year <?php echo htmlspecialchars($student['year']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Grades Summary -->
            <div class="grades-card">
                <h2>Course Grades</h2>
                
                <table>
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Department</th>
                            <th>Teacher</th>
                            <th>Grade</th>
                            <th>Letter</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades as $grade): 
                            $letter_grade = '';
                            $grade_class = '';
                            if ($grade['grade'] >= 90) {
                                $letter_grade = 'A';
                                $grade_class = 'grade-A';
                            } elseif ($grade['grade'] >= 80) {
                                $letter_grade = 'B';
                                $grade_class = 'grade-B';
                            } elseif ($grade['grade'] >= 70) {
                                $letter_grade = 'C';
                                $grade_class = 'grade-C';
                            } elseif ($grade['grade'] >= 60) {
                                $letter_grade = 'D';
                                $grade_class = 'grade-D';
                            } else {
                                $letter_grade = 'F';
                                $grade_class = 'grade-F';
                            }
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($grade['c_id']); ?></td>
                                <td><?php echo htmlspecialchars($grade['c_name']); ?></td>
                                <td><?php echo htmlspecialchars($grade['department']); ?></td>
                                <td><?php echo htmlspecialchars($grade['teacher_name']); ?></td>
                                <td class="<?php echo $grade_class; ?>"><?php echo htmlspecialchars($grade['grade']); ?></td>
                                <td class="<?php echo $grade_class; ?>"><?php echo $letter_grade; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($grades)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No grades recorded yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if (!empty($grades)): ?>
                <div class="gpa-display">
                    Cumulative GPA: <span class="gpa-value"><?php echo number_format($gpa, 2); ?></span>
                    (on a 4.0 scale)
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php $db->close(); ?>