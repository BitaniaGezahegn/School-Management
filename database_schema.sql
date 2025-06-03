-- 1. Login/Credentials Table
CREATE TABLE login (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account VARCHAR(255) UNIQUE NOT NULL, -- Changed to store email
    password VARCHAR(50) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    user_id VARCHAR(10) DEFAULT NULL, -- References student/teacher ID, NULLable initially
    status ENUM('active','pending') DEFAULT 'active', -- From Solution Architecture
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP -- From Solution Architecture
);

-- 2. Students Table
CREATE TABLE students (
    stud_id VARCHAR(10) PRIMARY KEY,
    stud_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE, -- Added for registration
    age INT,
    sex ENUM('M', 'F'),
    year INT,
    phone VARCHAR(20) DEFAULT NULL -- From Solution Architecture
);

-- 3. Teachers Table
CREATE TABLE teachers (
    t_id VARCHAR(10) PRIMARY KEY,
    t_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE, -- Added for registration
    major VARCHAR(30),
    phone VARCHAR(20) DEFAULT NULL -- From Solution Architecture
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

CREATE TABLE rooms (
    room_id VARCHAR(10) PRIMARY KEY,
    bid_num VARCHAR(10) NOT NULL,
    room_type ENUM('Lecture','Lab') NOT NULL
);
-- Sample Data
INSERT INTO students VALUES 
('S001', 'Akemu', 'akemu@example.com', 20, 'M', 2, NULL),
('S002', 'Megeras', 'megeras@example.com', 23, 'M', 3, NULL);

INSERT INTO teachers VALUES 
('T001', 'Kemal', 'kemal@example.com', 'Mathematics', NULL),
('T002', 'Ujulu', 'ujulu@example.com', 'IT', NULL);

INSERT INTO courses VALUES
('c001', 'Algebra', 'Mathematics', 'T001'),
('c002', 'Database', 'IT', 'T002');