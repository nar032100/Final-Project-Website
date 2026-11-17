CREATE TABLE IF NOT EXISTS users (

    id INT AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(100) NOT NULL,

    email VARCHAR(100) UNIQUE NOT NULL,

    password VARCHAR(255) NOT NULL,

    role ENUM('student', 'teacher') NOT NULL DEFAULT 'student',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);



CREATE TABLE IF NOT EXISTS activities (

    id INT AUTO_INCREMENT PRIMARY KEY,

    teacher_id INT NOT NULL,

    title VARCHAR(150) NOT NULL,

    description TEXT,

    deadline DATETIME,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE

);



CREATE TABLE IF NOT EXISTS projects (

    id INT AUTO_INCREMENT PRIMARY KEY,

    student_id INT NOT NULL,

    title VARCHAR(150) NOT NULL,

    project_url VARCHAR(255),

    file_path VARCHAR(255),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE

);



CREATE TABLE IF NOT EXISTS submissions (

    id INT AUTO_INCREMENT PRIMARY KEY,

    activity_id INT NOT NULL,

    student_id INT NOT NULL,

    submission_url VARCHAR(255),

    file_path VARCHAR(255),

    grade VARCHAR(10) DEFAULT NULL,

    feedback TEXT DEFAULT NULL,

    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE,

    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE

);



CREATE TABLE IF NOT EXISTS feedback (

    id INT AUTO_INCREMENT PRIMARY KEY,

    student_id INT NOT NULL,

    sender_name VARCHAR(100) NOT NULL,

    comment TEXT NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE

);



CREATE TABLE IF NOT EXISTS notifications (

    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    message VARCHAR(255) NOT NULL,

    is_read TINYINT(1) DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE

);


ALTER TABLE feedback ADD COLUMN teacher_id INT DEFAULT NULL AFTER student_id;