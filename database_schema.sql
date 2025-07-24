-- Create the database (if it doesn't exist)
CREATE DATABASE IF NOT EXISTS school_management;

-- Use the database
USE school_management;

-- 1. Login/Credentials Table
CREATE TABLE login (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account VARCHAR(255) UNIQUE NOT NULL, -- Changed to store email
    password VARCHAR(50) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    user_id VARCHAR(10) DEFAULT NULL, -- References student/teacher ID, NULLable initially
    status ENUM('active','pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Students Table
CREATE TABLE students (
    stud_id VARCHAR(10) PRIMARY KEY,
    stud_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE,
    age INT,
    sex ENUM('M', 'F'),
    year INT,
    phone VARCHAR(20) DEFAULT NULL
);

-- 3. Teachers Table
CREATE TABLE teachers (
    t_id VARCHAR(10) PRIMARY KEY,
    t_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE,
    major VARCHAR(30),
    phone VARCHAR(20) DEFAULT NULL
);

-- 4. Courses Table
CREATE TABLE courses (
    c_id VARCHAR(10) PRIMARY KEY,
    c_name VARCHAR(50) NOT NULL,
    department VARCHAR(30),
    teacher_id VARCHAR(10),
    FOREIGN KEY (teacher_id) REFERENCES teachers(t_id) ON DELETE SET NULL ON UPDATE CASCADE
);

-- 5. Marks/Grades Table
CREATE TABLE marks (
    mark_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(10),
    course_id VARCHAR(10),
    grade DECIMAL(5,2),
    FOREIGN KEY (student_id) REFERENCES students(stud_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(c_id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE(student_id, course_id) -- Prevents duplicate entries
);

-- 6. Rooms Table
CREATE TABLE rooms (
    room_id VARCHAR(10) PRIMARY KEY,
    bid_num VARCHAR(10) NOT NULL,
    room_type ENUM('Lecture','Lab') NOT NULL
);

-- 7. Class Sessions Table (for schedules)
CREATE TABLE class_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    course_id VARCHAR(10) NOT NULL,
    teacher_id VARCHAR(10) NULL, -- Can be NULL if teacher not yet assigned
    room_id VARCHAR(10) NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    semester_term VARCHAR(50) NOT NULL, -- e.g., "First Term", "Second Term 2024"
    FOREIGN KEY (course_id) REFERENCES courses(c_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(t_id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE ON UPDATE CASCADE,
    -- Constraint to prevent overlapping sessions for the same room, day, and term
    UNIQUE KEY `unique_room_time_slot` (`room_id`, `day_of_week`, `start_time`, `semester_term`),
    UNIQUE KEY `unique_room_time_slot_end` (`room_id`, `day_of_week`, `end_time`, `semester_term`),
    -- Constraint to prevent overlapping sessions for the same teacher, day, and term
    UNIQUE KEY `unique_teacher_time_slot` (`teacher_id`, `day_of_week`, `start_time`, `semester_term`),
    UNIQUE KEY `unique_teacher_time_slot_end` (`teacher_id`, `day_of_week`, `end_time`, `semester_term`)
    -- Note: More complex overlapping logic (e.g., start_time < existing_end_time AND end_time > existing_start_time)
    -- is typically handled at the application layer or with more complex SQL constraints/triggers if needed.
    -- The UNIQUE keys above prevent exact start/end time overlaps for the same entity.
);


-- Sample Data
INSERT INTO students (stud_id, stud_name, email, age, sex, year, phone) VALUES
('S001', 'Akemu', 'akemu@example.com', 20, 'M', 2, NULL),
('S002', 'Megeras', 'megeras@example.com', 23, 'M', 3, NULL);

INSERT INTO teachers (t_id, t_name, email, major, phone) VALUES
('T001', 'Kemal', 'kemal@example.com', 'Mathematics', NULL),
('T002', 'Ujulu', 'ujulu@example.com', 'IT', NULL);

INSERT INTO courses (c_id, c_name, department, teacher_id) VALUES
('c001', 'Algebra', 'Mathematics', 'T001'),
('c002', 'Database', 'IT', 'T002');

-- Sample Login Data for Students and Teachers
-- Passwords are plain text to match current login.php and register.php logic
INSERT INTO login (account, password, role, user_id, status) VALUES
('akemu@example.com', 'studentpass', 'student', 'S001', 'active'),
('megeras@example.com', 'studentpass', 'student', 'S002', 'active');

INSERT INTO login (account, password, role, user_id, status) VALUES
('kemal@example.com', 'teacherpass', 'teacher', 'T001', 'active'),
('ujulu@example.com', 'teacherpass', 'teacher', 'T002', 'active');

-- Add an admin user
-- Ensure password handling matches your login.php logic (currently plain text)
INSERT INTO login (account, password, role, user_id, status) VALUES
('admin', 'admin', 'admin', 'admin_user', 'active');

-- Sample Room Data
INSERT INTO rooms (room_id, bid_num, room_type) VALUES
('R101', 'B001', 'Lecture'),
('R102', 'B001', 'Lab'),
('R201', 'B002', 'Lecture');

-- Sample Marks Data (Optional, for testing grades.php and marks.php)
INSERT INTO marks (student_id, course_id, grade) VALUES
('S001', 'c001', 85.50),
('S001', 'c002', 92.00),
('S002', 'c001', 78.00);

-- Sample Class Session Data (Optional, for testing schedules)
INSERT INTO class_sessions (course_id, teacher_id, room_id, day_of_week, start_time, end_time, semester_term) VALUES
('c001', 'T001', 'R101', 'Monday', '09:00:00', '10:30:00', 'First Term'),
('c002', 'T002', 'R102', 'Tuesday', '10:00:00', '11:30:00', 'First Term'),
('c001', 'T001', 'R101', 'Wednesday', '09:00:00', '10:30:00', 'First Term');